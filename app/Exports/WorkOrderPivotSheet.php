<?php
namespace App\Exports;

use App\Models\WorkOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WorkOrderPivotSheet implements FromCollection, WithHeadings
{
public function __construct(public string $start, public string $end) {}

public function collection(): Collection
{
return WorkOrder::whereBetween('created_at', [$this->start, $this->end])
->get()
->groupBy('status')
->map(fn ($group, $status) => [
'Status' => $status,
'Total Work Orders' => $group->count(),
])
->values();
}

public function headings(): array
{
return ['Status', 'Total Work Orders'];
}
}
