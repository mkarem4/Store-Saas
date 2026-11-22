<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetPermissionTeam;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

trait ConfiguresPanel
{
    protected function base(Panel $panel, string $id): Panel
    {
        $namespace = ucfirst($id);

        return $panel
            ->id($id)
            ->path($id)
            ->login()
            ->profile(isSimple: false)
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->discoverResources(in: app_path("Filament/{$namespace}/Resources"), for: "App\\Filament\\{$namespace}\\Resources")
            ->discoverPages(in: app_path("Filament/{$namespace}/Pages"), for: "App\\Filament\\{$namespace}\\Pages")
            ->discoverWidgets(in: app_path("Filament/{$namespace}/Widgets"), for: "App\\Filament\\{$namespace}\\Widgets")
            ->renderHook(PanelsRenderHook::BODY_START, fn (): string => view('filament.partials.impersonation-banner')->render())
            ->renderHook(PanelsRenderHook::USER_MENU_BEFORE, fn (): string => view('filament.partials.locale-switcher')->render())
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
                SetPermissionTeam::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordChanged::class,
            ]);
    }
}
