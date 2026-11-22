{{-- Shared by the reports page and its PDF. Expects the reportData() array. --}}
@php
    $currency = $business->currency;
    $m = fn ($cents) => \App\Support\Money::format((int) $cents, $currency);
    $s = $summary;
@endphp
<div class="yr-grid">
    @foreach ([
        [__('Revenue'), $s['revenue_cents'], __('Excludes tax; net of returns')],
        [__('Cost of goods'), $s['cogs_cents'], null],
        [__('Gross profit'), $s['gross_profit_cents'], null],
        [__('Expenses'), $s['expenses_cents'], __('Rent, payroll, fees, other')],
        [__('Net profit'), $s['net_profit_cents'], null],
        [__('Tax collected'), $s['tax_cents'], __('Kept apart from revenue')],
    ] as [$label, $value, $hint])
        <div class="yr-card">
            <div class="yr-label">{{ $label }}</div>
            <div class="yr-value {{ $label === __('Net profit') ? ($value >= 0 ? 'yr-pos' : 'yr-neg') : '' }}">{{ $m($value) }}</div>
            @if ($hint)<div class="yr-hint">{{ $hint }}</div>@endif
        </div>
    @endforeach
</div>

<div class="yr-cols">
    <div class="yr-box">
        <h3>{{ __('Sales by channel') }}</h3>
        <table class="yr-table">
            @forelse ($s['by_channel'] as $channel => $amount)
                <tr><td>{{ \App\Enums\SaleChannel::from($channel)->label() }}</td><td class="yr-num">{{ $m($amount) }}</td></tr>
            @empty <tr><td colspan="2" class="yr-empty">{{ __('No sales') }}</td></tr> @endforelse
            <tr><td>{{ __('Returns') }}</td><td class="yr-num">−{{ $m($s['refunds_cents']) }}</td></tr>
            <tr><td>{{ __('Discounts given') }}</td><td class="yr-num">{{ $m($s['discounts_cents']) }}</td></tr>
            <tr><td>{{ __('Sales count') }} / {{ __('average') }}</td><td class="yr-num">{{ $s['sales_count'] }} / {{ $m($s['average_sale_cents']) }}</td></tr>
        </table>
    </div>
    <div class="yr-box">
        <h3>{{ __('Payments') }}</h3>
        <table class="yr-table">
            @forelse ($s['payment_breakdown'] as $row)
                <tr><td>{{ \App\Enums\PaymentMethod::from($row['method'])->label() }}</td><td class="yr-num">{{ $m($row['total_cents']) }}</td></tr>
            @empty <tr><td colspan="2" class="yr-empty">—</td></tr> @endforelse
        </table>
    </div>
    <div class="yr-box">
        <h3>{{ __('Expenses by category') }}</h3>
        <table class="yr-table">
            @forelse ($s['expenses_by_category'] as $name => $amount)
                <tr><td>{{ $name }}</td><td class="yr-num">{{ $m($amount) }}</td></tr>
            @empty <tr><td colspan="2" class="yr-empty">—</td></tr> @endforelse
        </table>
    </div>
    <div class="yr-box">
        <h3>{{ __('Top products') }}</h3>
        <table class="yr-table">
            @forelse ($s['top_products'] as $row)
                <tr><td>{{ $row['name'] }}</td><td class="yr-num">{{ rtrim(rtrim(number_format($row['qty'], 3), '0'), '.') }}</td><td class="yr-num">{{ $m($row['total_cents']) }}</td></tr>
            @empty <tr><td colspan="3" class="yr-empty">—</td></tr> @endforelse
        </table>
    </div>
    <div class="yr-box">
        <h3>{{ __('Sales by employee') }}</h3>
        <table class="yr-table">
            @forelse ($byEmployee as $row)
                <tr><td>{{ $row['employee'] }}</td><td class="yr-num">{{ $row['sales_count'] }}</td><td class="yr-num">{{ $m($row['sales_cents']) }}</td></tr>
            @empty <tr><td colspan="3" class="yr-empty">—</td></tr> @endforelse
        </table>
        @if ($s['commissions']->isNotEmpty())
            <h3>{{ __('Commissions') }}</h3>
            <table class="yr-table">
                @foreach ($s['commissions'] as $row)
                    <tr><td>{{ $row['employee'] }}</td><td class="yr-num">{{ $m($row['sales_cents']) }}</td><td class="yr-num">{{ $m($row['commission_cents']) }}</td></tr>
                @endforeach
            </table>
        @endif
    </div>
    <div class="yr-box">
        <h3>{{ __('Hours worked') }}</h3>
        <table class="yr-table">
            @forelse ($hours as $row)
                <tr><td>{{ $row['employee'] }}</td><td class="yr-num">{{ number_format($row['minutes'] / 60, 2) }} h</td><td class="yr-num">{{ $row['auto_closed'] ? __(':count to review', ['count' => $row['auto_closed']]) : '' }}</td></tr>
            @empty <tr><td colspan="3" class="yr-empty">—</td></tr> @endforelse
        </table>
    </div>
    <div class="yr-box">
        <h3>{{ __('Tax collected') }}</h3>
        <table class="yr-table">
            @forelse ($tax as $row)
                <tr><td>{{ $row['region'] }} · {{ $row['channel'] }} · {{ rtrim(rtrim(number_format($row['rate'], 3), '0'), '.') }}%</td><td class="yr-num">{{ $m($row['taxable_cents']) }}</td><td class="yr-num">{{ $m($row['tax_cents']) }}</td></tr>
            @empty <tr><td colspan="3" class="yr-empty">—</td></tr> @endforelse
        </table>
    </div>
    @if ($vehicles->isNotEmpty())
        <div class="yr-box">
            <h3>{{ __('Profit per car') }}</h3>
            <table class="yr-table">
                @foreach ($vehicles as $row)
                    <tr><td>{{ $row['vehicle'] }}<div class="yr-hint">{{ $row['vin'] }} · {{ $row['sold_by'] }}</div></td><td class="yr-num">{{ $m($row['sold_cents']) }}</td><td class="yr-num">{{ $m($row['profit_cents']) }}</td></tr>
                @endforeach
            </table>
        </div>
    @endif
    @if ($lowStock->isNotEmpty() || $s['inventory_value_cents'] > 0)
        <div class="yr-box">
            <h3>{{ __('Stock') }}</h3>
            <table class="yr-table">
                <tr><td>{{ __('Inventory value (at cost)') }}</td><td class="yr-num">{{ $m($s['inventory_value_cents']) }}</td></tr>
                @foreach ($lowStock->take(15) as $variant)
                    <tr><td>{{ $variant->displayName() }}</td><td class="yr-num yr-neg">{{ rtrim(rtrim(number_format((float) $variant->stock_qty, 3), '0'), '.') }}</td></tr>
                @endforeach
            </table>
        </div>
    @endif
</div>
