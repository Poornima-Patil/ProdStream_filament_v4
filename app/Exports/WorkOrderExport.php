<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class WorkOrderExport implements WithMultipleSheets
{
    public function __construct(public array $filters) {}

    public function sheets(): array
    {
        return [
            new WorkOrderSheet($this->filters),
        ];
    }
}
