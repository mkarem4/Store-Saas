<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\CustomerDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        $customer = $request->user();

        return response()->json([
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'locale' => $customer->locale,
                'addresses' => $customer->addresses()->get()->map(fn (CustomerAddress $address) => [
                    'id' => $address->id,
                    'label' => $address->label,
                    'address_line1' => $address->address_line1,
                    'address_line2' => $address->address_line2,
                    'city' => $address->city,
                    'state' => $address->state,
                    'postal_code' => $address->postal_code,
                    'phone' => $address->phone,
                    'is_default' => $address->is_default,
                ]),
                'orders_count' => $customer->orders()->count(),
            ],
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        $customer = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:customers,email,'.$customer->id],
            'phone' => ['sometimes', 'required', 'string', 'max:32'],
            'locale' => ['sometimes', 'in:en,ar'],
            'password' => ['sometimes', 'required', 'string', 'min:8', 'confirmed'],
        ]);

        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        unset($data['email']);
        $customer->update($data);

        return response()->json([
            'data' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'locale' => $customer->locale,
            ],
        ]);
    }

    /**
     * @return JsonResponse
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->user()->delete();

        return response()->json(['message' => __('Account deleted.')]);
    }

    /**
     * @return JsonResponse
     */
    public function storeDevice(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'in:ios,android'],
        ]);

        CustomerDevice::query()->updateOrCreate(
            ['customer_id' => $request->user()->id, 'fcm_token' => $data['fcm_token']],
            [
                'platform' => $data['platform'],
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['message' => __('Device registered.')], 201);
    }
}
