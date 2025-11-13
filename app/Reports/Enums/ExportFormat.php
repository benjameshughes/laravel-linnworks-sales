<?php

namespace App\Reports\Enums;

enum ExportFormat: string
{
    case XLSX = 'xlsx';
    case CSV = 'csv';

    public function label(): string
    {
        return match ($this) {
            self::XLSX => 'Excel (XLSX)',
            self::CSV => 'CSV',
        };
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::CSV => 'text/csv',
        };
    }

    public function extension(): string
    {
        return $this->value;
    }
}
