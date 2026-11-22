<?php

namespace App\Providers;

use App\Contracts\DistanceCalculator;
use App\Contracts\GoldPriceProvider;
use App\Contracts\PaymentGateway;
use App\Contracts\PushNotifier;
use App\Models\User;
use App\Services\Geo\GoogleDistanceCalculator;
use App\Services\Geo\HaversineDistanceCalculator;
use App\Services\Gold\GoldApiProvider;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\StripeGateway;
use App\Services\Push\FcmPushNotifier;
use App\Services\Push\LogPushNotifier;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, function (): PaymentGateway {
            return (string) config('services.stripe.secret') !== ''
                ? new StripeGateway
                : new FakeGateway;
        });

        $this->app->bind(DistanceCalculator::class, function (): DistanceCalculator {
            $key = (string) config('services.google_maps.key');

            return $key !== ''
                ? new GoogleDistanceCalculator($key, new HaversineDistanceCalculator)
                : new HaversineDistanceCalculator;
        });

        $this->app->bind(PushNotifier::class, function (): PushNotifier {
            $credentials = (string) config('services.fcm.credentials');

            return $credentials !== ''
                ? new FcmPushNotifier($credentials, config('services.fcm.project_id'))
                : new LogPushNotifier;
        });

        $this->app->bind(GoldPriceProvider::class, fn (): GoldPriceProvider => new GoldApiProvider(config('services.gold_api.key')));
    }

    public function boot(): void
    {
        Gate::before(function ($user): ?bool {
            return $user instanceof User && $user->isOwner() ? true : null;
        });

        Gate::define('manage-platform', fn (User $user): bool => $user->isOwner());

        Event::listen(Login::class, function ($event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });

        // Money and counts read the same in Arabic and English: western digits, "$1,234.50".
        Table::configureUsing(fn (Table $table) => $table->defaultNumberLocale('en_US'));
        Schema::configureUsing(fn (Schema $schema) => $schema->defaultNumberLocale('en_US'));

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
