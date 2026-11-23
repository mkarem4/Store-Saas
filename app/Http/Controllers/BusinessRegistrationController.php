<?php

namespace App\Http\Controllers;

use App\Enums\BusinessStatus;
use App\Models\BusinessType;
use App\Models\User;
use App\Services\BusinessProvisioner;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BusinessRegistrationController extends Controller
{
    public function show(): View
    {
        return view('welcome', [
            'businessTypes' => BusinessType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function __invoke(Request $request, BusinessProvisioner $provisioner): RedirectResponse
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower($request->input('email'))]);
        }

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:150'],
            'business_type_id' => ['required', 'integer', Rule::exists(BusinessType::class, 'id')->where('is_active', true)],
            'owner_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        $type = BusinessType::query()->where('is_active', true)->findOrFail((int) $data['business_type_id']);

        try {
            $provisioner->provision(
                $type,
                [
                    'name' => ['en' => $data['business_name'], 'ar' => $data['business_name']],
                    'status' => BusinessStatus::Inactive,
                ],
                $data['owner_name'],
                $data['email'],
                $data['password'],
                ownerLocale: app()->getLocale(),
            );
        } catch (UniqueConstraintViolationException $exception) {
            if (User::query()->where('email', $data['email'])->exists()) {
                throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => __('Email')])]);
            }

            throw $exception;
        }

        return to_route('home')->with('registration_submitted', true);
    }
}
