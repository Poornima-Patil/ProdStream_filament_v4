<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\CustomerInformation;
use App\Services\CustomerKPIService;
use Carbon\Carbon;
use Livewire\Attributes\On;

class CustomerAnalytics extends Component
{
    public $customer;
    public $fromDate;
    public $toDate;
    public $factoryId;

    protected CustomerKPIService $customerKPIService;

    public function boot(CustomerKPIService $customerKPIService): void
    {
        $this->customerKPIService = $customerKPIService;
    }

    public function mount($customer = null, $fromDate = null, $toDate = null, $factoryId = null): void
    {
        $this->customer = $customer;
        $this->fromDate = $fromDate ?? Carbon::now()->subDays(30)->format('Y-m-d');
        $this->toDate = $toDate ?? Carbon::now()->format('Y-m-d');
        $this->factoryId = $factoryId;
    }

    public function getWorkOrderStatusDistribution()
    {
        if (!$this->customer || !$this->factoryId) {
            return collect();
        }

        return $this->customerKPIService->getCustomerWorkOrderStatusDistribution(
            $this->customer->id,
            $this->factoryId,
            $this->fromDate,
            $this->toDate
        );
    }

    public function getQualityData()
    {
        if (!$this->customer || !$this->factoryId) {
            return null;
        }

        return $this->customerKPIService->getCustomerQualityData(
            $this->customer->id,
            $this->factoryId,
            $this->fromDate,
            $this->toDate
        );
    }

    public function getAnalyticsSummary()
    {
        if (!$this->customer || !$this->factoryId) {
            return null;
        }

        return $this->customerKPIService->getCustomerWorkOrderAnalytics(
            $this->customer->id,
            $this->factoryId,
            $this->fromDate,
            $this->toDate
        );
    }

    #[On('dateRangeUpdated')]
    public function updateDateRange($dateFrom, $dateTo): void
    {
        $this->fromDate = $dateFrom;
        $this->toDate = $dateTo;

        // Force re-render of the component
        $this->render();
    }

    public function render()
    {
        return view('livewire.customer-analytics');
    }
}
