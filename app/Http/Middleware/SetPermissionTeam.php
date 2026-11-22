<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->hasUser() && auth()->user()->business_id !== null) {
            setPermissionsTeamId(auth()->user()->business_id);
        }

        return $next($request);
    }
}
