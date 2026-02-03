<?php

declare(strict_types=1);

namespace App\Livewire\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('components.layouts.app')]
#[Title('Product Import/Export')]
final class ProductImportExport extends Component
{
    use WithFileUploads;

    public $file;

    public bool $showResults = false;

    public int $imported = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public array $importErrors = [];

    public array $exportFields = [
        'sku' => true,
        'title' => true,
        'brand' => true,
        'category_name' => true,
        'purchase_price' => true,
        'retail_price' => true,
        'shipping_cost' => true,
        'default_tax_rate' => true,
        'weight' => true,
        'barcode' => true,
        'stock_minimum' => true,
        'is_active' => true,
    ];

    public function export(): StreamedResponse
    {
        $selectedFields = array_keys(array_filter($this->exportFields));

        return response()->streamDownload(function () use ($selectedFields) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, $selectedFields);

            Product::query()
                ->select($selectedFields)
                ->orderBy('sku', 'desc')
                ->chunk(500, function ($products) use ($handle, $selectedFields) {
                    foreach ($products as $product) {
                        $row = [];
                        foreach ($selectedFields as $field) {
                            $value = $product->{$field};

                            if ($field === 'is_active') {
                                $value = $value ? '1' : '0';
                            }

                            $row[] = $value;
                        }
                        fputcsv($handle, $row);
                    }
                });

            fclose($handle);
        }, 'products-export-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function import(): void
    {
        $this->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $this->resetImportState();

        $path = $this->file->getRealPath();
        $handle = fopen($path, 'r');

        if ($handle === false) {
            $this->importErrors[] = ['row' => 0, 'sku' => 'N/A', 'message' => 'Could not open file'];
            $this->showResults = true;

            return;
        }

        $headers = fgetcsv($handle);

        if ($headers === false || ! in_array('sku', $headers, true)) {
            $this->importErrors[] = ['row' => 0, 'sku' => 'N/A', 'message' => 'CSV must have a "sku" column'];
            $this->showResults = true;
            fclose($handle);

            return;
        }

        $headerMap = array_flip($headers);
        $rowNumber = 1;

        $editableFields = [
            'title',
            'description',
            'brand',
            'category_name',
            'purchase_price',
            'retail_price',
            'shipping_cost',
            'default_tax_rate',
            'weight',
            'barcode',
            'stock_minimum',
            'is_active',
        ];

        DB::beginTransaction();

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count($row) !== count($headers)) {
                $this->importErrors[] = [
                    'row' => $rowNumber,
                    'sku' => 'N/A',
                    'message' => 'Column count mismatch',
                ];
                $this->skipped++;

                continue;
            }

            $data = array_combine($headers, $row);
            $sku = trim($data['sku'] ?? '');

            if (empty($sku)) {
                $this->importErrors[] = [
                    'row' => $rowNumber,
                    'sku' => 'N/A',
                    'message' => 'Empty SKU',
                ];
                $this->skipped++;

                continue;
            }

            $product = Product::where('sku', $sku)->first();

            if (! $product) {
                $this->importErrors[] = [
                    'row' => $rowNumber,
                    'sku' => $sku,
                    'message' => 'SKU not found in database',
                ];
                $this->skipped++;

                continue;
            }

            $updates = [];

            foreach ($editableFields as $field) {
                if (! isset($headerMap[$field])) {
                    continue;
                }

                $value = trim($data[$field] ?? '');

                if ($value === '') {
                    continue;
                }

                $converted = $this->convertValue($field, $value);

                if ($converted !== null) {
                    $updates[$field] = $converted;
                }
            }

            if (empty($updates)) {
                $this->skipped++;

                continue;
            }

            $product->update($updates);
            $this->updated++;
        }

        DB::commit();
        fclose($handle);

        $this->showResults = true;
        $this->file = null;

        Log::info('Product import completed', [
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => count($this->importErrors),
        ]);
    }

    public function downloadTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'sku',
                'title',
                'brand',
                'category_name',
                'purchase_price',
                'retail_price',
                'shipping_cost',
                'default_tax_rate',
                'weight',
                'barcode',
                'stock_minimum',
                'is_active',
            ]);

            fputcsv($handle, [
                'EXAMPLE-SKU-001',
                'Example Product Title',
                'Example Brand',
                'Example Category',
                '10.50',
                '24.99',
                '3.50',
                '20',
                '0.5',
                '1234567890123',
                '5',
                '1',
            ]);

            fclose($handle);
        }, 'products-import-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function resetImportState(): void
    {
        $this->showResults = false;
        $this->imported = 0;
        $this->updated = 0;
        $this->skipped = 0;
        $this->importErrors = [];
    }

    public function render()
    {
        return view('livewire.products.product-import-export', [
            'productCount' => Product::count(),
        ]);
    }

    private function convertValue(string $field, string $value): mixed
    {
        return match ($field) {
            'purchase_price', 'retail_price', 'shipping_cost', 'weight' => $this->parseDecimal($value),
            'default_tax_rate' => $this->parseDecimal($value, max: 100),
            'stock_minimum' => $this->parseInt($value),
            'is_active' => $this->parseBool($value),
            default => $value,
        };
    }

    private function parseDecimal(string $value, ?float $max = null): ?float
    {
        $value = str_replace(['£', '$', ',', ' '], '', $value);
        $value = str_replace('%', '', $value);

        if (! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        if ($float < 0) {
            return null;
        }

        if ($max !== null && $float > $max) {
            return null;
        }

        return $float;
    }

    private function parseInt(string $value): ?int
    {
        $value = str_replace([',', ' '], '', $value);

        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) $value;

        return $int >= 0 ? $int : null;
    }

    private function parseBool(string $value): ?bool
    {
        $value = strtolower(trim($value));

        if (in_array($value, ['1', 'true', 'yes', 'y', 'active'], true)) {
            return true;
        }

        if (in_array($value, ['0', 'false', 'no', 'n', 'inactive'], true)) {
            return false;
        }

        return null;
    }
}
