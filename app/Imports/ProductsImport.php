<?php

namespace App\Imports;

use App\Enums\ProductUnit;
use App\Models\Business;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Services\CatalogService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * Bulk catalog import for onboarding the pilot stores (M2.5, D27).
 *
 * Columns: name_en, name_ar, category, sku, barcode, unit (piece/carton/kg),
 * cost, wholesale, retail, stock, low_stock, published. A row whose SKU
 * already exists updates that variant's prices; stock is only set on create.
 */
class ProductsImport implements SkipsEmptyRows, ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    /** @var array<int, string> */
    public array $errors = [];

    public function __construct(private readonly Business $business, private readonly ?int $userId = null) {}

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $line = $index + 2;

            try {
                $this->importRow($row->map(fn ($value) => is_string($value) ? trim($value) : $value)->all());
            } catch (\Throwable $exception) {
                $this->errors[] = __('Row :line: :message', ['line' => $line, 'message' => $exception->getMessage()]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function importRow(array $row): void
    {
        $nameEn = (string) ($row['name_en'] ?? '');

        if ($nameEn === '') {
            throw new \InvalidArgumentException(__('name_en is required.'));
        }

        $sku = filled($row['sku'] ?? null) ? (string) $row['sku'] : null;
        $prices = [
            'cost_price_cents' => Money::toCents($row['cost'] ?? 0),
            'wholesale_price_cents' => Money::toCents($row['wholesale'] ?? 0),
            'retail_price_cents' => Money::toCents($row['retail'] ?? 0),
        ];

        if ($sku !== null) {
            $existing = ProductVariant::query()->withoutGlobalScope('business')->where('business_id', $this->business->id)->where('sku', $sku)->first();

            if ($existing !== null) {
                $existing->update([
                    'wholesale_price_cents' => $prices['wholesale_price_cents'],
                    'retail_price_cents' => $prices['retail_price_cents'],
                    'low_stock_threshold' => filled($row['low_stock'] ?? null) ? (float) $row['low_stock'] : $existing->low_stock_threshold,
                ]);
                $this->updated++;

                return;
            }
        }

        if ($prices['retail_price_cents'] <= 0) {
            throw new \InvalidArgumentException(__('retail must be greater than zero.'));
        }

        $category = null;

        if (filled($row['category'] ?? null)) {
            $category = Category::query()->withoutGlobalScope('business')->firstOrCreate(
                ['business_id' => $this->business->id, 'slug' => Str::slug((string) $row['category']) ?: Str::lower(Str::random(6))],
                ['name' => ['en' => (string) $row['category'], 'ar' => (string) $row['category']], 'is_active' => true],
            );
        }

        $unit = ProductUnit::tryFrom(strtolower((string) ($row['unit'] ?? 'piece'))) ?? ProductUnit::Piece;

        app(CatalogService::class)->createProduct($this->business, [
            'category_id' => $category?->id,
            'name' => ['en' => $nameEn, 'ar' => (string) ($row['name_ar'] ?? '') ?: $nameEn],
            'is_published' => ! in_array(strtolower((string) ($row['published'] ?? 'yes')), ['0', 'no', 'false'], true),
            'is_available' => true,
            'track_stock' => $this->business->businessType?->key !== 'restaurant',
        ], $prices + [
            'sku' => $sku,
            'barcode' => filled($row['barcode'] ?? null) ? (string) $row['barcode'] : null,
            'unit' => $unit,
            'opening_qty' => (float) ($row['stock'] ?? 0),
            'low_stock_threshold' => filled($row['low_stock'] ?? null) ? (float) $row['low_stock'] : null,
        ], $this->userId);

        $this->created++;
    }
}
