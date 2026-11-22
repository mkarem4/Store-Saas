<?php

namespace App\Providers;

use App\Contracts\PaymentGateway;
use App\Models\User;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\StripeGateway;
use App\Support\Permission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function (): PaymentGateway {
            return (string) config('services.stripe.secret') !== ''
                ? new StripeGateway()
                : new FakeGateway();
        });
    }

    public function boot(): void
    {
        Gate::before(function ($user): ?bool {
            return $user instanceof User && $user->isOwner() ? true : null;
        });

        Gate::define('manage-platform', fn (User $user): bool => $user->isOwner());

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
