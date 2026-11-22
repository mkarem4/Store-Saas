<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function types(): JsonResponse
    {
        $types = BusinessType::query()->where('is_active', true)->orderBy('sort_order')->get();

        return response()->json([
            'data' => $types->map(fn (BusinessType $type) => [
                'key' => $type->key,
                'name' => $type->name,
                'icon' => $type->icon,
            ]),
        ]);
    }

    /**
     * Stores in the app (M8.1): filter by type, name and fulfillment.
     */
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));
        $fulfillment = (string) $request->query('fulfillment', '');

        $businesses = Business::query()
            ->where('status', 'active')
            ->with(['businessType', 'media'])
            ->when($request->filled('type'), fn ($query) => $query->whereHas('businessType', fn ($q) => $q->where('key', $request->string('type'))))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->when(in_array($fulfillment, ['pickup', 'delivery'], true), fn ($query) => $query->where("settings->{$fulfillment}_enabled", true))
            ->orderBy('id')
            ->paginate(min((int) $request->query('per_page', 20), 50));

        return response()->json([
            'meta' => [
                'current_page' => $businesses->currentPage(),
                'last_page' => $businesses->lastPage(),
                'per_page' => $businesses->perPage(),
                'total' => $businesses->total(),
            ],
            'data' => $businesses->getCollection()->map(fn (Business $business) => [
                'logo_url' => $business->getFirstMediaUrl('logo') ?: null,
                'slug' => $business->slug,
                'name' => $business->name,
                'phone' => $business->phone,
                'email' => $business->email,
                'address_line' => $business->address_line,
                'city' => $business->city,
                'latitude' => $business->latitude,
                'longitude' => $business->longitude,
                'type' => $business->businessType?->key,
                'settings' => [
                    'pickup_enabled' => (bool) $business->settings('pickup_enabled'),
                    'delivery_enabled' => (bool) $business->settings('delivery_enabled'),
                    'delivery_base_fee_cents' => (int) $business->settings('delivery_base_fee_cents', 0),
                    'delivery_fee_per_km_cents' => (int) $business->settings('delivery_fee_per_km_cents', 0),
                    'delivery_max_km' => (float) $business->settings('delivery_max_km', 0),
                    'minimum_order_cents' => (int) $business->settings('minimum_order_cents', 0),
                    'currency' => $business->currency,
                ],
                'accepting_orders' => $business->acceptsOrders(),
                'open_now' => $business->isOpenNow(),
            ]),
        ]);
    }

    public function show(Business $business): JsonResponse
    {
        return response()->json([
            'data' => [
                'slug' => $business->slug,
                'name' => $business->name,
                'description' => $business->description,
                'phone' => $business->phone,
                'email' => $business->email,
                'website' => $business->website,
                'address_line' => $business->address_line,
                'city' => $business->city,
                'state' => $business->state,
                'postal_code' => $business->postal_code,
                'country' => $business->country,
                'latitude' => $business->latitude,
                'longitude' => $business->longitude,
                'tax_rate' => $business->taxRate(),
                'type' => $business->businessType?->key,
                'logo_url' => $business->getMedia('logo')->first()?->getUrl(),
                'cover_url' => $business->getMedia('cover')->first()?->getUrl(),
                'settings' => [
                    'pickup_enabled' => (bool) $business->settings('pickup_enabled'),
                    'delivery_enabled' => (bool) $business->settings('delivery_enabled'),
                    'delivery_base_fee_cents' => (int) $business->settings('delivery_base_fee_cents', 0),
                    'delivery_fee_per_km_cents' => (int) $business->settings('delivery_fee_per_km_cents', 0),
                    'delivery_max_km' => (float) $business->settings('delivery_max_km', 0),
                    'minimum_order_cents' => (int) $business->settings('minimum_order_cents', 0),
                    'currency' => $business->currency,
                    'currency_decimals' => (int) $business->currency_decimals,
                ],
                'accepting_orders' => $business->acceptsOrders(),
                'open_now' => $business->isOpenNow(),
                'working_hours' => $business->settings('working_hours'),
            ],
        ]);
    }
}
