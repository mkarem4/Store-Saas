<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InquiryStatus;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Inquiry;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InquiryController extends Controller
{
    /**
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'business' => ['required', 'string'],
            'type' => ['sometimes', 'in:general,vehicle,rental,quote'],
            'vehicle_id' => ['nullable', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $business = Business::query()->where('slug', $data['business'])->firstOrFail();

        $vehicleId = null;

        if (! empty($data['vehicle_id'])) {
            $vehicle = Vehicle::query()
                ->where('business_id', $business->id)
                ->find($data['vehicle_id']);

            $vehicleId = $vehicle?->id;
        }

        $inquiry = Inquiry::query()->create([
            'business_id' => $business->id,
            'customer_id' => $request->user()?->id,
            'vehicle_id' => $vehicleId,
            'type' => $data['type'] ?? 'general',
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'message' => $data['message'] ?? null,
            'status' => InquiryStatus::New,
        ]);

        return response()->json([
            'data' => [
                'id' => $inquiry->id,
                'status' => $inquiry->status->value,
            ],
        ], 201);
    }
}
