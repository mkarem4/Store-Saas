<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Platform owner signs in as a store owner (M1.6). Every start/stop is in the
 * audit log, and a banner in the store panel offers the way back.
 */
class ImpersonationController extends Controller
{
    public const SESSION_KEY = 'impersonator_id';

    public function start(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        abort_unless($admin instanceof User && $admin->isOwner(), 403);
        abort_unless($user->role === UserRole::BusinessOwner && $user->business?->isActive() && $user->is_active, 403);

        activity('impersonation')->performedOn($user)->causedBy($admin)
            ->withProperties(['business_id' => $user->business_id, 'ip' => $request->ip()])
            ->log('impersonation.started');

        $this->switchTo($request, $user);
        $request->session()->put(self::SESSION_KEY, $admin->id);

        return redirect()->to(url('/business/'.$user->business->slug));
    }

    public function leave(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull(self::SESSION_KEY);
        $admin = $adminId !== null ? User::query()->find($adminId) : null;

        if (! $admin instanceof User || ! $admin->isOwner()) {
            Auth::guard('web')->logout();

            return redirect()->to(url('/admin/login'));
        }

        activity('impersonation')->performedOn($request->user() ?? $admin)->causedBy($admin)->log('impersonation.ended');

        $this->switchTo($request, $admin);

        return redirect()->to(url('/admin/businesses'));
    }

    private function switchTo(Request $request, User $user): void
    {
        Auth::guard('web')->login($user);

        // AuthenticateSession compares this hash on every request.
        $request->session()->put('password_hash_web', $user->getAuthPassword());
        $request->session()->regenerate();
    }
}
