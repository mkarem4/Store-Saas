<?php

namespace App\Filament\Admin\Resources\PayoutRequests\Pages;

use App\Enums\PayoutStatus;
use App\Filament\Admin\Resources\PayoutRequests\PayoutRequestResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPayoutRequests extends ListRecords
{
    protected static string $resource = PayoutRequestResource::class;

    public function getTabs(): array
    {
        return [
            'open' => Tab::make(__('Open'))->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [PayoutStatus::Requested, PayoutStatus::Approved])),
            'paid' => Tab::make(__('Paid'))->modifyQueryUsing(fn (Builder $query) => $query->where('status', PayoutStatus::Paid)),
            'rejected' => Tab::make(__('Rejected'))->modifyQueryUsing(fn (Builder $query) => $query->where('status', PayoutStatus::Rejected)),
            'all' => Tab::make(__('All')),
        ];
    }
}
