<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAddressController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $addresses = $request->user()->addresses()->orderByDesc('is_default')->get();

        return response()->json([
            'data' => $addresses->map(fn (CustomerAddress $address): array => $this->transform($address)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $address = $request->user()->addresses()->create($this->validated($request));

        return response()->json(['data' => $this->transform($address)], 201);
    }

    public function update(Request $request, CustomerAddress $address): JsonResponse
    {
        abort_unless((int) $address->customer_id === (int) $request->user()->id, 403);

        $address->update($this->validated($request));

        return response()->json(['data' => $this->transform($address->refresh())]);
    }

    public function destroy(Request $request, CustomerAddress $address): JsonResponse
    {
        abort_unless((int) $address->customer_id === (int) $request->user()->id, 403);

        $address->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'phone' => ['nullable', 'string', 'max:32'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(CustomerAddress $address): array
    {
        return [
            'id' => $address->id,
            'label' => $address->label,
            'address_line1' => $address->address_line1,
            'address_line2' => $address->address_line2,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
            'phone' => $address->phone,
            'is_default' => $address->is_default,
        ];
    }
}
