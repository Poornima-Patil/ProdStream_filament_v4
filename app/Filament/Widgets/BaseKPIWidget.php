<?php

namespace App\Filament\Widgets;

use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Widgets\Widget;

abstract class BaseKPIWidget extends Widget implements HasForms
{
    use InteractsWithForms;

    // Mode toggle
    public string $mode = 'dashboard'; // or 'analytics'

    // Analytics filters (state variables)
    public string $timePeriod = 'today';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public bool $enableComparison = false;

    public string $comparisonType = 'previous_period';

    // Widget title
    protected string $title = 'KPI Widget';

    /**
     * Switch between dashboard and analytics mode
     */
    public function setMode(string $mode): void
    {
        $this->mode = $mode;
        $this->dispatch('modeChanged', mode: $mode);
    }

    /**
     * Get data based on current mode
     */
    public function getKPIData(): array
    {
        if ($this->mode === 'dashboard') {
            return $this->getDashboardData();
        }

        return $this->getAnalyticsData();
    }

    /**
     * Get widget title
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Child classes must implement these methods
     */
    abstract protected function getDashboardData(): array;

    abstract protected function getAnalyticsData(): array;

    /**
     * Shared form schema for analytics filters
     */
    protected function getFormSchema(): array
    {
        if ($this->mode === 'analytics') {
            return [
                Forms\Components\Select::make('timePeriod')
                    ->label('Time Period')
                    ->options([
                        'today' => 'Today',
                        'yesterday' => 'Yesterday',
                        'this_week' => 'This Week',
                        'last_week' => 'Last Week',
                        'this_month' => 'This Month',
                        'last_month' => 'Last Month',
                        '7d' => 'Last 7 Days',
                        '14d' => 'Last 14 Days',
                        '30d' => 'Last 30 Days',
                        '60d' => 'Last 60 Days',
                        '90d' => 'Last 90 Days',
                        'this_quarter' => 'This Quarter',
                        'this_year' => 'This Year',
                        'custom' => 'Custom Date Range',
                    ])
                    ->default('today')
                    ->live()
                    ->reactive(),

                Forms\Components\DatePicker::make('dateFrom')
                    ->label('From Date')
                    ->visible(fn ($get) => $get('timePeriod') === 'custom')
                    ->maxDate(now()),

                Forms\Components\DatePicker::make('dateTo')
                    ->label('To Date')
                    ->visible(fn ($get) => $get('timePeriod') === 'custom')
                    ->maxDate(now()),

                Forms\Components\Toggle::make('enableComparison')
                    ->label('Compare with previous period')
                    ->default(false)
                    ->live()
                    ->reactive(),

                Forms\Components\Select::make('comparisonType')
                    ->label('Comparison Type')
                    ->options([
                        'previous_period' => 'Previous Period (same duration)',
                        'previous_week' => 'Previous Week',
                        'previous_month' => 'Previous Month',
                        'previous_quarter' => 'Previous Quarter',
                        'previous_year' => 'Same Period Last Year',
                    ])
                    ->visible(fn ($get) => $get('enableComparison'))
                    ->default('previous_period')
                    ->live(),
            ];
        }

        return [];
    }
}
