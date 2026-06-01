<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReportRowsExport implements FromArray, WithHeadings
{
    public function __construct(
        protected array $columns,
        protected array $rows,
    ) {
    }

    public function headings(): array
    {
        return array_map(
            fn (array $column) => $column['label'],
            $this->columns,
        );
    }

    public function array(): array
    {
        return array_map(function (array $row) {
            return array_map(function (array $column) use ($row) {
                $value = $row[$column['key']] ?? null;

                if (is_bool($value)) {
                    return $value ? 'Da' : 'Ne';
                }

                return $value;
            }, $this->columns);
        }, $this->rows);
    }
}
