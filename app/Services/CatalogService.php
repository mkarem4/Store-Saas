<?php

namespace App\Services;

use App\Models\Business;
use App\Models\JewelryAttribute;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates products with their variant(s); opening stock goes through
 * receiving so it always carries a cost (D9).
 */
class CatalogService
{
    public function __construct(private readonly StockService $stock) {}

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $variant  sku, barcode, unit, prices (cents), opening_qty, low_stock_threshold, expiry_date, jewelry
     */
    public function createProduct(Business $business, array $product, array $variant, ?int $userId = null): Product
    {
        return DB::transaction(function () use ($business, $product, $variant, $userId): Product {
            $model = Product::query()->create(array_merge(Arr::only($product, [
                'category_id', 'name', 'description', 'sku', 'barcode', 'attributes', 'is_published',
                'is_available', 'track_stock', 'has_modifiers', 'prep_time_minutes', 'sort_order',
            ]), ['business_id' => $business->id]));

            $this->addVariant($model, array_merge(['is_default' => true], $variant), $userId);

            return $model->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addVariant(Product $product, array $data, ?int $userId = null): ProductVariant
    {
        $openingQty = (float) ($data['opening_qty'] ?? 0);
        $cost = (int) ($data['cost_price_cents'] ?? 0);

        if ($openingQty > 0 && $cost <= 0) {
            throw new InvalidArgumentException(__('Enter the purchase cost for the opening stock.'));
        }

        return DB::transaction(function () use ($product, $data, $openingQty, $cost, $userId): ProductVariant {
            $variant = ProductVariant::query()->create(array_merge(Arr::only($data, [
                'name', 'sku', 'barcode', 'unit', 'wholesale_price_cents', 'retail_price_cents',
                'low_stock_threshold', 'expiry_date', 'is_default', 'attributes', 'sort_order',
            ]), [
                'business_id' => $product->business_id,
                'product_id' => $product->id,
                'name' => array_filter((array) ($data['name'] ?? [])) === [] ? ['en' => '', 'ar' => ''] : $data['name'],
                'sku' => blank($data['sku'] ?? null) ? null : $data['sku'],
                'barcode' => blank($data['barcode'] ?? null) ? null : $data['barcode'],
                'cost_price_cents' => $openingQty > 0 ? 0 : $cost,
                'stock_qty' => 0,
                'reserved_qty' => 0,
            ]));

            $this->saveJewelry($variant, $data['jewelry'] ?? null);

            if ($openingQty > 0) {
                $this->stock->receive($variant, $openingQty, $cost, null, $userId, __('Opening stock'));
            }

            return $variant->refresh();
        });
    }

    /**
     * @param  array<string, mixed>|null  $jewelry
     */
    public function saveJewelry(ProductVariant $variant, ?array $jewelry): void
    {
        if ($jewelry === null || blank($jewelry['pricing_mode'] ?? null)) {
            return;
        }

        JewelryAttribute::query()->updateOrCreate(
            ['product_variant_id' => $variant->id],
            array_merge(Arr::only($jewelry, ['pricing_mode', 'weight_g', 'karat', 'metal', 'making_type', 'making_value', 'certificate_number']), [
                'business_id' => $variant->business_id,
            ]),
        );
    }
}
