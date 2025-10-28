<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Widgets;

use App\Models\WorkOrder;
use Filament\Widgets\Widget;

class WorkOrderProgress extends Widget
{
    protected string $view = 'filament.admin.resources.work-order-resource.widgets.work-order-progress';

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    public ?WorkOrder $record = null;

    public static function canView(): bool
    {
        return true;
    }
}
