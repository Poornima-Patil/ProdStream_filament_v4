<?php

namespace App\Providers;

use App\Filament\Admin\Widgets\AdvancedWorkOrderGantt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Livewire::component('app.filament.admin.widgets.advanced-work-order-gantt', AdvancedWorkOrderGantt::class);

        // Force PHP timezone
        date_default_timezone_set(config('app.timezone', 'Asia/Kolkata'));

        // Set MySQL timezone on connection
        if (config('database.default') === 'mysql') {
            DB::statement("SET time_zone = '+05:30'");
        }
    }
}
