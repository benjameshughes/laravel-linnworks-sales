<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Set SQLite as source
config(['database.connections.sqlite_source' => config('database.connections.sqlite')]);
config(['database.connections.sqlite_source.database' => database_path('database.sqlite')]);

// Disable foreign key checks and strict mode for MySQL during migration
DB::statement('SET FOREIGN_KEY_CHECKS=0;');
DB::statement("SET sql_mode='NO_AUTO_VALUE_ON_ZERO';");

// Important tables to copy (in order due to foreign keys)
$tables = ['users', 'products', 'orders', 'order_items', 'order_shipping', 'order_notes', 'order_properties', 'order_identifiers', 'sync_logs'];

foreach ($tables as $table) {
    echo "Copying {$table}...\n";
    $count = DB::connection('sqlite_source')->table($table)->count();
    echo "  Total records: {$count}\n";

    if ($count === 0) {
        echo "  Skipping empty table\n\n";
        continue;
    }

    // Truncate MySQL table before copying to avoid duplicates
    echo "  Truncating MySQL table...\n";
    DB::table($table)->truncate();

    $copied = 0;
    DB::connection('sqlite_source')->table($table)->orderBy('id')->chunk(500, function($rows) use ($table, &$copied) {
        $data = $rows->map(function($row) use ($table) {
            $row = (array)$row;

            // Fix invalid MySQL dates
            foreach ($row as $key => $value) {
                if ($value === '0001-01-01 00:00:00' || $value === '0001-01-01') {
                    $row[$key] = null;
                }

                // Handle DST transition times (2024-03-31 01:XX:XX doesn't exist in UK timezone)
                // Add one hour to any time in the 01:00-01:59 range on DST transition dates
                if (is_string($value) && preg_match('/^(2024-03-31) 01:(\d{2}:\d{2})$/', $value, $matches)) {
                    $row[$key] = $matches[1] . ' 02:' . $matches[2];
                }
                // Also handle October DST transition (02:XX:XX occurs twice)
                if (is_string($value) && preg_match('/^(2024-10-27) 01:(\d{2}:\d{2})$/', $value, $matches)) {
                    $row[$key] = $matches[1] . ' 02:' . $matches[2];
                }
            }

            // Handle column mapping for order_items table
            if ($table === 'order_items') {
                // Map SQLite columns to MySQL columns
                $mappedRow = [];
                foreach ($row as $key => $value) {
                    switch ($key) {
                        case 'price_per_unit':
                            $mappedRow['unit_price'] = $value;
                            break;
                        case 'line_total':
                            $mappedRow['total_price'] = $value;
                            break;
                        case 'unit_cost':
                            $mappedRow['cost_price'] = $value;
                            break;
                        case 'tax_amount':
                            $mappedRow['tax_rate'] = $value;
                            break;
                        case 'metadata':
                            $mappedRow['item_attributes'] = $value;
                            break;
                        default:
                            // Keep other columns as-is
                            $mappedRow[$key] = $value;
                            break;
                    }
                }
                $row = $mappedRow;
            }

            return $row;
        })->toArray();

        DB::table($table)->insert($data);
        $copied += count($data);
        echo "  Copied {$copied} / " . DB::connection('sqlite_source')->table($table)->count() . " records\r";
    });

    echo "\n  ✓ Completed {$table}\n\n";
}

// Re-enable foreign key checks
DB::statement('SET FOREIGN_KEY_CHECKS=1;');

echo "All data copied successfully!\n";
