<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Widgets;

use App\Models\WorkOrder;
use App\Models\WorkOrderLog;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Log;

class WorkOrderQtyTrendChart extends ChartWidget
{
    public ?WorkOrder $workOrder = null;

    protected ?string $heading = null;
    
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        if (! $this->workOrder) {
            Log::info('No WorkOrder passed to widget.');

            return ['datasets' => [], 'labels' => []];
        }

        Log::info('WorkOrderQtyTrendChart: Record ID '.$this->workOrder->id);

        $logs = WorkOrderLog::where('work_order_id', $this->workOrder->id)
            ->whereIn('status', ['Hold', 'Completed', 'Closed'])
            ->orderBy('changed_at')
            ->get();

        Log::info('Logs:', $logs->toArray());

        $okQtys = $logs->pluck('ok_qtys')->toArray();
        $scrappedQtys = $logs->pluck('scrapped_qtys')->toArray();
        $labels = $logs->map(fn ($log) => optional($log->changed_at)->format('d/m H:i'))->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'OK QTYs',
                    'data' => $okQtys,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.6)',
                ],
                [
                    'label' => 'Scrapped QTYs',
                    'data' => $scrappedQtys,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.6)',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'labels' => [
                        // Use CSS variables so Chart.js picks up the correct color in dark mode
                        'color' => 'rgb(var(--tw-prose-body))',
                    ],
                ],
            ],
            'scales' => [
                'x' => [
                    'ticks' => [
                        'color' => 'rgb(var(--tw-prose-body))',
                    ],
                    'grid' => [
                        'color' => 'rgba(100,116,139,0.2)', // slate-500/20
                    ],
                ],
                'y' => [
                    'ticks' => [
                        'color' => 'rgb(var(--tw-prose-body))',
                    ],
                    'grid' => [
                        'color' => 'rgba(100,116,139,0.2)',
                    ],
                ],
            ],
        ];
    }
}
