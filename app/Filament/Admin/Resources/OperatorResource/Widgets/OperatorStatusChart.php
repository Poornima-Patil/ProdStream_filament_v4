<?php

namespace App\Filament\Admin\Resources\OperatorResource\Widgets;

use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class OperatorStatusChart extends ChartWidget
{
    protected ?string $heading = 'Work Order Status Distribution';

    public function getHeading(): ?string
    {
        $baseHeading = 'Work Order Status Distribution';

        if ($this->dateFrom && $this->dateTo) {
            $fromDate = Carbon::parse($this->dateFrom)->format('M j, Y');
            $toDate = Carbon::parse($this->dateTo)->format('M j, Y');

            return $baseHeading.' ('.$fromDate.' - '.$toDate.')';
        }

        return $baseHeading;
    }

    protected static ?int $sort = 1;

    public ?\Illuminate\Database\Eloquent\Model $record = null;

    protected int|string|array $columnSpan = 'full';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public function mount(): void
    {
        // Date range should be passed from parent component
        // If not provided, default to last 30 days
        if (! $this->dateFrom || ! $this->dateTo) {
            $this->dateTo = Carbon::now()->format('Y-m-d');
            $this->dateFrom = Carbon::now()->subDays(30)->format('Y-m-d');
        }
    }

    #[On('dateRangeUpdated')]
    public function updateDateRange($dateFrom, $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;

        // Force widget to refresh
        $this->dispatch('$refresh');
    }

    protected function getData(): array
    {
        if (! $this->record?->id) {
            return [
                'labels' => [],
                'datasets' => [],
            ];
        }

        // Get work order status distribution for this operator with date filtering
        $query = DB::table('work_orders')
            ->where('operator_id', $this->record->id)
            ->where('factory_id', \Filament\Facades\Filament::getTenant()->id);

        // Apply date range filter
        if ($this->dateFrom && $this->dateTo) {
            $query->whereBetween('created_at', [
                Carbon::parse($this->dateFrom)->startOfDay(),
                Carbon::parse($this->dateTo)->endOfDay(),
            ]);
        }

        $statusDistribution = $query->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

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

    protected function getOptions(): RawJs|array
    {
        $js = <<<'JS'
    {
        responsive: true,
        maintainAspectRatio: false,
        aspectRatio: 1.1,
        cutout: '50%',
        plugins: {
            tooltip: {
                enabled: true,
                intersect: false,
                callbacks: {
                    label: function(context) {
                        const value = context.raw || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                        return context.label + ': ' + value + ' (' + percentage + '%)';
                    },
                }
            },
            legend: {
                position: 'right',
                labels: {
                    boxWidth: 20,
                    padding: 15,
                    font: {
                        size: 12,
                    },
                },
            }
        },
        scales: {
            x: { display: false },
            y: { display: false }
        },
        animation: {
            onComplete: function() {
                const chart = this;
                const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                const ctx = chart.ctx;

                // Calculate center using chartArea for more accurate positioning
                const centerX = (chart.chartArea.left + chart.chartArea.right) / 2;
                const centerY = (chart.chartArea.top + chart.chartArea.bottom) / 2;

                ctx.save();
                ctx.font = 'bold 24px sans-serif';

                // Detect dark mode using Tailwind/Filament class on <html>
                const isDark = document.documentElement.classList.contains('dark');
                ctx.fillStyle = isDark ? '#fff' : '#111';

                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(total.toString(), centerX, centerY);
                ctx.restore();
            }
        }
    }
    JS;

        return RawJs::make($js);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
