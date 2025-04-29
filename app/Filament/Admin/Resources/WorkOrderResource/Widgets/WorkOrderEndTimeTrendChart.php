<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class WorkOrderEndTimeTrendChart extends ChartWidget
{
    protected static ?string $heading = 'WO Completion vs End Time';

    protected static ?string $maxHeight = '350px';

    protected static ?string $maxWidth = 'full';

    use InteractsWithPageTable;

    protected function getData(): array
    {
        $workOrders = $this->getPageTableQuery()->clone()->get();

        $labels = [];
        $early = [];
        $onTime = [];
        $late = [];
        $woIds = [];

        foreach ($workOrders as $wo) {
            $labels[] = 'WO#'.$wo->unique_id;
            $woIds[] = $wo->id;

            $plannedEnd = $wo->end_time ? Carbon::parse($wo->end_time) : null;
            $actualCompletion = optional($wo->workOrderLogs->first())->changed_at;

            $early[] = 0;
            $onTime[] = 0;
            $late[] = 0;

            if (! $plannedEnd) {
                continue;
            }

            $now = now();
            $status = strtolower($wo->status);

            if (in_array($status, ['closed', 'completed']) && $actualCompletion) {
                if ($actualCompletion->lt($plannedEnd)) {
                    $early[array_key_last($early)] = 1;
                } elseif ($actualCompletion->eq($plannedEnd)) {
                    $onTime[array_key_last($onTime)] = 1;
                } else {
                    $late[array_key_last($late)] = 1;
                }
            } elseif (in_array($status, ['assigned', 'start', 'hold']) && $plannedEnd->lt($now)) {
                $late[array_key_last($late)] = 1;
            } else {
                $onTime[array_key_last($onTime)] = 1;
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Completed Early',
                    'data' => $early,
                    'backgroundColor' => 'rgba(59,130,246,0.7)',
                ],
                [
                    'label' => 'On Time',
                    'data' => $onTime,
                    'backgroundColor' => 'rgba(34,197,94,0.7)',
                ],
                [
                    'label' => 'Delayed',
                    'data' => $late,
                    'backgroundColor' => 'rgba(239,68,68,0.7)',
                ],
            ],
            'labels' => $labels,
            'options' => [
                'onClick' => new HtmlString('
        function(event, elements) {
            if (elements.length > 0) {
                const index = elements[0].index;
                const woIds = '.json_encode($woIds).";
                const woId = woIds[index];
                window.location.href = '/admin/".auth()->user()->factory_id."/work-orders/' + woId;
            }
        }
    "),
                'responsive' => true,
                'maintainAspectRatio' => false,
                'scales' => [
                    'x' => [
                        'display' => false,
                        'grid' => ['display' => false],
                        'ticks' => ['display' => false],
                    ],
                    'y' => [
                        'display' => false,
                        'grid' => ['display' => false],
                        'ticks' => ['display' => false],
                    ],
                ],
                'plugins' => [
                    'tooltip' => [
                        'callbacks' => [
                            'title' => new HtmlString('function(tooltipItems) {
                    const uniqueIds = '.json_encode($labels).';
                    const index = tooltipItems[0].dataIndex;
                    return uniqueIds[index];
                }'),
                            'label' => new HtmlString("function(context) {
                    return context.dataset.label + ': ' + context.raw;
                }"),
                        ],
                    ],
                    'legend' => [
                        'display' => true,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getTablePage(): string
    {
        return \App\Filament\Admin\Resources\WorkOrderResource\Pages\ListWorkOrders::class;
    }

    public function getColumnSpan(): int|string
    {
        return 'full'; // or 2, depending on layout system
    }
}
