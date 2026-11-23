<?php

namespace App\Providers;

use App\Contracts\DistanceCalculator;
use App\Contracts\GoldPriceProvider;
use App\Contracts\PaymentGateway;
use App\Contracts\PushNotifier;
use App\Filament\Business\BusinessPanel;
use App\Filament\Support\TableEmptyState;
use App\Models\User;
use App\Services\Geo\GoogleDistanceCalculator;
use App\Services\Geo\HaversineDistanceCalculator;
use App\Services\Gold\GoldApiProvider;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\StripeGateway;
use App\Services\Push\FcmPushNotifier;
use App\Services\Push\LogPushNotifier;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\Entry;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Filters\SelectFilter;
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

        // Times in store panels show and are entered in the store's own timezone (the app runs in UTC).
        FilamentTimezone::set(fn (): ?string => BusinessPanel::business()?->timezone);

        // One look for every picker: Filament's own dropdowns and calendars, never the browser's.
        Select::configureUsing(fn (Select $select) => $select
            ->native(false)
            // A required field has nothing to clear back to.
            ->selectablePlaceholder(fn (Select $component): bool => ! $component->isRequired()));
        SelectFilter::configureUsing(fn (SelectFilter $filter) => $filter->native(false));
        DateTimePicker::configureUsing(fn (DateTimePicker $picker) => $picker->native(false));

        // Labels Filament derives from a field name ("status" -> "Status") go through the translator too.
        BaseFilter::configureUsing(fn (BaseFilter $filter) => $filter->translateLabel());
        Column::configureUsing(fn (Column $column) => $column->translateLabel());
        Field::configureUsing(fn (Field $field) => $field->translateLabel());
        Entry::configureUsing(fn (Entry $entry) => $entry->translateLabel());

        // A modal's submit button says what it does: it repeats the action's own label
        // ("Return items", "Reject"). Filament's Create/Save/Delete set their own in setUp().
        Action::configureUsing(fn (Action $action) => $action->modalSubmitActionLabel(fn (Action $action): string => (string) $action->getLabel()));

        // Viewing a record from a list opens a side drawer, so the list stays in place.
        ViewAction::configureUsing(fn (ViewAction $action) => $action->slideOver());

        // Lists keep their search, filters and sort when you come back from a record.
        Table::configureUsing(fn (Table $table) => TableEmptyState::apply($table)
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession());

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
