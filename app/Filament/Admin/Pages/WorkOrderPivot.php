<?php

namespace App\Filament\Admin\Pages;

use App\Models\WorkOrder;
use Filament\Forms;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Support\Facades\Auth;


class WorkOrderPivot extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.admin.pages.work-order-pivot';
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';
    protected static ?string $navigationLabel = 'Pivot Table';

    protected static ?string $navigationGroup = 'Work Order Reports';
    public ?string $startDate=null;
    public ?string $endDate=null;
    public Collection $data;
    public Collection $pivotData;

    protected $queryString = [
        'startDate' => ['except' => null],
        'endDate' => ['except' => null],
    ];
    public function mount(): void
    {
        $this->startDate = request()->query('startDate', $this->startDate ?? now()->subMonth()->toDateString());
        $this->endDate = request()->query('endDate', $this->endDate ?? now()->toDateString());

        $this->data = collect();
        $this->pivotData = collect();
        $this->form->fill([
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);

        $this->loadData();
    }
    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            DatePicker::make('startDate')
                ->label('Start Date')
                ->required()
                ->displayFormat('Y-m-d') // Format displayed to the user
                ->format('Y-m-d'),       // Format stored in the backend

            DatePicker::make('endDate')
                ->label('End Date')
                ->required()
                ->displayFormat('Y-m-d') // Format displayed to the user
                ->format('Y-m-d'),       // Format stored in the backend
        ]);
    }
    public function applyFilters()
    {
        $formState = $this->form->getState();

        $this->startDate = $formState['startDate'];
        $this->endDate = $formState['endDate'];
        $tenant = auth()->user()->factory_id;
        return redirect()->route('filament.admin.pages.work-order-pivot', [
            'tenant' => $tenant,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ]);
    }

    public function loadData(): void
    {

        $factoryId = Auth::user()->factory_id;

        $this->pivotData = WorkOrder::with([
            'bom.purchaseOrder.partNumber',
            'machine',
            'operator.user'
        ])
            ->where('factory_id', $factoryId) // ✅ filter by the user's factory
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->get()
            ->map(function ($wo) {
                return [
                    'Work Order No' => (string) $wo->unique_id,
                    'BOM' => optional($wo->bom)->unique_id ?? '-',
                    'Part Number' => optional($wo->bom?->purchaseOrder?->partNumber)->partnumber ?? '-',
                    'Revision' => optional($wo->bom?->purchaseOrder?->partNumber)->revision ?? '-',
                    'Machine' => optional($wo->machine)->name ?? '-',
                    'Operator' => optional($wo->operator?->user)->first_name ?? '-',
                    'Qty' => (int) $wo->qty,
                    'Status' => $wo->status ?? '-',
                    'Start Time' => (string) $wo->start_time,
                    'End Time' => (string) $wo->end_time,
                    'OK Qty' => (int) $wo->ok_qtys ?? 0,
                    'KO Qty' => (int) $wo->scrapped_qtys ?? 0,
                ];
            });
    }
    public function getViewData(): array
    {
        return [
            'data' => $this->data,
            'pivotData' => $this->pivotData->values()->all(),
        ];
    }

    public function updatedStartDate()
    {
        $this->loadData();
    }

    public function updatedEndDate()
    {
        $this->loadData();
    }
}
