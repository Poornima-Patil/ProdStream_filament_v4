<?php

namespace App\Providers\Filament;

use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use App\Filament\Admin\Pages\WorkOrderWidgets;
use App\Filament\Pages\Tenancy\EditFactoryProfile;
use App\Filament\Pages\Tenancy\RegisterFactory;
use App\Models\Factory;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->darkMode() // Enable dark mode toggle
            ->globalSearch(false) // Disable global search that can cause blue patches
            ->breadcrumbs(false) // Disable breadcrumbs that can cause blue patches
            ->colors([
                'primary' => '#106EBE',
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\\Filament\\Admin\\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\\Filament\\Admin\\Pages')
            ->pages([
                Dashboard::class,
                //WorkOrderWidgets::class, // Add the Work Order Widgets page here
            ])
            // ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\\Filament\\Admin\\Widgets')
            ->widgets([
                AccountWidget::class,
                // Widgets\FilamentInfoWidget::class, // Removed to eliminate blue patch on refresh
                //\App\Filament\Admin\Widgets\WorkOrderWidgetsCard::class, // Keep only the widgets you want globally
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->tenant(Factory::class, ownershipRelationship: 'owner')
            ->tenantRegistration(RegisterFactory::class)
            ->tenantProfile(EditFactoryProfile::class);
    }
}
