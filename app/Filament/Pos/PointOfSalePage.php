<?php

namespace App\Filament\Pos;

use App\Enums\PaymentMethod;
use App\Filament\Business\BusinessPanel;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Support\ClientSelect;
use App\Models\Category;
use App\Models\Employee;
use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\ProductVariant;
use App\Services\SaleService;
use App\Support\Money;
use App\Support\Permission;
use App\Support\TaxCalculator;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Point of sale (S6, M3) shared by the store panel and the staff panel.
 * Prices always come from the server (rule 6); staff cannot discount,
 * override prices or return items. Tax is added on top (M3.12).
 */
abstract class PointOfSalePage extends Page
{
    protected string $view = 'filament.pos.point-of-sale';

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $search = '';

    public ?int $categoryId = null;

    /** @var array<string, array<string, mixed>> */
    public array $cart = [];

    public ?int $clientId = null;

    public ?int $employeeId = null;

    public bool $wholesale = false;

    public ?int $lastSaleId = null;

    public static function getNavigationLabel(): string
    {
        return __('Point of sale');
    }

    public function getTitle(): string
    {
        return __('Point of sale');
    }

    public static function canAccess(): bool
    {
        return BusinessPanel::can(Permission::UsePos) && ! BusinessPanel::isType(['car_showroom', 'car_rental']);
    }

    public function mount(): void
    {
        $this->employeeId = Employee::query()->where('user_id', auth()->id())->value('id');
    }

    public function isOwner(): bool
    {
        return BusinessPanel::can(Permission::RefundSales);
    }

    public function canSellWholesale(): bool
    {
        return BusinessPanel::can(Permission::SellWholesale);
    }

