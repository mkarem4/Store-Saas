<?php

namespace App\Filament\Business\Resources\Withdrawals\Pages;

use App\Filament\Business\Resources\Withdrawals\WithdrawalResource;
use Filament\Resources\Pages\ListRecords;

class ListWithdrawals extends ListRecords
{
    protected static string $resource = WithdrawalResource::class;
}
