<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['required', 'email', 'max:255', 'unique:customers'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'locale' => ['sometimes', 'in:en,ar'],
        ]);

        $customer = Customer::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'password' => $data['password'],
            'locale' => $data['locale'] ?? 'en',
        ]);

        $token = $customer->createToken('app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'locale' => $customer->locale,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        // D3: e-mail + password; "phone" is still accepted as the login key.
        $data = $request->validate([
            'email' => ['required_without:phone', 'nullable', 'string'],
            'phone' => ['required_without:email', 'nullable', 'string'],
            'password' => ['required', 'string'],
        ]);

        $login = (string) ($data['email'] ?? $data['phone']);
        $field = isset($data['email']) ? 'email' : 'phone';

        $customer = Customer::query()
            ->where('email', $login)
            ->orWhere('phone', $login)
            ->first();

        if ($customer === null || ! Hash::check($data['password'], $customer->password)) {
            throw ValidationException::withMessages([
                $field => __('Invalid credentials.'),
            ]);
        }

        $token = $customer->createToken('app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'locale' => $customer->locale,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('Logged out.')]);
    }

    /**
     * Send a reset link/code to the customer's e-mail (D3). Always answers the
     * same way so e-mail addresses cannot be probed.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        Password::broker('customers')->sendResetLink(['email' => $data['email']]);

        return response()->json(['message' => __('If the e-mail is registered, a reset link is on its way.')]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('customers')->reset($data, function (Customer $customer, string $password): void {
            $customer->forceFill(['password' => $password])->save();
            $customer->tokens()->delete();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => __($status)]);
        }

        return response()->json(['message' => __('Your password has been reset. Sign in again.')]);
    }
}
