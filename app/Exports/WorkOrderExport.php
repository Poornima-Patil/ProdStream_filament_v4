<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class WorkOrderExport implements WithMultipleSheets
{
public function __construct(public string $start, public string $end) {}

public function sheets(): array
{
return [
new WorkOrderSheet($this->start, $this->end),
new WorkOrderPivotSheet($this->start, $this->end),
];
}
}
