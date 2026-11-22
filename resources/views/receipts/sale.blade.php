@php
    $currency = $business->currency;
    $money = fn (int $cents) => \App\Support\Money::format($cents, $currency, (int) $business->currency_decimals);
    $rtl = app()->getLocale() === 'ar';
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $sale->number }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { font-family: {{ isset($pdf) ? "'DejaVu Sans'" : "ui-monospace, 'Courier New', monospace" }}; font-size: 12px; width: 80mm; margin: 0 auto; padding: 4mm; color: #000; background: #fff; }
        h1 { font-size: 15px; text-align: center; margin: 0 0 2px; }
        .center { text-align: center; }
        .muted { color: #444; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 2px 0; vertical-align: top; }
        .num { text-align: {{ $rtl ? 'left' : 'right' }}; white-space: nowrap; }
        .rule { border-top: 1px dashed #000; margin: 6px 0; }
        .total td { font-weight: bold; font-size: 14px; }
        .mod { font-size: 11px; padding-inline-start: 8px; }
        @media print { .no-print { display: none; } }
        .no-print { text-align: center; margin-top: 10px; }
        .no-print button { padding: 6px 14px; }
    </style>
</head>
<body>
    <h1>{{ $business->translatedName() }}</h1>
    <div class="center muted">
        {{ $business->address_line }} {{ $business->city }}<br>
        {{ $business->phone }}
    </div>
    <div class="rule"></div>
    <table class="muted">
        <tr><td>{{ __('Receipt') }}</td><td class="num">{{ $sale->number }}</td></tr>
        <tr><td>{{ __('Date') }}</td><td class="num">{{ $sale->sold_at->copy()->setTimezone($business->timezone)->format('Y-m-d H:i') }}</td></tr>
        @if ($sale->employee)<tr><td>{{ __('Cashier') }}</td><td class="num">{{ $sale->employee->name }}</td></tr>@endif
        @if ($sale->client)<tr><td>{{ __('Client') }}</td><td class="num">{{ $sale->client->name }}</td></tr>@endif
    </table>
    <div class="rule"></div>
    <table>
        @foreach ($sale->items as $item)
            <tr>
                <td>{{ $item->name }}<br><span class="muted">{{ rtrim(rtrim(number_format((float) $item->qty, 3), '0'), '.') }} {{ $item->unit->value === 'kg' ? 'kg' : '×' }} {{ $money((int) $item->unit_price_cents) }}</span></td>
                <td class="num">{{ $money((int) $item->total_cents) }}</td>
            </tr>
            @foreach ($item->modifiers as $modifier)
                <tr><td class="mod" colspan="2">+ {{ $modifier->name }} @if($modifier->price_delta_cents) ({{ $money((int) $modifier->price_delta_cents) }}) @endif</td></tr>
            @endforeach
            @if ($item->discount_cents > 0)
                <tr><td class="mod">{{ __('Discount') }}</td><td class="num">−{{ $money((int) $item->discount_cents) }}</td></tr>
            @endif
        @endforeach
    </table>
    <div class="rule"></div>
    <table>
        <tr><td>{{ __('Subtotal') }}</td><td class="num">{{ $money((int) $sale->subtotal_cents) }}</td></tr>
        @if ($sale->discount_cents > 0)<tr><td>{{ __('Discount') }}</td><td class="num">−{{ $money((int) $sale->discount_cents) }}</td></tr>@endif
        @if ($sale->tax_cents > 0)<tr><td>{{ __('Tax (:rate%)', ['rate' => rtrim(rtrim(number_format((float) $sale->tax_rate, 3), '0'), '.')]) }}</td><td class="num">{{ $money((int) $sale->tax_cents) }}</td></tr>@endif
        <tr class="total"><td>{{ __('Total') }}</td><td class="num">{{ $money((int) $sale->total_cents) }}</td></tr>
        @foreach ($sale->payments as $payment)
            <tr class="muted"><td>{{ $payment->method->label() }}</td><td class="num">{{ $money((int) $payment->amount_cents) }}</td></tr>
            @if (($payment->meta['tendered_cents'] ?? null) !== null)
                <tr class="muted"><td>{{ __('Cash given') }}</td><td class="num">{{ $money((int) $payment->meta['tendered_cents']) }}</td></tr>
                <tr class="muted"><td>{{ __('Change') }}</td><td class="num">{{ $money((int) $payment->meta['tendered_cents'] - (int) $payment->amount_cents) }}</td></tr>
            @endif
        @endforeach
        @foreach ($sale->refunds as $refund)
            <tr class="muted"><td>{{ __('Refund') }} {{ $refund->refunded_at->format('Y-m-d') }}</td><td class="num">−{{ $money((int) $refund->amount_cents) }}</td></tr>
        @endforeach
    </table>
    <div class="rule"></div>
    <div class="center muted">{{ __('Thank you!') }}</div>
    @unless (isset($pdf))
        <div class="no-print"><button onclick="window.print()">{{ __('Print') }}</button></div>
        @if ($autoPrint)<script>window.addEventListener('load', () => window.print());</script>@endif
    @endunless
</body>
</html>
