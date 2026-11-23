<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FulfillmentType;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /**
     * Price a cart without creating an order.
     */
    public function quote(Request $request): JsonResponse
    {
        $data = $this->validateCart($request);

        $business = $this->business($request);

        try {
            $quote = $this->orders->quote(
                $business,
                $data['items'],
                FulfillmentType::from($data['fulfillment']),
                $this->address($request, $data),
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['' => $exception->getMessage()]);
        }

        unset($quote['lines']);

        return response()->json(['data' => $quote], 200);
    }

    /**
     * Place an order.
     */
    public function store(Request $request): JsonResponse
    {
        $customer = $request->user();
        $data = $this->validateCart($request);

        $business = $this->business($request);

        try {
            $order = $this->orders->place(
                $customer,
                $business,
                $data['items'],
                FulfillmentType::from($data['fulfillment']),
                $this->address($request, $data),
                $request->input('notes'),
                $request->header('Idempotency-Key'),
                $request->input('payment_method_id'),
            );
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['' => $exception->getMessage()]);
        }

        return response()->json([
            'data' => $this->transform($order),
            'payment_client_secret' => $order->paymentClientSecret,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->latest('placed_at')
            ->paginate(min((int) $request->query('per_page', 20), 50));

        return response()->json([
            'data' => $orders->map(fn (Order $order): array => $this->transform($order)),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
        ]);
    }

    public function show(Request $request, string $number): JsonResponse
    {
        $order = $this->customerOrder($request, $number)->load(['items.modifiers', 'statusHistory']);

        return response()->json([
            'data' => $this->transform($order, true),
        ]);
    }

    public function cancel(Request $request, string $number): JsonResponse
    {
        $order = $this->customerOrder($request, $number);

        try {
            $order = $this->orders->cancelByCustomer($order);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['' => $exception->getMessage()]);
        }

        return response()->json([
            'data' => $this->transform($order),
        ]);
    }

    private function customerOrder(Request $request, string $number): Order
    {
        $data = $request->validate(['business' => ['sometimes', 'required', 'string', 'max:255']]);
        $orders = $request->user()->orders()->where('number', $number);

        if (isset($data['business'])) {
            $orders->whereRelation('business', 'slug', $data['business']);
        }

        $matches = $orders->limit(2)->get();

        abort_if($matches->isEmpty(), 404);
        abort_if($matches->count() > 1, 409, __('Specify the store to identify this order.'));

        return $matches->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCart(Request $request): array
    {
        return $request->validate([
            'business' => ['required', 'string'],
            'fulfillment' => ['required', 'in:pickup,delivery'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'payment_method_id' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.modifier_ids' => ['array'],
            'items.*.modifier_ids.*' => ['integer'],
            'address_id' => ['nullable', 'integer'],
            'address' => ['nullable', 'array'],
            'address.line1' => ['required_with:address', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['required_with:address', 'string', 'max:120'],
            'address.phone' => ['required_with:address', 'string', 'max:32'],
            'address.notes' => ['nullable', 'string', 'max:500'],
            'address.latitude' => ['required_with:address', 'numeric', 'between:-90,90'],
            'address.longitude' => ['required_with:address', 'numeric', 'between:-180,180'],
        ]);
    }

    /**
     * Delivery address from a saved address (address_id) or inline fields.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private function address(Request $request, array $data): ?array
    {
        if (($data['fulfillment'] ?? null) !== FulfillmentType::Delivery->value) {
            return null;
        }

        if (! empty($data['address_id']) && $request->user() !== null) {
            $saved = $request->user()->addresses()->find($data['address_id']);

            if ($saved === null) {
                throw ValidationException::withMessages(['address_id' => __('Address not found.')]);
            }

            return [
                'line1' => $saved->address_line1,
                'line2' => $saved->address_line2,
                'city' => $saved->city,
                'state' => $saved->state,
                'postal_code' => $saved->postal_code,
                'phone' => $saved->phone ?? $request->user()->phone,
                'notes' => $data['address']['notes'] ?? null,
                'latitude' => $saved->latitude,
                'longitude' => $saved->longitude,
            ];
        }

        return $data['address'] ?? null;
    }

    private function business(Request $request): Business
    {
        $business = Business::query()->where('slug', $request->input('business'))->firstOrFail();

        if (! $business->acceptsOrders()) {
            throw ValidationException::withMessages(['business' => __('This store is not accepting orders right now.')]);
        }

        return $business;
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Order $order, bool $withItems = false): array
    {
        $data = [
            'number' => $order->number,
            'business' => $order->business?->slug,
            'fulfillment' => $order->fulfillment->value,
            'status' => $order->status->value,
            'status_label' => $order->status->label(),
            'payment_status' => $order->payment_status->value,
            'subtotal_cents' => $order->subtotal_cents,
            'delivery_fee_cents' => $order->delivery_fee_cents,
            'delivery_distance_km' => $order->delivery_distance_km,
            'discount_cents' => $order->discount_cents,
            'tax_rate' => $order->tax_rate,
            'tax_cents' => $order->tax_cents,
            'refunded_cents' => $order->refunded_cents,
            'total_cents' => $order->total_cents,
            'notes' => $order->notes,
            'placed_at' => $order->placed_at?->toIso8601String(),
            'expires_at' => $order->expires_at?->toIso8601String(),
            'accepted_at' => $order->accepted_at?->toIso8601String(),
            'completed_at' => $order->completed_at?->toIso8601String(),
        ];

        if ($withItems) {
            $data['items'] = $order->items->map(fn ($item) => [
                'name' => $item->name,
                'qty' => (float) $item->qty,
                'unit' => $item->unit->value,
                'unit_price_cents' => $item->unit_price_cents,
                'total_cents' => $item->total_cents,
                'modifiers' => $item->modifiers->map(fn ($modifier) => [
                    'name' => $modifier->name,
                    'price_delta_cents' => $modifier->price_delta_cents,
                ]),
            ]);

            $data['history'] = $order->statusHistory->map(fn ($history) => [
                'status' => $history->status->value,
                'note' => $history->note,
                'at' => $history->created_at?->toIso8601String(),
            ]);
        }

        return $data;
    }
}
