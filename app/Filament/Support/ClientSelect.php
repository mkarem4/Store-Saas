<?php

namespace App\Filament\Support;

use App\Filament\Business\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Forms\Components\Select;

final class ClientSelect
{
    public static function make(string $name = 'client_id', bool $required = true): Select
    {
        return Select::make($name)
            ->label(__('Client'))
            ->required($required)
            ->searchable()
            ->getSearchResultsUsing(fn (string $search): array => Client::query()
                ->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('id_number', $search))
                ->limit(30)->get()->mapWithKeys(fn (Client $client): array => [$client->id => "{$client->name} · {$client->phone}"])->all())
            ->getOptionLabelUsing(fn ($value): ?string => ($client = Client::query()->find($value)) ? "{$client->name} · {$client->phone}" : null)
            ->createOptionForm(ClientResource::fields())
            ->createOptionUsing(fn (array $data): int => Client::query()->create($data)->getKey());
    }
}
