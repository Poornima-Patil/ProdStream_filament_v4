<?php

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class KPIDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.admin.pages.kpi-dashboard';

    protected static ?string $navigationLabel = 'KPI Dashboard';

    protected static ?string $title = 'Production KPI Dashboard';

    protected static ?int $navigationSort = -10; // Shows at top of navigation

    protected static ?string $navigationGroup = 'Analytics';

    // Make this the default landing page for factory admins
    public static function shouldRegisterNavigation(): bool
    {
        return Auth::check();
    }
}
