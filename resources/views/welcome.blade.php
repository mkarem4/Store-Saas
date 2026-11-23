<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Register your store') }} | {{ config('app.name') }}</title>
    <link rel="stylesheet" href="landing.css">
</head>
<body>
    <header class="site-header shell">
        <a href="{{ route('home') }}" class="brand">
            <span class="brand-mark" aria-hidden="true"><x-heroicon-o-building-storefront /></span>
            {{ config('app.name') }}
        </a>
        <nav aria-label="{{ __('Navigation') }}">
            <a class="locale" href="{{ route('locale.switch', app()->isLocale('ar') ? 'en' : 'ar') }}" lang="{{ app()->isLocale('ar') ? 'en' : 'ar' }}">{{ app()->isLocale('ar') ? 'English' : 'العربية' }}</a>
            <a class="sign-in" href="{{ route('filament.business.auth.login') }}">{{ __('Store sign-in') }}</a>
        </nav>
    </header>

    <main class="shell landing">
        <section class="hero" aria-labelledby="hero-title">
            <div class="hero-copy">
                <h1 id="hero-title">{{ __('Run your shop, from checkout to closing time') }}</h1>
                <p>{{ __('Sell at the counter, take pickup and delivery orders, and see what you made after stock costs, wages and expenses.') }}</p>
                <a class="mobile-register" href="#registration">{{ __('Register your store') }}</a>
                <a class="explore-link" href="#how-it-works">{{ __('See how it works') }}</a>
            </div>

            <figure class="preview">
                <figcaption>{{ __('Example sale') }}</figcaption>
                <div class="sale-preview">
                    <div class="sale-details">
                        <div class="sale-status"><x-heroicon-o-check-circle aria-hidden="true" /> {{ __('Sale recorded') }}</div>
                        <p class="sale-total"><bdi>$46.00</bdi></p>
                        <dl class="stock-change">
                            <div><dt>{{ __('Product') }}</dt><dd>{{ __('Cedar perfume') }}</dd></div>
                            <div><dt>{{ __('Units in stock') }}</dt><dd><bdi>18 → 16</bdi></dd></div>
                        </dl>
                    </div>
                    <div class="receipt">
                        <div class="receipt-heading">
                            <x-heroicon-o-receipt-percent aria-hidden="true" />
                            <span>{{ __('Sales receipt') }}</span>
                        </div>
                        <span class="receipt-number"><bdi>#0148</bdi></span>
                        <div class="receipt-line">
                            <span>{{ __('Cedar perfume') }}</span>
                            <span><bdi>2 × $23.00</bdi></span>
                        </div>
                        <div class="receipt-total"><span>{{ __('Total') }}</span><strong><bdi>$46.00</bdi></strong></div>
                        <div class="barcode" aria-hidden="true"></div>
                    </div>
                </div>
            </figure>
        </section>

        <section class="registration" id="registration" aria-labelledby="registration-title">
            <h2 id="registration-title">{{ __('Register your store') }}</h2>
            <p class="form-intro">{{ __('Choose your business type to set up the right tools for your store.') }}</p>

            @if (session('registration_submitted'))
                <p role="status" class="form-status">{{ __('Your store was registered and is awaiting activation. Once activated, sign in using your email and password.') }}</p>
            @endif

            <form method="POST" action="{{ route('business.registration') }}">
                @csrf
                <div class="field field-wide">
                    <label for="business_type_id">{{ __('Store type') }}</label>
                    <select id="business_type_id" name="business_type_id" required
                        @error('business_type_id') aria-invalid="true" aria-describedby="business_type_id-error" @enderror>
                        <option value="">{{ __('Choose a store type') }}</option>
                        @foreach ($businessTypes as $type)
                            <option value="{{ $type->id }}" @selected((string) old('business_type_id') === (string) $type->id)>{{ $type->translatedName() }}</option>
                        @endforeach
                    </select>
                    @error('business_type_id')
                        <p class="field-error" id="business_type_id-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                @foreach ([
                    'business_name' => [__('Store name'), 'text', 'organization', 150],
                    'owner_name' => [__('Owner name'), 'text', 'name', 255],
                    'email' => [__('Email'), 'email', 'email', 255],
                    'password' => [__('Password'), 'password', 'new-password', 72],
                    'password_confirmation' => [__('Confirm password'), 'password', 'new-password', 72],
                ] as $field => [$label, $type, $autocomplete, $maxLength])
                    <div @class(['field', 'field-wide' => $field === 'email'])>
                        <label for="{{ $field }}">{{ $label }}</label>
                        <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" autocomplete="{{ $autocomplete }}" maxlength="{{ $maxLength }}" required
                            @if ($type === 'password') minlength="8" @else value="{{ old($field) }}" @endif
                            @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                        @error($field)
                            <p class="field-error" id="{{ $field }}-error" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <button class="field-wide" type="submit" @disabled($businessTypes->isEmpty())>{{ __('Register your store') }}</button>

                @if ($businessTypes->isEmpty())
                    <p class="form-status field-wide">{{ __('Store registration is currently unavailable.') }}</p>
                @endif
            </form>
            <p class="activation-note">{{ __('You can sign in after your store is activated.') }}</p>
        </section>
    </main>

    <section class="operations" id="how-it-works" aria-labelledby="operations-title">
        <div class="shell operations-layout">
            <div class="section-intro">
                <h2 id="operations-title">{{ __('One working day, from sale to profit') }}</h2>
                <p>{{ __('Counter sales and app orders feed into the same store records, so you can follow each sale through to the end of the day.') }}</p>
            </div>
            <div class="workflows">
                <article class="workflow">
                    <x-heroicon-o-calculator aria-hidden="true" />
                    <div><h3>{{ __('Sell at the counter') }}</h3><p>{{ __('Record cash, card or split payments, print receipts and handle returns.') }}</p></div>
                </article>
                <article class="workflow">
                    <x-heroicon-o-shopping-bag aria-hidden="true" />
                    <div><h3>{{ __('Handle pickup and delivery') }}</h3><p>{{ __('Accept or decline app orders, set delivery fees by distance, and track payouts in your store wallet.') }}</p></div>
                </article>
                <article class="workflow">
                    <x-heroicon-o-archive-box aria-hidden="true" />
                    <div><h3>{{ __('Know what’s in stock') }}</h3><p>{{ __('Receive supplier stock, track what sells, and see which items need reordering.') }}</p></div>
                </article>
                <article class="workflow">
                    <x-heroicon-o-user-group aria-hidden="true" />
                    <div><h3>{{ __('Manage shifts and payroll') }}</h3><p>{{ __('Give each employee their own login and role, review clock-ins, and track wages and commissions.') }}</p></div>
                </article>
                <article class="workflow">
                    <x-heroicon-o-chart-bar aria-hidden="true" />
                    <div><h3>{{ __('See what the shop earned') }}</h3><p>{{ __('Compare sales channels and review costs, expenses and net profit. Export reports to Excel or PDF.') }}</p></div>
                </article>
            </div>
        </div>
    </section>

    @if ($businessTypes->isNotEmpty())
        <section class="shell store-types" aria-labelledby="types-title">
            <div class="section-intro">
                <h2 id="types-title">{{ __('Tools that fit your type of shop') }}</h2>
                <p>{{ __('A grocery shop tracks stock differently from a car showroom. Your store type determines the tools and fields in your dashboard.') }}</p>
            </div>
            <ul class="type-list">
                @foreach ($businessTypes as $businessType)
                    <li>
                        <h3>{{ $businessType->translatedName() }}</h3>
                        @if ($detail = match ($businessType->key) {
                            'perfume' => __('Track bottles, variants and stock.'),
                            'grocery' => __('Sell by item, case or weight.'),
                            'car_showroom' => __('Track VINs, vehicle costs and buyer inquiries.'),
                            'car_rental' => __('Track availability, rental contracts and deposits.'),
                            'clothing' => __('Manage stock by size and colour.'),
                            'electronics' => __('Track serial numbers and device inventory.'),
                            'jewelry' => __('Track weight, gold prices and making charges.'),
                            'restaurant' => __('Manage menu items, extras and order preparation.'),
                            default => null,
                        })
                            <p>{{ $detail }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
    <section class="shell access-section" aria-labelledby="access-title">
        <div class="section-intro">
            <h2 id="access-title">{{ __('A workspace for each shop') }}</h2>
            <p>{{ __('Each shop has its own inventory, sales and staff accounts.') }}</p>
        </div>
        <dl class="access-list">
            <div><dt>{{ __('Platform administrator') }}</dt><dd>{{ __('Reviews store registrations, activates shops and manages platform commission.') }}</dd></div>
            <div><dt>{{ __('Store owner') }}</dt><dd>{{ __('Runs the store, reviews its numbers and sets staff permissions.') }}</dd></div>
            <div><dt>{{ __('Staff') }}</dt><dd>{{ __('Signs in to record sales or clock in, depending on the role assigned.') }}</dd></div>
        </dl>
        <div class="bottom-cta">
            <p>{{ __('Register your shop, then sign in once it’s activated.') }}</p>
            <a class="primary-link" href="#registration">{{ __('Register your store') }}</a>
        </div>
    </section>
    <footer class="shell site-footer">
        <span>{{ config('app.name') }}</span>
        <a href="{{ route('filament.business.auth.login') }}">{{ __('Store sign-in') }}</a>
    </footer>
</body>
</html>
