<?php

namespace App\Exports;

use App\Support\Money;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Flat export of the reports page (M6.5).
 */
class ReportExport implements FromArray, ShouldAutoSize
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(private readonly array $data) {}

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $s = $this->data['summary'];
        $money = fn ($cents): float => Money::fromCents((int) $cents);

        $rows = [
            [__('Report'), $this->data['from']->toDateString(), $this->data['until']->toDateString()],
            [],
            [__('Revenue'), $money($s['revenue_cents'])],
            [__('Cost of goods'), $money($s['cogs_cents'])],
            [__('Gross profit'), $money($s['gross_profit_cents'])],
            [__('Expenses'), $money($s['expenses_cents'])],
            [__('Net profit'), $money($s['net_profit_cents'])],
            [__('Tax collected'), $money($s['tax_cents'])],
            [__('Returns'), $money($s['refunds_cents'])],
            [],
            [__('Expenses by category')],
        ];

        foreach ($s['expenses_by_category'] as $name => $amount) {
            $rows[] = [$name, $money($amount)];
        }

        $rows[] = [];
        $rows[] = [__('Top products'), __('Qty'), __('Total')];

        foreach ($s['top_products'] as $row) {
            $rows[] = [$row['name'], $row['qty'], $money($row['total_cents'])];
        }

        $rows[] = [];
        $rows[] = [__('Sales by employee'), __('Sales'), __('Total')];

        foreach ($this->data['byEmployee'] as $row) {
            $rows[] = [$row['employee'], $row['sales_count'], $money($row['sales_cents'])];
        }

        $rows[] = [];
        $rows[] = [__('Commissions'), __('Net sales'), __('Commission')];

        foreach ($s['commissions'] as $row) {
            $rows[] = [$row['employee'], $money($row['sales_cents']), $money($row['commission_cents'])];
        }

        $rows[] = [];
        $rows[] = [__('Tax collected'), __('Taxable'), __('Tax')];

        foreach ($this->data['tax'] as $row) {
            $rows[] = [$row['region'].' '.$row['channel'].' '.$row['rate'].'%', $money($row['taxable_cents']), $money($row['tax_cents'])];
        }

        return $rows;
    }
}
