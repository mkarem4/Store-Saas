<?php

namespace App\Exports;

use App\Models\ProductVariant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Same columns as ProductsImport, so an export can be edited and re-imported.
 */
class ProductsExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function __construct(private readonly int $businessId) {}

    public function query(): Builder
    {
        return ProductVariant::query()
            ->withoutGlobalScope('business')
            ->where('business_id', $this->businessId)
            ->with('product.category')
            ->orderBy('product_id');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['name_en', 'name_ar', 'category', 'sku', 'barcode', 'unit', 'cost', 'wholesale', 'retail', 'stock', 'low_stock', 'published'];
    }

    /**
     * @param  ProductVariant  $variant
     * @return array<int, mixed>
     */
    public function map($variant): array
    {
        $product = $variant->product;

        return [
            $product?->translated('name', 'en'),
            $product?->translated('name', 'ar'),
            $product?->category?->translated('name', 'en'),
            $variant->sku,
            $variant->barcode,
            $variant->unit->value,
            Money::fromCents((int) $variant->cost_price_cents),
            Money::fromCents((int) $variant->wholesale_price_cents),
            Money::fromCents((int) $variant->retail_price_cents),
            (float) $variant->stock_qty,
            $variant->low_stock_threshold !== null ? (float) $variant->low_stock_threshold : null,
            $product?->is_published ? 'yes' : 'no',
        ];
    }
}
