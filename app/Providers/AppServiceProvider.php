<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use App\Filament\Admin\Widgets\AdvancedWorkOrderGantt;

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
        //Livewire::component('app.filament.admin.widgets.advanced-work-order-gantt', AdvancedWorkOrderGantt::class);
    }
}
