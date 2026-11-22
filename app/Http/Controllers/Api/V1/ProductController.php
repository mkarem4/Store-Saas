<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request, Business $business): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 24), 100);

        $products = Product::query()
            ->where('business_id', $business->id)
            ->where('is_published', true)
            ->when(
                $request->filled('category'),
                fn ($query) => $query->whereHas('category', fn ($q) => $q->where('slug', $request->string('category'))),
            )
            ->when(
                $request->filled('q') || $request->filled('search'),
                fn ($query) => $query->whereRaw('name LIKE ?', ['%'.(string) $request->query('q', $request->query('search')).'%']),
            )
            ->with(['variants' => fn ($query) => $query->orderBy('sort_order'), 'category', 'modifierGroups.modifiers', 'media'])
            ->orderByDesc('is_available')
            ->orderBy('sort_order')
            ->paginate($perPage);

        return response()->json([
            'data' => $products->map(fn (Product $product) => $this->transform($product)),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(Business $business, Product $product): JsonResponse
    {
        abort_unless((int) $product->business_id === (int) $business->id, 404);
        abort_unless($product->is_published, 404);

        $product->load(['variants' => fn ($query) => $query->orderBy('sort_order'), 'category', 'modifierGroups.modifiers']);

        return response()->json([
            'data' => $this->transform($product),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Product $product): array
    {
        // M8.2: out-of-stock or switched-off items stay listed, marked unavailable.
        $variants = $product->variants
            ->map(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'name' => $variant->translated('name'),
                'available' => $product->is_available && (! $product->track_stock || $variant->availableQty() > 0),
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'unit' => $variant->unit->value,
                'price_cents' => $variant->priceFor(),
                'in_stock' => $product->track_stock ? $variant->availableQty() > 0 : true,
                'attributes' => $variant->attributes,
            ]);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'category' => $product->category?->slug,
            'images' => $product->getMedia('images')->map(fn ($media) => $media->getUrl())->values(),
            'price_cents' => $variants->min('price_cents'),
            'available' => $variants->contains('available', true),
            'variants' => $variants,
            'modifiers' => collect($product->modifierGroups ?? [])->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->translated('name'),
                'required' => $group->is_required,
                'min_selections' => $group->min_selections,
                'max_selections' => $group->max_selections,
                'choices' => $group->modifiers
                    ->where('is_available', true)
                    ->map(fn ($modifier) => [
                        'id' => $modifier->id,
                        'name' => $modifier->translated('name'),
                        'price_delta_cents' => $modifier->price_delta_cents,
                    ]),
            ]),
            'prep_time_minutes' => $product->prep_time_minutes,
        ];
    }
}
