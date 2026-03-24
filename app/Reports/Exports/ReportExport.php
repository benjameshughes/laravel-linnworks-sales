<?php

namespace App\Reports\Exports;

use App\Reports\AbstractReport;
use App\Reports\Enums\ExportFormat;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportExport
{
    public function __construct(
        private readonly AbstractReport $report,
        private readonly EloquentBuilder|QueryBuilder $query,
        private readonly array $filters,
        private readonly ExportFormat $format = ExportFormat::XLSX
    ) {}

    /**
     * Generate report and return file contents as string.
     * Prefer generateToFile() for large reports to avoid OOM.
     */
    public function generate(): string
    {
        $path = $this->generateToFile();
        $content = file_get_contents($path);
        @unlink($path);

        return $content;
    }

    /**
     * Generate report to a temp file and return the file path.
     * Uses cursor() to stream rows — only one row in PHP memory at a time.
     */
    public function generateToFile(): string
    {
        return match ($this->format) {
            ExportFormat::XLSX => $this->generateXLSX(),
            ExportFormat::CSV => $this->generateCSV(),
        };
    }

    private function generateXLSX(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $rowNum = 1;

        $sheet->setCellValue('A1', $this->report->name());
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $rowNum++;

        if (isset($this->filters['date_range'])) {
            $dateRange = $this->filters['date_range']['start'].' to '.$this->filters['date_range']['end'];
            $sheet->setCellValue('A'.$rowNum, 'Date Range: '.$dateRange);
            $sheet->getStyle('A'.$rowNum)->getFont()->setBold(true);
            $rowNum++;
        }

        $rowNum++;

        $columns = $this->report->columns();
        $colNum = 1;

        foreach ($columns as $columnKey => $columnConfig) {
            $label = $columnConfig['label'] ?? $columnKey;
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum).$rowNum, $label);
            $colNum++;
        }

        $sheet->getStyle($rowNum.':'.$rowNum)->getFont()->setBold(true);
        $sheet->getStyle($rowNum.':'.$rowNum)
            ->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('F3F4F6');

        $rowNum++;

        foreach ($this->query->cursor() as $row) {
            $colNum = 1;

            foreach ($columns as $columnKey => $columnConfig) {
                $value = $row->{$columnKey} ?? '';

                if (isset($columnConfig['type'])) {
                    $value = $this->formatValue($value, $columnConfig['type']);
                }

                $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNum).$rowNum, $value);
                $colNum++;
            }

            $rowNum++;
        }

        foreach (range(1, count($columns)) as $columnIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex))->setAutoSize(true);
        }

        $path = tempnam(sys_get_temp_dir(), 'report_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    private function generateCSV(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'report_');
        $output = fopen($path, 'w');

        $columns = $this->report->columns();
        $headers = array_map(fn ($config, $key) => $config['label'] ?? $key, $columns, array_keys($columns));
        fputcsv($output, $headers);

        foreach ($this->query->cursor() as $row) {
            $values = [];

            foreach ($columns as $columnKey => $columnConfig) {
                $value = $row->{$columnKey} ?? '';

                if (isset($columnConfig['type'])) {
                    $value = $this->formatValue($value, $columnConfig['type']);
                }

                $values[] = $value;
            }

            fputcsv($output, $values);
        }

        fclose($output);

        return $path;
    }

    private function formatValue(mixed $value, string $type): mixed
    {
        return match ($type) {
            'currency' => is_numeric($value) ? number_format($value, 2, '.', '') : $value,
            'percentage' => is_numeric($value) ? number_format($value, 2).'%' : $value,
            'integer' => is_numeric($value) ? (int) $value : $value,
            'decimal' => is_numeric($value) ? number_format($value, 2, '.', '') : $value,
            default => $value,
        };
    }
}
