@php
    $totals = $this->totals();
    $results = $this->results();
    $categories = $this->categories();
@endphp
<x-filament-panels::page>
    <style>
        .pos { display: grid; grid-template-columns: minmax(0, 1fr) 380px; gap: 16px; align-items: start; }
        @media (max-width: 1024px) { .pos { grid-template-columns: 1fr; } }
        .pos-panel { border: 1px solid rgba(127,127,127,.25); border-radius: 12px; padding: 12px; background: rgba(127,127,127,.03); }
        .pos-cats { display: flex; gap: 6px; flex-wrap: wrap; margin: 10px 0; }
        .pos-cat { border: 1px solid rgba(127,127,127,.3); border-radius: 999px; padding: 4px 12px; font-size: 13px; cursor: pointer; background: transparent; color: inherit; }
        .pos-cat.on { background: rgb(var(--primary-600, 5 150 105)); border-color: transparent; color: #fff; }
        .pos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; }
        .pos-item { text-align: start; border: 1px solid rgba(127,127,127,.25); border-radius: 10px; padding: 10px; cursor: pointer; background: transparent; color: inherit; min-height: 84px; display: flex; flex-direction: column; justify-content: space-between; }
        .pos-item:hover { border-color: rgb(var(--primary-500, 16 185 129)); }
        .pos-item .n { font-weight: 600; font-size: 13px; line-height: 1.25; }
        .pos-item .p { font-size: 13px; margin-top: 6px; }
        .pos-item .s { font-size: 11px; opacity: .6; }
        .pos-line { display: grid; grid-template-columns: 1fr auto; gap: 4px; padding: 8px 0; border-bottom: 1px solid rgba(127,127,127,.15); }
        .pos-line .m { font-size: 11px; opacity: .65; }
        .pos-qty { display: flex; gap: 4px; align-items: center; }
        .pos-qty button { width: 26px; height: 26px; border-radius: 6px; border: 1px solid rgba(127,127,127,.3); background: transparent; color: inherit; cursor: pointer; }
        .pos-tot { display: flex; justify-content: space-between; padding: 3px 0; font-size: 14px; }
        .pos-tot.big { font-size: 20px; font-weight: 700; padding-top: 8px; }
        .pos-empty { opacity: .5; text-align: center; padding: 32px 0; }
        .pos-link { font-size: 12px; text-decoration: underline; cursor: pointer; opacity: .8; background: none; border: 0; color: inherit; padding: 0; }
    </style>

    <div class="pos">
        <div class="pos-panel">
            <x-filament::input.wrapper prefix-icon="heroicon-o-qr-code">
                <x-filament::input type="text" wire:model.live.debounce.250ms="search" wire:keydown.enter.prevent="scan"
                    placeholder="{{ __('Scan a barcode or search by name / SKU') }}" autofocus autocomplete="off" />
            </x-filament::input.wrapper>

            @if ($categories->isNotEmpty())
                <div class="pos-cats">
                    <button type="button" class="pos-cat {{ $categoryId === null ? 'on' : '' }}" wire:click="$set('categoryId', null)">{{ __('All') }}</button>
                    @foreach ($categories as $category)
                        <button type="button" class="pos-cat {{ $categoryId === $category->id ? 'on' : '' }}" wire:click="$set('categoryId', {{ $category->id }})">{{ $category->translatedName() }}</button>
                    @endforeach
                </div>
            @endif

            <div class="pos-grid" wire:loading.class="opacity-50" wire:target="search,categoryId">
                @forelse ($results as $variant)
                    <button type="button" class="pos-item" wire:click="pick({{ $variant->id }})" wire:key="v-{{ $variant->id }}">
                        <span class="n">{{ $variant->displayName() }}</span>
                        <span>
                            <span class="p">{{ $this->money($variant->priceFor($wholesale && $this->canSellWholesale())) }}@if ($variant->unit->value === 'kg') / kg @endif</span>
                            @if ($variant->product->track_stock)
                                <span class="s"> · {{ rtrim(rtrim(number_format($variant->availableQty(), 3), '0'), '.') }}</span>
                            @endif
                        </span>
                    </button>
                @empty
                    <div class="pos-empty" style="grid-column: 1 / -1">{{ __('No items found') }}</div>
                @endforelse
            </div>
        </div>

        <div class="pos-panel">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                <strong>{{ __('Cart') }}</strong>
                <div style="display:flex; gap:12px; align-items:center;">
                    @if ($this->canSellWholesale())
                        <label style="font-size:12px; display:flex; gap:4px; align-items:center;">
                            <input type="checkbox" wire:model.live="wholesale"> {{ __('Wholesale') }}
                        </label>
                    @endif
                    @if ($cart !== [])<button type="button" class="pos-link" wire:click="clear">{{ __('Clear') }}</button>@endif
                </div>
            </div>

            @forelse ($cart as $key => $line)
                <div class="pos-line" wire:key="c-{{ $key }}">
                    <div>
                        <div style="font-size:13px; font-weight:600;">{{ $line['name'] }}</div>
                        @if ($line['modifiers'] !== [])<div class="m">+ {{ implode(', ', $line['modifiers']) }}</div>@endif
                        <div class="m">{{ $this->money($totals['lines'][$key]['unit_cents'] ?? 0) }} @if ($line['unit'] === 'kg') / kg @endif
                            @if ($line['discount_cents'] > 0) · {{ __('discount') }} {{ $this->money((int) $line['discount_cents']) }} @endif
                            @if ($this->isOwner()) · <button type="button" class="pos-link" wire:click="mountAction('discount', { key: '{{ $key }}' })">{{ __('discount') }}</button> @endif
                        </div>
                    </div>
                    <div style="text-align:end;">
                        <div style="font-weight:600;">{{ $this->money($totals['lines'][$key]['total_cents'] ?? 0) }}</div>
                        <div class="pos-qty" style="justify-content:flex-end; margin-top:4px;">
                            <button type="button" wire:click="decrement('{{ $key }}')">−</button>
                            <span style="min-width:44px; text-align:center; font-size:13px;">{{ rtrim(rtrim(number_format((float) $line['qty'], 3), '0'), '.') }}{{ $line['unit'] === 'kg' ? ' kg' : '' }}</span>
                            <button type="button" wire:click="increment('{{ $key }}')" @disabled($line['unit'] === 'kg')>+</button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="pos-empty">{{ __('Scan or tap an item to start a sale') }}</div>
            @endforelse

            <div style="margin-top:10px;">
                <div class="pos-tot"><span>{{ __('Subtotal') }}</span><span>{{ $this->money($totals['subtotal']) }}</span></div>
                @if ($totals['discount'] > 0)<div class="pos-tot"><span>{{ __('Discount') }}</span><span>−{{ $this->money($totals['discount']) }}</span></div>@endif
                <div class="pos-tot"><span>{{ __('Tax (:rate%)', ['rate' => rtrim(rtrim(number_format($totals['rate'], 3), '0'), '.')]) }}</span><span>{{ $this->money($totals['tax']) }}</span></div>
                <div class="pos-tot big"><span>{{ __('Total') }}</span><span>{{ $this->money($totals['total']) }}</span></div>
            </div>

            <div style="margin-top:12px; display:grid;">
                {{ $this->checkoutAction }}
            </div>

            @if ($lastSaleId)
                <div style="margin-top:10px; text-align:center;">
                    <a class="pos-link" href="{{ route('receipts.show', ['sale' => $lastSaleId, 'print' => 1]) }}" target="_blank">{{ __('Reprint last receipt') }}</a>
                </div>
            @endif
        </div>
    </div>

</x-filament-panels::page>
