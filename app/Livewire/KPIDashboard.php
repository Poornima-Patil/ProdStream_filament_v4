<?php

namespace App\Livewire;

use App\Services\KPIService;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class KPIDashboard extends Component
{
    public $fromDate;
    public $toDate;
    public $kpis = [];
    public $lastUpdated;
    public $currentFactory;

    // Modal properties
    public $showKPIDetail = false;
    public $selectedKPI = '';
    public $selectedKPITitle = '';

    protected $listeners = [
        'refreshKPIs' => 'loadKPIs'
    ];

    public function mount()
    {
        // Get current user's factory (multi-tenant)
        $this->currentFactory = Auth::user()->factory;

        // Set default date range to current month (past 30 days)
        $today = now();
        $this->toDate = $today->format('Y-m-d');
        $this->fromDate = $today->copy()->subDays(30)->format('Y-m-d');
        

        // Load initial KPI data
        $this->loadKPIs();
        $this->lastUpdated = now()->format('H:i:s');
    }

    public function loadKPIs()
    {
        try {
            $kpiService = new KPIService();

            // Always use current user's factory ID with custom date range
            $this->kpis = $kpiService->getExecutiveKPIsWithDateRange(
                $this->currentFactory->id,
                $this->fromDate,
                $this->toDate
            );

            // Debug: Log the KPI data to see what's being returned
            Log::info('KPI Dashboard Data:', [
                'factory_id' => $this->currentFactory->id,
                'from_date' => $this->fromDate,
                'to_date' => $this->toDate,
                'kpis_count' => count($this->kpis),
                'kpis_keys' => array_keys($this->kpis),
                'work_order_data' => $this->kpis['work_order_completion_rate'] ?? 'NOT_SET',
                'production_data' => $this->kpis['production_throughput'] ?? 'NOT_SET'
            ]);

            $this->lastUpdated = now()->format('H:i:s');
        } catch (\Exception $e) {
            Log::error('Error loading KPIs: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Error loading KPIs: ' . $e->getMessage()
            ]);
        }
    }

    public function updatedFromDate()
    {
        $this->validateDateRange();
        $this->loadKPIs();
    }

    public function updatedToDate()
    {
        $this->validateDateRange();
        $this->loadKPIs();
    }

    private function validateDateRange()
    {
        if ($this->fromDate && $this->toDate && $this->fromDate > $this->toDate) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'From date cannot be later than To date'
            ]);
        }
    }

    public function refreshDashboard()
    {
        // Clear cache for this factory
        $kpiService = new KPIService();
        $kpiService->clearKPICache($this->currentFactory->id);

        // Reload KPIs
        $this->loadKPIs();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Dashboard refreshed successfully'
        ]);
    }

    public function viewKPIDetails($kpiType)
    {
        // Debug: Log to see if method is called
        Log::info('viewKPIDetails called with: ' . $kpiType);

        // Set modal properties
        $this->selectedKPI = $kpiType;
        $this->selectedKPITitle = $this->getKPITitle($kpiType);
        $this->showKPIDetail = true;

        // Also show notification for now
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Detailed view for ' . $this->selectedKPITitle . ' opened!'
        ]);
    }

    public function closeKPIDetail()
    {
        $this->showKPIDetail = false;
        $this->selectedKPI = '';
        $this->selectedKPITitle = '';
    }

    private function getKPITitle($kpiType)
    {
        $titles = [
            'work_order_completion_rate' => 'Work Order Completion Rate',
            'on_time_delivery_rate' => 'On-Time Delivery Rate',
            'quality_rate' => 'Production Throughput',
            'production_throughput' => 'Production Throughput (Daily)',
            'scrap_rate' => 'Scrap Rate',
            'machine_utilization' => 'Machine Utilization',
            'work_orders' => 'Work Orders',
            'machines' => 'Machine Status',
            'quality' => 'Quality Report',
        ];

        return $titles[$kpiType] ?? ucwords(str_replace('_', ' ', $kpiType));
    }

    public function exportKPIs()
    {
        // Future implementation for export
        $this->dispatch('notify', [
            'type' => 'info',
            'message' => 'Export functionality coming soon!'
        ]);
    }

    // Helper methods for UI

    public function getStatusColor($status)
    {
        return match ($status) {
            'excellent' => 'bg-green-500',
            'good' => 'bg-green-400',
            'warning' => 'bg-yellow-500',
            'critical' => 'bg-red-500',
            default => 'bg-gray-400'
        };
    }

    public function getStatusText($status)
    {
        return match ($status) {
            'excellent' => 'Excellent',
            'good' => 'Good',
            'warning' => 'Needs Attention',
            'critical' => 'Critical',
            default => 'No Data'
        };
    }

    public function getTrendIcon($trend)
    {
        if ($trend > 0) {
            return '↗'; // Up arrow
        } elseif ($trend < 0) {
            return '↘'; // Down arrow
        } else {
            return '→'; // Flat arrow
        }
    }

    public function getTrendColor($trend)
    {
        if ($trend > 0) {
            return 'text-green-600';
        } elseif ($trend < 0) {
            return 'text-red-600';
        } else {
            return 'text-gray-600';
        }
    }

    public function getDateRangeLabel()
    {
        if (!$this->fromDate || !$this->toDate) {
            return 'Date range not set';
        }
        
        $from = \Carbon\Carbon::parse($this->fromDate)->format('M d, Y');
        $to = \Carbon\Carbon::parse($this->toDate)->format('M d, Y');
        
        return "{$from} - {$to}";
    }

    public function render()
    {
        return view('livewire.kpi-dashboard');
    }
}
