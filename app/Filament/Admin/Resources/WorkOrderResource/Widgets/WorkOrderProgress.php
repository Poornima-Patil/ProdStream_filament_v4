<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Widgets;

use Filament\Widgets\Widget;

class WorkOrderProgress extends Widget
{
    protected static string $view = 'filament.admin.resources.work-order-resource.widgets.work-order-progress';

    public ?\App\Models\WorkOrder $record = null;

    public static function canView(): bool
    {
        return true;
    }
}
