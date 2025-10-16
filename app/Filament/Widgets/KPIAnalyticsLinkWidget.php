<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class KPIAnalyticsLinkWidget extends Widget
{
    protected string $view = 'filament.widgets.kpi-analytics-link-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1; // Show at the top
}
