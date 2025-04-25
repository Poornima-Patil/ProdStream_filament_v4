<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use App\Filament\Admin\Widgets\AdvancedWorkOrderGantt;

class WorkOrderWidgets extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.admin.pages.work-order-widgets';

    protected function getWidgets(): array
    {
        return [
            AdvancedWorkOrderGantt::class, // Add the Advanced Gantt Chart widget here
        ];
    }
}
