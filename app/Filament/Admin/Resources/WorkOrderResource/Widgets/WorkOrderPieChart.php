<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Widgets;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;

class WorkOrderPieChart extends ChartWidget
{
    use InteractsWithPageTable;

    protected static ?string $heading = 'Work Order Status Distribution';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $statuses = ['Assigned', 'Start', 'Hold', 'Completed', 'Closed'];
        $counts = [];
        $statusColors = config('work_order_status');

        foreach ($statuses as $status) {
            $counts[] = $this->getPageTableQuery()
                ->clone()
                ->where('status', $status)
                ->count();
        }

        return [
            'labels' => $statuses,
            'datasets' => [
                [
                    'label' => 'Work Orders',
                    'data' => $counts,
                    'backgroundColor' => [
                        $statusColors['assigned'],
                        $statusColors['start'],
                        $statusColors['hold'],
                        $statusColors['completed'],
                        $statusColors['closed'],
                    ],
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
                        let label = context.dataset.label || '';
                        const value = context.raw || 0;
                        return ' ' + label + ': ' + value.toLocaleString('en-IN');
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
                ctx.fillStyle = '#111';
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

    protected function getTablePage(): string
    {
        return \App\Filament\Admin\Resources\WorkOrderResource\Pages\ListWorkOrders::class;
    }
}
