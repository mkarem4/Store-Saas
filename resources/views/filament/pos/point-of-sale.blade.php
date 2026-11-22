@php
    $totals = $this->totals();
    $results = $this->results();
    $categories = $this->categories();
    $wholesaleOn = $wholesale && $this->canSellWholesale();
    $qty = fn (float $value): string => rtrim(rtrim(number_format($value, 3), '0'), '.');
@endphp

<x-filament-panels::page>
    <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_400px]">
        {{-- Catalog --}}
        <div class="flex min-w-0 flex-col gap-4">
            <x-filament::input.wrapper prefix-icon="heroicon-o-qr-code">
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.250ms="search"
                    wire:keydown.enter.prevent="scan"
                    :placeholder="__('Scan a barcode or search by name / SKU')"
                    autofocus
                    autocomplete="off"
                    {{-- Back to the scanner after each add, but don't pop the keyboard on touch screens. --}}
                    x-on:pos-line-added.window="if (window.matchMedia('(pointer: fine)').matches) $el.focus()"
                    inputmode="search"
                />
            </x-filament::input.wrapper>

            @if ($categories->isNotEmpty())
                <x-filament::tabs :label="__('Categories')" contained>
                    <x-filament::tabs.item :active="$categoryId === null" wire:click="$set('categoryId', null)">
                        {{ __('All') }}
                    </x-filament::tabs.item>
                    @foreach ($categories as $category)
                        <x-filament::tabs.item :active="$categoryId === $category->id" wire:click="$set('categoryId', {{ $category->id }})" wire:key="cat-{{ $category->id }}">
                            {{ $category->translatedName() }}
                        </x-filament::tabs.item>
                    @endforeach
                </x-filament::tabs>
            @endif

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4" wire:loading.class="opacity-60" wire:target="search,categoryId">
                @forelse ($results as $variant)
                    @php
                        $available = $variant->availableQty();
                        $tracks = $variant->product->track_stock;
                    @endphp
                    <button type="button" class="yl-tile" wire:click="pick({{ $variant->id }})" wire:key="v-{{ $variant->id }}">
                        <span class="line-clamp-2 text-sm font-medium text-gray-950 dark:text-white">{{ $variant->displayName() }}</span>
                        <span class="flex flex-wrap items-end justify-between gap-x-2 gap-y-1">
                            <span class="yl-figure whitespace-nowrap text-base font-semibold text-gray-950 dark:text-white">
                                {{ $this->money($variant->priceFor($wholesaleOn)) }}<span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ $variant->unit->value === 'kg' ? ' / kg' : '' }}</span>
                            </span>
                            @if ($tracks)
                                <x-filament::badge size="sm" :color="$available <= 0 ? 'danger' : ($variant->isLowStock() ? 'warning' : 'gray')">
                                    {{ $available <= 0 ? __('Out of stock') : __(':qty left', ['qty' => $qty($available)]) }}
                                </x-filament::badge>
                            @endif
                        </span>
                    </button>
                @empty
                    <div class="col-span-full">
                        <x-filament::empty-state
                            icon="heroicon-o-magnifying-glass"
                            :heading="$search === '' ? __('No items in this category') : __('Nothing matches “:term”', ['term' => $search])"
                            :description="__('Check the spelling or scan the barcode again.')"
                            :contained="false"
                        />
                    </div>
                @endforelse
            </div>
        </div>

        {{-- The live receipt --}}
        <aside id="pos-cart" class="yl-receipt flex scroll-mt-4 flex-col p-5 lg:sticky lg:top-6">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('Current sale') }}</h2>
                <div class="flex items-center gap-4">
                    @if ($this->canSellWholesale())
                        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <x-filament::input.checkbox wire:model.live="wholesale" />
                            {{ __('Wholesale') }}
                        </label>
                    @endif
                    @if ($cart !== [])
                        <x-filament::link tag="button" color="gray" size="sm" wire:click="clear">{{ __('Clear') }}</x-filament::link>
                    @endif
                </div>
            </div>

            <div class="mt-3 flex flex-col divide-y divide-gray-950/5 dark:divide-white/10">
                @forelse ($cart as $key => $line)
                    <div class="flex items-start justify-between gap-3 py-3" wire:key="c-{{ $key }}">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $line['name'] }}</p>
                            @if ($line['modifiers'] !== [])
                                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ implode('، ', $line['modifiers']) }}</p>
                            @endif
                            <p class="yl-figure mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ $this->money($totals['lines'][$key]['unit_cents'] ?? 0) }}{{ $line['unit'] === 'kg' ? ' / kg' : '' }}
                                @if ($line['discount_cents'] > 0)
                                    <span class="text-danger-600 dark:text-danger-400">−{{ $this->money((int) $line['discount_cents']) }}</span>
                                @endif
                            </p>
                            @if ($this->isOwner())
                                <x-filament::link tag="button" size="sm" class="mt-1" wire:click="mountAction('discount', { key: '{{ $key }}' })">
                                    {{ $line['discount_cents'] > 0 ? __('Change discount') : __('Add discount') }}
                                </x-filament::link>
                            @endif
                        </div>
                        <div class="flex shrink-0 flex-col items-end gap-2">
                            <span class="yl-figure text-sm font-semibold text-gray-950 dark:text-white">{{ $this->money($totals['lines'][$key]['total_cents'] ?? 0) }}</span>
                            <div class="flex items-center gap-1">
                                <x-filament::icon-button icon="heroicon-m-minus" color="gray" size="lg" :label="__('Remove one')" wire:click="decrement('{{ $key }}')" />
                                <span class="yl-figure min-w-12 text-center text-sm text-gray-950 dark:text-white">{{ $qty((float) $line['qty']) }}{{ $line['unit'] === 'kg' ? ' kg' : '' }}</span>
                                <x-filament::icon-button icon="heroicon-m-plus" color="gray" size="lg" :label="__('Add one')" :disabled="$line['unit'] === 'kg'" wire:click="increment('{{ $key }}')" />
                            </div>
                        </div>
                    </div>
                @empty
                    <x-filament::empty-state
                        icon="heroicon-o-shopping-bag"
                        :heading="__('No items yet')"
                        :description="__('Scan a barcode or tap an item to start the sale.')"
                        :contained="false"
                    />
                @endforelse
            </div>

            <div class="yl-tear" aria-hidden="true"></div>

            <dl class="yl-figure flex flex-col gap-1.5 text-sm">
                <div class="flex justify-between text-gray-600 dark:text-gray-300"><dt>{{ __('Subtotal') }}</dt><dd>{{ $this->money($totals['subtotal']) }}</dd></div>
                @if ($totals['discount'] > 0)
                    <div class="flex justify-between text-gray-600 dark:text-gray-300"><dt>{{ __('Discount') }}</dt><dd>−{{ $this->money($totals['discount']) }}</dd></div>
                @endif
                <div class="flex justify-between text-gray-600 dark:text-gray-300">
                    <dt>{{ __('Tax (:rate%)', ['rate' => rtrim(rtrim(number_format($totals['rate'], 3), '0'), '.')]) }}</dt>
                    <dd>{{ $this->money($totals['tax']) }}</dd>
                </div>
                <div class="mt-1 flex items-baseline justify-between text-gray-950 dark:text-white">
                    <dt class="text-base font-medium">{{ __('Total') }}</dt>
                    <dd class="text-2xl font-semibold">{{ $this->money($totals['total']) }}</dd>
                </div>
            </dl>

            <div class="mt-5 grid">
                {{ $this->checkoutAction }}
            </div>

            @if ($lastSaleId)
                <div class="mt-3 text-center">
                    <x-filament::link :href="route('receipts.show', ['sale' => $lastSaleId, 'print' => 1])" target="_blank" icon="heroicon-m-printer" color="gray" size="sm">
                        {{ __('Reprint last receipt') }}
                    </x-filament::link>
                </div>
            @endif
        </aside>
    </div>

    {{-- Phone: the cart sits below the catalogue, so a sticky bar shows what's in it and jumps there. --}}
    @if ($cart !== [])
        <a href="#pos-cart" class="yl-pos-cartbar lg:hidden">
            <span class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-shopping-bag" class="size-5" />
                {{ trans_choice(':count item|:count items', count($cart)) }}
            </span>
            <span class="yl-figure font-semibold">{{ $this->money($totals['total']) }}</span>
        </a>
    @endif
</x-filament-panels::page>