    /**
     * @return Collection<int, Category>
     */
    public function categories(): Collection
    {
        return Category::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function results(): Collection
    {
        $term = trim($this->search);

        return ProductVariant::query()
            ->with(['product.modifierGroups', 'jewelryAttribute'])
            ->whereHas('product', fn (Builder $query) => $query->where('is_available', true)
                ->when($this->categoryId, fn (Builder $q) => $q->where('category_id', $this->categoryId)))
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q
                ->where('barcode', $term)
                ->orWhere('sku', 'like', "{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhereHas('product', fn (Builder $p) => $p->where('name', 'like', "%{$term}%"))))
            ->orderBy('product_id')
            ->limit(30)
            ->get();
    }

    /**
     * Barcode scanners type the code and press Enter (M3.1).
     */
    public function scan(): void
    {
        $code = trim($this->search);

        if ($code === '') {
            return;
        }

        $variant = ProductVariant::query()->with('product.modifierGroups')
            ->where(fn (Builder $query) => $query->where('barcode', $code)->orWhere('sku', $code))
            ->first();

        if ($variant === null) {
            $results = $this->results();

            if ($results->count() !== 1) {
                return;
            }

            $variant = $results->first();
        }

        $this->search = '';
        $this->pick($variant->id);
    }

    public function pick(int $variantId): void
    {
        $variant = ProductVariant::query()->with('product.modifierGroups')->findOrFail($variantId);

        if ($variant->product->modifierGroups->isNotEmpty()) {
            $this->mountAction('modifiers', ['variant' => $variantId]);

            return;
        }

        if ($variant->unit->allowsDecimalQuantity()) {
            $this->mountAction('weight', ['variant' => $variantId]);

            return;
        }

        $this->addLine($variant, 1);
    }

    /**
     * @param  array<int, int>  $modifierIds
     */
    public function addLine(ProductVariant $variant, float $qty, array $modifierIds = []): void
    {
        if ($variant->product->track_stock && ! $variant->product->business->settings('allow_negative_stock') && $variant->availableQty() < $qty + $this->qtyInCart($variant->id)) {
            Notification::make()->warning()->title(__('Not enough stock for :name.', ['name' => $variant->displayName()]))->send();

            return;
        }

        sort($modifierIds);
        $modifiers = Modifier::query()->whereIn('id', $modifierIds)->get();

        // Same item with the same options stacks onto one line.
        foreach ($this->cart as $key => $line) {
            if ($line['variant_id'] === $variant->id && $line['modifier_ids'] === $modifierIds && ! $variant->unit->allowsDecimalQuantity()) {
                $this->cart[$key]['qty'] += $qty;

                return;
            }
        }

        $this->cart[(string) Str::uuid()] = [
            'variant_id' => $variant->id,
            'name' => $variant->displayName(),
            'unit' => $variant->unit->value,
            'qty' => $qty,
            'modifier_ids' => $modifierIds,
            'modifiers' => $modifiers->map(fn (Modifier $modifier): string => $modifier->translatedName())->all(),
            'modifier_delta_cents' => (int) $modifiers->sum('price_delta_cents'),
            'discount_cents' => 0,
        ];
    }

    public function qtyInCart(int $variantId): float
    {
        return (float) collect($this->cart)->where('variant_id', $variantId)->sum('qty');
    }

    public function increment(string $key): void
    {
        if (isset($this->cart[$key]) && $this->cart[$key]['unit'] !== 'kg') {
            $this->cart[$key]['qty']++;
        }
    }

    public function decrement(string $key): void
    {
        if (! isset($this->cart[$key])) {
            return;
        }

        if ($this->cart[$key]['unit'] === 'kg' || $this->cart[$key]['qty'] <= 1) {
            unset($this->cart[$key]);

            return;
        }

        $this->cart[$key]['qty']--;
    }

    public function remove(string $key): void
    {
        unset($this->cart[$key]);
    }

    public function clear(): void
    {
        $this->cart = [];
        $this->clientId = null;
        $this->wholesale = false;
    }

    /**
     * Server-side preview of what the sale will charge.
     *
     * @return array{lines: array<string, array<string, mixed>>, subtotal: int, discount: int, tax: int, total: int, rate: float}
     */
    public function totals(): array
    {
        $business = BusinessPanel::business();
        $variants = ProductVariant::query()->with('jewelryAttribute')->whereIn('id', collect($this->cart)->pluck('variant_id'))->get()->keyBy('id');
        $wholesale = $this->wholesale && $this->canSellWholesale();
        $lines = [];
        $subtotal = 0;
        $discount = 0;

        foreach ($this->cart as $key => $line) {
            $variant = $variants[$line['variant_id']] ?? null;

            if ($variant === null) {
                continue;
            }

            $unit = $variant->priceFor($wholesale) + (int) $line['modifier_delta_cents'];
            $gross = (int) round($unit * (float) $line['qty']);
            $lineDiscount = min((int) $line['discount_cents'], $gross);

            $lines[$key] = ['unit_cents' => $unit, 'total_cents' => $gross - $lineDiscount];
            $subtotal += $gross;
            $discount += $lineDiscount;
        }

        $rate = TaxCalculator::appliesToPos() ? TaxCalculator::rateFor($business) : 0.0;
        $tax = TaxCalculator::taxCents($subtotal - $discount, $rate);

        return ['lines' => $lines, 'subtotal' => $subtotal, 'discount' => $discount, 'tax' => $tax, 'total' => $subtotal - $discount + $tax, 'rate' => $rate];
    }

    public function money(int $cents): string
    {
        return Money::format($cents, BusinessPanel::currency());
    }

    public function modifiersAction(): Action
    {
        return Action::make('modifiers')
            ->modalSubmitActionLabel(__('Add to sale'))
            ->modalHeading(fn (array $arguments): string => (string) ProductVariant::query()->find($arguments['variant'] ?? 0)?->displayName())
            ->modalWidth(Width::Large)
            ->schema(function (array $arguments): array {
                $variant = ProductVariant::query()->with('product.modifierGroups.modifiers')->findOrFail($arguments['variant']);

                return $variant->product->modifierGroups->map(function (ModifierGroup $group): Component {
                    $options = $group->modifiers->where('is_available', true)->mapWithKeys(fn (Modifier $modifier): array => [
                        $modifier->id => $modifier->translatedName().($modifier->price_delta_cents ? ' ('.($modifier->price_delta_cents > 0 ? '+' : '').$this->money((int) $modifier->price_delta_cents).')' : ''),
                    ])->all();

                    $field = $group->max_selections > 1
                        ? CheckboxList::make("group_{$group->id}")->options($options)->minItems($group->is_required ? max(1, $group->min_selections) : $group->min_selections)->maxItems($group->max_selections)
                        : Radio::make("group_{$group->id}")->options($options);

                    return $field->label($group->translatedName())->required($group->is_required || $group->min_selections > 0);
                })->all();
            })
            ->action(function (array $data, array $arguments): void {
                $variant = ProductVariant::query()->with('product')->findOrFail($arguments['variant']);
                $ids = collect($data)->flatten()->filter()->map(fn ($id): int => (int) $id)->values()->all();

                $this->addLine($variant, 1, $ids);
            });
    }

    public function weightAction(): Action
    {
        return Action::make('weight')
            ->modalSubmitActionLabel(__('Add to sale'))
            ->modalHeading(fn (array $arguments): string => (string) ProductVariant::query()->find($arguments['variant'] ?? 0)?->displayName())
            ->modalWidth(Width::Small)
            ->schema([
                TextInput::make('qty')->label(__('Weight'))->numeric()->minValue(0.001)->step('0.001')->suffix('kg')->required()->autofocus(),
            ])
            ->action(function (array $data, array $arguments): void {
                $this->addLine(ProductVariant::query()->with('product')->findOrFail($arguments['variant']), round((float) $data['qty'], 3));
            });
    }

    public function discountAction(): Action
    {
        return Action::make('discount')
            ->modalSubmitActionLabel(__('Apply discount'))
            ->visible(fn (): bool => $this->isOwner())
            ->modalWidth(Width::Small)
            ->fillForm(fn (array $arguments): array => ['discount_cents' => $this->cart[$arguments['key']]['discount_cents'] ?? 0])
            ->schema([MoneyInput::make('discount_cents')->label(__('Line discount'))])
            ->action(function (array $data, array $arguments): void {
                if (isset($this->cart[$arguments['key']])) {
                    $this->cart[$arguments['key']]['discount_cents'] = (int) $data['discount_cents'];
                }
            });
    }

    public function checkoutAction(): Action
    {
        return Action::make('checkout')
            ->label(fn (): string => __('Charge :total', ['total' => $this->money($this->totals()['total'])]))
            ->icon(Heroicon::OutlinedBanknotes)
            ->size('xl')
            ->disabled(fn (): bool => $this->cart === [])
            ->modalHeading(fn (): string => __('Total :total', ['total' => $this->money($this->totals()['total'])]))
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel(__('Complete sale'))
            ->fillForm(fn (): array => ['method' => 'cash', 'client_id' => $this->clientId, 'employee_id' => $this->employeeId])
            ->schema(fn (): array => [
                Radio::make('method')->label(__('Payment'))->inline()->live()->required()->options([
                    'cash' => __('Cash'),
                    'card' => __('Card'),
                    'split' => __('Cash + card'),
                ]),
                Grid::make(2)->schema([
                    MoneyInput::make('tendered_cents')->label(__('Cash given'))->live(debounce: 300)
                        ->visible(fn (Get $get): bool => $get('method') === 'cash'),
                    Text::make(fn (Get $get): string => __('Change: :change', ['change' => $this->money(max(0, Money::toCents($get('tendered_cents')) - $this->totals()['total']))]))
                        ->visible(fn (Get $get): bool => $get('method') === 'cash' && filled($get('tendered_cents'))),
                    MoneyInput::make('cash_cents')->label(__('Cash part'))->live(debounce: 300)->required()
                        ->visible(fn (Get $get): bool => $get('method') === 'split'),
                    Text::make(fn (Get $get): string => __('Card part: :amount', ['amount' => $this->money(max(0, $this->totals()['total'] - Money::toCents($get('cash_cents'))))]))
                        ->visible(fn (Get $get): bool => $get('method') === 'split'),
                    TextInput::make('card_ref')->label(__('Card approval # (optional)'))
                        ->visible(fn (Get $get): bool => in_array($get('method'), ['card', 'split'], true)),
                ]),
                ClientSelect::make(required: false)->label(__('Client (optional)')),
                Select::make('employee_id')->label(__('Sold by'))->visible(fn (): bool => $this->isOwner())
                    ->options(fn (): array => Employee::query()->where('is_active', true)->pluck('name', 'id')->all()),
            ])
            ->action(function (array $data): void {
                $this->completeSale($data);
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function completeSale(array $data): void
    {
        $total = $this->totals()['total'];
        $payments = match ($data['method']) {
            'card' => [['method' => PaymentMethod::Card->value, 'amount_cents' => $total, 'gateway_ref' => $data['card_ref'] ?? null]],
            'split' => array_values(array_filter([
                ['method' => PaymentMethod::Cash->value, 'amount_cents' => min($total, (int) $data['cash_cents'])],
                ['method' => PaymentMethod::Card->value, 'amount_cents' => max(0, $total - (int) $data['cash_cents']), 'gateway_ref' => $data['card_ref'] ?? null],
            ], fn (array $payment): bool => $payment['amount_cents'] > 0)),
            default => [['method' => PaymentMethod::Cash->value, 'amount_cents' => $total, 'meta' => filled($data['tendered_cents'] ?? null) && (int) $data['tendered_cents'] >= $total ? ['tendered_cents' => (int) $data['tendered_cents']] : null]],
        };

        if (($data['method'] ?? null) === 'cash' && filled($data['tendered_cents'] ?? null) && (int) $data['tendered_cents'] < $total) {
            Notification::make()->danger()->title(__('Cash given is less than the total.'))->send();

            return;
        }

        try {
            $sale = app(SaleService::class)->createPosSale(
                BusinessPanel::business(),
                array_values(array_map(fn (array $line): array => [
                    'variant_id' => $line['variant_id'],
                    'qty' => $line['qty'],
                    'discount_cents' => $this->isOwner() ? (int) $line['discount_cents'] : 0,
                    'modifier_ids' => $line['modifier_ids'],
                    'wholesale' => $this->wholesale && $this->canSellWholesale(),
                ], $this->cart)),
                $payments,
                employeeId: $this->isOwner() ? ($data['employee_id'] ?? $this->employeeId) : $this->employeeId,
                clientId: $data['client_id'] ?? null,
                userId: auth()->id(),
                canSellWholesale: $this->canSellWholesale(),
            );
        } catch (InvalidArgumentException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        $this->lastSaleId = $sale->id;
        $this->clear();

        Notification::make()->success()->title(__('Sale :number completed', ['number' => $sale->number]))
            ->actions([
                Action::make('print')->label(__('Print receipt'))->url(route('receipts.show', ['sale' => $sale->id, 'print' => 1]), shouldOpenInNewTab: true),
            ])
            ->send();

        $this->js("window.open('".route('receipts.show', ['sale' => $sale->id, 'print' => 1])."', '_blank')");
    }
}
