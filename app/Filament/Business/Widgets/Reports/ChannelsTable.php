<?php

namespace App\Filament\Business\Widgets\Reports;

use App\Enums\PaymentMethod;
use App\Enums\SaleChannel;
use App\Filament\Business\BusinessPanel;
use App\Services\ReportService;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Carbon;

class ChannelsTable extends ReportTable
{
    protected static ?int $sort = 5;

    protected function heading(): string
    {
        return __('Where the money came from');
    }

    protected function rows(ReportService $service, Carbon $from, Carbon $until): array
    {
        $summary = $service->summary(BusinessPanel::business(), $from, $until);
        $rows = [];

        foreach ($summary['by_channel'] as $channel => $amount) {
            $rows[] = ['label' => SaleChannel::from($channel)->label(), 'kind' => __('Channel'), 'amount_cents' => $amount];
        }

        foreach ($summary['payment_breakdown'] as $row) {
            $rows[] = ['label' => PaymentMethod::from($row['method'])->label(), 'kind' => __('Payment'), 'amount_cents' => $row['total_cents']];
        }

        if ($summary['refunds_cents'] > 0) {
            $rows[] = ['label' => __('Returns'), 'kind' => __('Deducted'), 'amount_cents' => -$summary['refunds_cents']];
        }

        return $rows;
    }

    protected function columns(string $currency): array
    {
        return [
            TextColumn::make('label')->label(__('Source')),
            TextColumn::make('kind')->label(__('Type'))->badge()->color('gray'),
            TextColumn::make('amount_cents')->label(__('Amount'))->money($currency, divideBy: 100)->alignEnd(),
        ];
    }
}
