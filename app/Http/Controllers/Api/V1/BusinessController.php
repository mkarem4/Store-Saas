<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessType;
use Illuminate\Http\JsonResponse;

class BusinessController extends Controller
{
    /**
     * @return JsonResponse
     */
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
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $businesses = Business::query()->where('status', 'active')->get();

        return response()->json([
            'data' => $businesses->map(fn (Business $business) => [
                'slug' => $business->slug,
                'name' => $business->name,
                'phone' => $business->phone,
                'email' => $business->email,
                'address_line' => $business->address_line,
                'city' => $business->city,
                'type' => $business->businessType?->key,
                'settings' => [
                    'pickup_enabled' => (bool) $business->settings('pickup_enabled'),
                    'delivery_enabled' => (bool) $business->settings('delivery_enabled'),
                    'delivery_fee_cents' => (int) $business->settings('delivery_fee_cents', 0),
                    'minimum_order_cents' => (int) $business->settings('minimum_order_cents', 0),
                    'currency' => $business->currency,
                ],
                'accepting_orders' => $business->acceptsOrders(),
                'open_now' => $business->isOpenNow(),
            ]),
        ]);
    }

    /**
     * @return JsonResponse
     */
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
                'type' => $business->businessType?->key,
                'logo_url' => $business->getMedia('logo')->first()?->getUrl(),
                'cover_url' => $business->getMedia('cover')->first()?->getUrl(),
                'settings' => [
                    'pickup_enabled' => (bool) $business->settings('pickup_enabled'),
                    'delivery_enabled' => (bool) $business->settings('delivery_enabled'),
                    'delivery_fee_cents' => (int) $business->settings('delivery_fee_cents', 0),
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
