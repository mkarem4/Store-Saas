<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel routes outside the {tenant} prefix (the profile page) still render the
 * tenant sidebar, whose links need a tenant. Use the signed-in user's own store.
 */
class UseOwnTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Filament::getCurrentPanel();
        $user = $request->user();

        if ($panel?->hasTenancy() && Filament::getTenant() === null && $user?->business_id !== null) {
            $business = Business::query()->find($user->business_id);

            if ($business !== null && $user->canAccessTenant($business)) {
                Filament::setTenant($business, isQuiet: true);
                URL::defaults(['tenant' => $business->slug]);
            }
        }

        return $next($request);
    }
}
