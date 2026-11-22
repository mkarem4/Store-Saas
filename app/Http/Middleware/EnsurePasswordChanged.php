<?php

namespace App\Http\Middleware;

use App\Http\Controllers\ImpersonationController;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accounts created with a temporary password must set their own before using
 * the panel (§3).
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password || $request->session()->has(ImpersonationController::SESSION_KEY)) {
            return $next($request);
        }

        $panel = Filament::getCurrentPanel();

        if ($panel === null || ! $panel->hasProfile()) {
            return $next($request);
        }

        $profileUrl = $panel->getProfileUrl();

        if ($profileUrl === null || $request->is('livewire*') || str_starts_with($request->url(), $profileUrl) || $request->routeIs('*.auth.logout')) {
            return $next($request);
        }

        return redirect()->to($profileUrl)->with('status', __('Please choose a new password to continue.'));
    }
}
