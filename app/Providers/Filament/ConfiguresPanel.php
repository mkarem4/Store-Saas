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
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

trait ConfiguresPanel
{
    /**
     * Receipt-ink blue with #1E40AF (Tailwind blue-800) as the 600 shade
     * Filament uses for buttons and links, so the accent stays deep.
     *
     * @return array<int, string>
     */
    protected static function inkPalette(): array
    {
        $blue = Color::Blue;

        return [
            50 => $blue[50], 100 => $blue[100], 200 => $blue[200], 300 => $blue[300],
            400 => $blue[500], 500 => $blue[700], 600 => $blue[800], 700 => $blue[900],
            800 => $blue[950], 900 => $blue[950], 950 => $blue[950],
        ];
    }

    /**
     * Cleared-ledger teal for the platform console, with #0F766E (teal-700)
     * as the 600 shade so white button text keeps 5.4:1 contrast.
     *
     * @return array<int, string>
     */
    protected static function tealPalette(): array
    {
        $teal = Color::Teal;

        return [
            50 => $teal[50], 100 => $teal[100], 200 => $teal[200], 300 => $teal[300],
            400 => $teal[400], 500 => $teal[600], 600 => $teal[700], 700 => $teal[800],
            800 => $teal[900], 900 => $teal[950], 950 => $teal[950],
        ];
    }

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
            ->viteTheme('resources/css/filament/yulio/theme.css')
            ->font('IBM Plex Sans Arabic')
            ->colors([
                'primary' => self::inkPalette(),
                'gray' => Color::Stone,
                'success' => Color::Green,
                'danger' => Color::Red,
                'warning' => Color::Amber,
                'info' => Color::Sky,
            ])
            ->favicon(asset('favicon.ico'))
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
