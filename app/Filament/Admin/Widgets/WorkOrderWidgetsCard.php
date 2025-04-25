<?php

namespace App\Filament\Admin\Widgets;

use Filament\Widgets\StatsOverviewWidget\Card;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class WorkOrderWidgetsCard extends BaseWidget
{
    protected function getCards(): array
    {
        // Get the current tenant (assuming it's stored in the session or auth context)
        $tenant = auth()->user()->factory_id; // Replace with the correct way to get the tenant

        return [
            Card::make('Work Order Widgets', 'Explore Widgets')
                ->description('View Work Order-related widgets and graphs')
                ->descriptionIcon('heroicon-o-arrow-right')
                ->color('primary')
                ->url(route('filament.admin.pages.work-order-widgets', ['tenant' => $tenant])), // Pass the tenant parameter
        ];
    }
}