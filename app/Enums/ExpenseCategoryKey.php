<?php

namespace App\Enums;

/** Built-in expense categories; custom category keys remain supported. */
enum ExpenseCategoryKey: string
{
    case Rent = 'rent';
    case Salaries = 'salaries';
    case Electricity = 'electricity';
    case Water = 'water';
    case Maintenance = 'maintenance';
    case Marketing = 'marketing';
    case Other = 'other';
    case PaymentFees = 'payment_fees';
    case PlatformCommission = 'platform_commission';
}
