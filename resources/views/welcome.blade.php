<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Register your store') }} | {{ config('app.name') }}</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-stone-100 text-stone-900 antialiased">
    <header class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-6 py-6">
        <a href="{{ route('home') }}" class="text-xl font-bold">{{ config('app.name') }}</a>
        <nav class="flex items-center gap-5 text-sm font-semibold">
            <a href="{{ route('locale.switch', app()->isLocale('ar') ? 'en' : 'ar') }}">{{ app()->isLocale('ar') ? 'English' : 'العربية' }}</a>
            <a href="{{ route('filament.business.auth.login') }}">{{ __('Store sign-in') }}</a>
        </nav>
    </header>

    <main class="mx-auto grid max-w-6xl overflow-hidden bg-white lg:my-8 lg:grid-cols-2 lg:rounded-3xl">
        <section class="flex flex-col justify-center bg-emerald-950 px-8 py-14 text-white sm:px-14 lg:py-24">
            <h1 class="max-w-lg text-4xl leading-tight font-bold sm:text-6xl">{{ __('Run your store from one place') }}</h1>
            <p class="mt-6 max-w-md text-lg text-emerald-100">{{ __('Manage sales, inventory and your team from your store dashboard.') }}</p>
        </section>

        <section class="px-8 py-12 sm:px-14" id="registration">
            <h2 class="text-2xl font-bold">{{ __('Register your store') }}</h2>
            @if (session('registration_submitted'))
                <p role="status" class="mt-5 rounded-lg bg-emerald-50 p-4 text-emerald-900">{{ __('Your store was registered and is awaiting activation. Once activated, sign in using your email and password.') }}</p>
            @endif

            <form method="POST" action="{{ route('business.registration') }}" class="mt-6 space-y-4">
                @csrf
                <div>
                    <label for="business_type_id" class="mb-1 block text-sm font-semibold">{{ __('Store type') }}</label>
                    <select id="business_type_id" name="business_type_id" required class="w-full rounded-lg border border-stone-400 bg-white px-3 py-2.5 focus-visible:outline-2 focus-visible:outline-emerald-700">
                        <option value="">{{ __('Choose a store type') }}</option>
                        @foreach ($businessTypes as $type)
                            <option value="{{ $type->id }}" @selected((string) old('business_type_id') === (string) $type->id)>{{ $type->translatedName() }}</option>
                        @endforeach
                    </select>
                    @error('business_type_id')
                        <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                @foreach ([
                    'business_name' => [__('Store name'), 'text', 'organization', 150],
                    'owner_name' => [__('Owner name'), 'text', 'name', 255],
                    'email' => [__('Email'), 'email', 'email', 255],
                    'password' => [__('Password'), 'password', 'new-password', 72],
                    'password_confirmation' => [__('Confirm password'), 'password', 'new-password', 72],
                ] as $field => [$label, $type, $autocomplete, $maxLength])
                    <div>
                        <label for="{{ $field }}" class="mb-1 block text-sm font-semibold">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" maxlength="{{ $maxLength }}" required
                            @if ($type === 'password') minlength="8" @else value="{{ old($field) }}" @endif
                            class="w-full rounded-lg border border-stone-400 px-3 py-2.5 focus-visible:outline-2 focus-visible:outline-emerald-700">
                        @error($field)
                            <p class="mt-1 text-sm text-red-700" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <button type="submit" @disabled($businessTypes->isEmpty()) class="w-full rounded-lg bg-emerald-900 px-4 py-3 font-semibold text-white hover:bg-emerald-800 disabled:opacity-50">{{ __('Register your store') }}</button>

                @if ($businessTypes->isEmpty())
                    <p class="text-sm text-stone-600">{{ __('Store registration is currently unavailable.') }}</p>
                @endif
            </form>
        </section>
    </main>
</body>
</html>
