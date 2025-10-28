<?php

namespace App\Filament\Admin\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class Settings extends Page
{
    protected string $view = 'filament.admin.pages.settings';

    protected static ?string $navigationLabel = 'Settings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Admin Operations';

    protected static ?int $navigationSort = 99;

    public ?string $kpiDateType = null;

    public function mount(): void
    {
        // Load current session value
        $this->kpiDateType = session('kpi_date_type', 'created_at');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')
                ->label('Reset to Defaults')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action('resetToDefaults')
                ->requiresConfirmation(),
        ];
    }

    public function save(): void
    {
        // Save to session
        session(['kpi_date_type' => $this->kpiDateType]);

        // Clear KPI cache since filter type changed
        Cache::flush();

        // Notify user
        Notification::make()
            ->title('Settings saved successfully')
            ->success()
            ->send();

        // Broadcast change to other components
        $this->dispatch('kpi-date-type-changed', $this->kpiDateType);
    }

    public function resetToDefaults(): void
    {
        $this->kpiDateType = 'created_at';
        session(['kpi_date_type' => 'created_at']);

        Notification::make()
            ->title('Settings reset to defaults')
            ->success()
            ->send();
    }
}
