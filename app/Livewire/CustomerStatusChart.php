<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\CustomerKPIService;
use Carbon\Carbon;
use Livewire\Attributes\On;

class CustomerStatusChart extends Component
{
    public $record;
    public $fromDate;
    public $toDate;
    public $factoryId;

    protected CustomerKPIService $customerKPIService;

    public function boot(CustomerKPIService $customerKPIService): void
    {
        $this->customerKPIService = $customerKPIService;
    }

    public function mount($record, $fromDate = null, $toDate = null, $factoryId = null)
    {
        $this->record = $record;
        $this->fromDate = $fromDate ?? Carbon::now()->subDays(30)->format('Y-m-d');
        $this->toDate = $toDate ?? Carbon::now()->format('Y-m-d');
        $this->factoryId = $factoryId;
    }

    public function getData()
    {
        if (!$this->record?->id || !$this->factoryId) {
            return [
                'labels' => [],
                'datasets' => []
            ];
        }

        // Get status distribution for customer
        $statusDistribution = $this->customerKPIService->getCustomerWorkOrderStatusDistribution(
            $this->record->id,
            $this->factoryId,
            $this->fromDate,
            $this->toDate
        );

        $statuses = ['Assigned', 'Start', 'Hold', 'Completed', 'Closed'];
        $counts = [];
        $statusColors = config('work_order_status');

        foreach ($statuses as $status) {
            $counts[] = $statusDistribution->get($status)?->count ?? 0;
        }

        $colors = [
            $statusColors['assigned'],
            $statusColors['start'],
            $statusColors['hold'],
            $statusColors['completed'],
            $statusColors['closed'],
        ];

        return [
            'labels' => $statuses,
            'datasets' => [
                [
                    'label' => 'Work Orders',
                    'data' => $counts,
                    'backgroundColor' => $colors,
                    'borderWidth' => 2,
                    'hoverOffset' => 10,
                ],
            ],
        ];
    }

    #[On('dateRangeUpdated')]
    public function updateDateRange($dateFrom, $dateTo): void
    {
        $this->fromDate = $dateFrom;
        $this->toDate = $dateTo;

        // Force re-render of the chart
        $this->render();
    }

    public function render()
    {
        return view('livewire.customer-status-chart');
    }
}
