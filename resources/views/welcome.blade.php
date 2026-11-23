@php
    $isArabic = app()->isLocale('ar');
    $otherLocale = $isArabic ? 'en' : 'ar';
    $brand = (string) config('app.name');

    $typeDetails = [
        'perfume' => __('Track bottles, variants and stock.'),
        'grocery' => __('Sell by item, case or weight.'),
        'car_showroom' => __('Track VINs, vehicle costs and buyer inquiries.'),
        'car_rental' => __('Track availability, rental contracts and deposits.'),
        'clothing' => __('Manage stock by size and colour.'),
        'electronics' => __('Track serial numbers and device inventory.'),
        'jewelry' => __('Track weight, gold prices and making charges.'),
        'restaurant' => __('Manage menu items, extras and order preparation.'),
    ];

    $textFields = [
        'business_name' => [__('Store name'), 'text', 'organization', 150],
        'owner_name' => [__('Owner name'), 'text', 'name', 255],
        'email' => [__('Email'), 'email', 'email', 255],
        'password' => [__('Password'), 'password', 'new-password', 72],
        'password_confirmation' => [__('Confirm password'), 'password', 'new-password', 72],
    ];

    // The first invalid field takes focus after a failed submit, which also scrolls the form into view.
    $firstError = collect(['business_type_id', ...array_keys($textFields)])->first(fn (string $field): bool => $errors->has($field));

    $day = [
        ['09:00', 'user-group', __('Manage shifts and payroll'), __('Give each employee their own login and role, review clock-ins, and track wages and commissions.')],
        ['10:30', 'archive-box', __('Know what’s in stock'), __('Receive supplier stock, track what sells, and see which items need reordering.')],
        ['13:15', 'calculator', __('Sell at the counter'), __('Record cash, card or split payments, print receipts and handle returns.')],
        ['19:40', 'shopping-bag', __('Handle pickup and delivery'), __('Accept or decline app orders, set delivery fees by distance, and track payouts in your store wallet.')],
        ['23:00', 'chart-bar', __('See what the shop earned'), __('Compare sales channels and review costs, expenses and net profit. Export reports to Excel or PDF.')],
    ];
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isArabic ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Register your store') }} | {{ $brand }}</title>
    <meta name="description" content="{{ __('Point of sale, app orders, stock, payroll and profit reports for your shop, in one dashboard.') }}">
    <link rel="canonical" href="{{ route('home') }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#09090b" media="(prefers-color-scheme: dark)">
    <script>
        {{-- Applied before paint so a stored dark theme never flashes light. --}}
        (() => {
            let theme = 'system';
            try { theme = localStorage.getItem('theme') ?? 'system'; } catch (e) {}
            if (theme === 'dark' || (theme === 'system' && matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <a href="#main" class="btn btn-primary sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-3 focus:z-50">{{ __('Skip to content') }}</a>

    <header class="site-header" data-site-header>
        <div class="shell flex h-full items-center justify-between gap-3">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-2.5 text-base font-semibold tracking-tight">
                <span class="brand-mark" aria-hidden="true">{{ mb_substr($brand, 0, 1) }}</span>
                <span class="truncate">{{ $brand }}</span>
            </a>

            <nav aria-label="{{ __('Navigation') }}" class="flex items-center gap-1 sm:gap-2">
                <a href="#how-it-works" class="hidden rounded-lg px-3 py-2 text-sm text-muted hover:text-ink md:inline-flex">{{ __('How it works') }}</a>
                <a href="{{ route('locale.switch', $otherLocale) }}" lang="{{ $otherLocale }}" hreflang="{{ $otherLocale }}"
                   class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-medium text-muted hover:bg-sunken hover:text-ink">{{ $isArabic ? 'English' : 'العربية' }}</a>
                <button type="button" class="icon-btn" data-theme-toggle aria-pressed="false" aria-label="{{ __('Dark mode') }}">
                    <x-heroicon-o-moon class="size-5 dark:hidden" aria-hidden="true" />
                    <x-heroicon-o-sun class="hidden size-5 dark:block" aria-hidden="true" />
                </button>
                <a href="{{ route('filament.business.auth.login') }}" class="btn btn-quiet hidden sm:inline-flex">{{ __('Store sign-in') }}</a>
                <a href="#register" class="btn btn-primary">{{ __('Register your store') }}</a>
            </nav>
        </div>
    </header>

    <main id="main" tabindex="-1" class="outline-none">
        @if (session('registration_submitted'))
            <div class="shell pt-6">
                <div role="status" class="flex flex-col gap-3 rounded-xl border border-profit/40 bg-profit/8 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="flex items-start gap-2.5 text-sm text-ink">
                        <x-heroicon-o-check-circle class="mt-px size-5 shrink-0 text-profit" aria-hidden="true" />
                        {{ __('Your store was registered and is awaiting activation. Once activated, sign in using your email and password.') }}
                    </p>
                    <a href="{{ route('filament.business.auth.login') }}" class="btn btn-quiet shrink-0">{{ __('Store sign-in') }}</a>
                </div>
            </div>
        @endif

        {{-- Hero --}}
        <section aria-labelledby="hero-title" class="shell grid grid-cols-1 items-center gap-12 py-14 sm:py-20 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)] lg:gap-16 lg:py-24">
            <div class="max-w-2xl">
                <h1 id="hero-title" class="text-display">{{ __('Run your shop, from checkout to closing time') }}</h1>
                <p class="text-lead mt-6 max-w-xl text-muted">{{ __('Sell at the counter, take pickup and delivery orders, and see what you made after stock costs, wages and expenses.') }}</p>
                <div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <a href="#register" class="btn btn-primary h-12 px-6 text-base">{{ __('Register your store') }}</a>
                    <a href="#how-it-works" class="btn btn-quiet h-12 px-6 text-base">{{ __('See how it works') }}</a>
                </div>
                <p class="mt-6 text-sm text-muted">
                    {{ __('Already registered?') }}
                    <a href="{{ route('filament.business.auth.login') }}" class="font-medium text-ink underline decoration-line-strong underline-offset-4 hover:decoration-ink">{{ __('Store sign-in') }}</a>
                </p>
            </div>

            <figure class="mx-auto w-full max-w-md lg:max-w-none">
                <div class="rounded-2xl border border-line bg-sunken px-6 pt-8 pb-10 sm:px-12">
                    <div class="printer-slot" aria-hidden="true"></div>
                    <div class="receipt-shadow -mt-1.5 px-3">
                        <div class="receipt-feed">
                            <div class="receipt px-5 pt-6 sm:px-7">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-base font-semibold">{{ __('End-of-day report') }}</p>
                                        <p class="figures mt-0.5 text-xs text-paper-muted"><bdi dir="ltr">09:00 – 23:00</bdi></p>
                                    </div>
                                    <span class="figures text-xs text-paper-muted"><bdi dir="ltr">Z-0148</bdi></span>
                                </div>

                                <dl class="mt-5 space-y-3 text-sm">
                                    <div class="flex items-baseline justify-between gap-4">
                                        <dt>{{ __('Counter sales') }} <span class="block text-xs text-paper-muted">{{ trans_choice(':count sale|:count sales', 38) }}</span></dt>
                                        <dd class="figures"><bdi dir="ltr">$1,240.00</bdi></dd>
                                    </div>
                                    <div class="flex items-baseline justify-between gap-4">
                                        <dt>{{ __('App orders') }} <span class="block text-xs text-paper-muted">{{ trans_choice(':count order|:count orders', 12) }}</span></dt>
                                        <dd class="figures"><bdi dir="ltr">$386.50</bdi></dd>
                                    </div>
                                </dl>

                                <div class="receipt-rule my-4"></div>

                                <dl class="space-y-3 text-sm text-paper-muted">
                                    <div class="flex items-baseline justify-between gap-4">
                                        <dt>{{ __('Cost of goods') }}</dt>
                                        <dd class="figures"><bdi dir="ltr">−$702.00</bdi></dd>
                                    </div>
                                    <div class="flex items-baseline justify-between gap-4">
                                        <dt>{{ __('Payroll') }}</dt>
                                        <dd class="figures"><bdi dir="ltr">−$180.00</bdi></dd>
                                    </div>
                                    <div class="flex items-baseline justify-between gap-4">
                                        <dt>{{ __('Expenses') }}</dt>
                                        <dd class="figures"><bdi dir="ltr">−$64.00</bdi></dd>
                                    </div>
                                </dl>

                                <div class="mt-4 border-t-2 border-paper-ink"></div>

                                <dl class="receipt-total -mx-2 mt-1 rounded-md px-2 py-2">
                                    <div class="flex items-baseline justify-between gap-4">
                                        <dt class="text-sm font-semibold">{{ __('Net profit') }}</dt>
                                        <dd class="figures text-3xl font-semibold tracking-tight text-[#047857]"><bdi dir="ltr">$680.50</bdi></dd>
                                    </div>
                                </dl>

                                <div class="mt-5 h-9 bg-[repeating-linear-gradient(90deg,#18181b_0_2px,transparent_2px_4px,#18181b_4px_5px,transparent_5px_8px,#18181b_8px_11px,transparent_11px_13px)] opacity-85" aria-hidden="true"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <figcaption class="mt-3 text-center text-sm text-muted">{{ __('Example: one shop’s day, as the dashboard closes it') }}</figcaption>
            </figure>
        </section>

        {{-- A working day --}}
        <section id="how-it-works" aria-labelledby="day-title" class="border-t border-line py-16 sm:py-24">
            <div class="shell">
                <div class="max-w-2xl">
                    <h2 id="day-title" class="text-title">{{ __('One working day, from sale to profit') }}</h2>
                    <p class="text-lead mt-4 text-muted">{{ __('Counter sales and app orders feed into the same store records, so you can follow each sale through to the end of the day.') }}</p>
                </div>

                <ol class="mt-12 grid grid-cols-1 lg:mt-16 lg:grid-cols-5 lg:gap-8">
                    @foreach ($day as [$time, $icon, $title, $text])
                        <li class="day-step">
                            <time class="figures text-sm font-medium text-muted" datetime="{{ $time }}"><bdi dir="ltr">{{ $time }}</bdi></time>
                            <h3 class="mt-2 flex items-center gap-2 text-base font-semibold">
                                <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5 shrink-0 text-muted" aria-hidden="true" />
                                {{ $title }}
                            </h3>
                            <p class="mt-2 max-w-md text-sm leading-relaxed text-muted">{{ $text }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Registration --}}
        <section id="register" aria-labelledby="register-title" class="border-y border-line bg-sunken py-16 sm:py-24">
            <div class="shell grid grid-cols-1 gap-10 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:gap-16">
                <div class="lg:sticky lg:top-[calc(var(--yl-header-h)+2rem)] lg:self-start">
                    <h2 id="register-title" class="text-title">{{ __('Register your store') }}</h2>
                    <p class="text-lead mt-4 text-muted">{{ __('Choose your business type to set up the right tools for your store.') }}</p>

                    <h3 class="mt-10 text-sm font-semibold">{{ __('How registration works') }}</h3>
                    <ol class="mt-4 space-y-4">
                        @foreach ([
                            __('Choose your store type and enter your login details.'),
                            __('The platform team reviews your request and activates your store.'),
                            __('Sign in with the same email and password.'),
                            __('Add your products and staff, then start selling.'),
                        ] as $step)
                            <li class="flex gap-3.5 text-sm leading-relaxed text-ink-soft">
                                <span class="figures grid size-7 shrink-0 place-items-center rounded-full border border-line-strong/60 bg-raised text-xs font-medium text-ink">{{ $loop->iteration }}</span>
                                <span class="pt-1">{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div class="rounded-2xl border border-line bg-raised p-4 sm:p-8">
                    <form method="POST" action="{{ route('business.registration') }}" class="grid grid-cols-1 gap-6">
                        @csrf

                        <fieldset @error('business_type_id') aria-describedby="business_type_id-error" @enderror>
                            <legend class="field-label">{{ __('Store type') }}</legend>
                            <p class="mb-3 text-sm text-muted">{{ __('A grocery shop tracks stock differently from a car showroom. Your store type determines the tools and fields in your dashboard.') }}</p>

                            @if ($businessTypes->isEmpty())
                                <p role="status" class="rounded-lg border border-line bg-sunken p-4 text-sm text-muted">{{ __('Store registration is currently unavailable.') }}</p>
                            @else
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    @foreach ($businessTypes as $type)
                                        <label class="type-option">
                                            <input type="radio" name="business_type_id" value="{{ $type->id }}" required
                                                @checked((string) old('business_type_id') === (string) $type->id)
                                                @if ($firstError === 'business_type_id' && $loop->first) autofocus @endif
                                                @error('business_type_id') aria-invalid="true" @enderror>
                                            <span class="min-w-0">
                                                <span class="block text-sm font-medium">{{ $type->translatedName() }}</span>
                                                @if ($detail = $typeDetails[$type->key] ?? null)
                                                    <span class="mt-0.5 block text-xs leading-relaxed text-muted">{{ $detail }}</span>
                                                @endif
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif

                            @error('business_type_id')
                                <p class="field-error" id="business_type_id-error"><x-heroicon-m-exclamation-circle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />{{ $message }}</p>
                            @enderror
                        </fieldset>

                        <div class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                            @foreach ($textFields as $field => [$label, $inputType, $autocomplete, $maxLength])
                                <div @class(['sm:col-span-2' => $field === 'email'])>
                                    <label for="{{ $field }}" class="field-label">{{ $label }}</label>
                                    <div class="field-control" @error($field) data-invalid @enderror>
                                        <input id="{{ $field }}" name="{{ $field }}" type="{{ $inputType }}" autocomplete="{{ $autocomplete }}" maxlength="{{ $maxLength }}" required
                                            @if ($inputType === 'password') minlength="8" @else value="{{ old($field) }}" @endif
                                            @if ($inputType === 'email') dir="ltr" @endif
                                            @if ($firstError === $field) autofocus @endif
                                            @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror>
                                        @if ($inputType === 'password')
                                            <button type="button" class="password-toggle group icon-btn me-0.5 size-10 shrink-0" hidden
                                                data-password-toggle aria-controls="{{ $field }}" aria-pressed="false"
                                                aria-label="{{ __('Show password') }}" data-label-show="{{ __('Show password') }}" data-label-hide="{{ __('Hide password') }}">
                                                <x-heroicon-o-eye class="size-5 group-aria-pressed:hidden" aria-hidden="true" />
                                                <x-heroicon-o-eye-slash class="hidden size-5 group-aria-pressed:block" aria-hidden="true" />
                                            </button>
                                        @endif
                                    </div>
                                    @if ($field === 'password')
                                        <p class="mt-1.5 text-xs text-muted">{{ __('At least 8 characters.') }}</p>
                                    @endif
                                    @error($field)
                                        <p class="field-error" id="{{ $field }}-error"><x-heroicon-m-exclamation-circle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>

                        <div>
                            <button type="submit" class="btn btn-primary h-12 w-full text-base disabled:cursor-not-allowed disabled:opacity-50" @disabled($businessTypes->isEmpty())>{{ __('Register your store') }}</button>
                            <p class="mt-3 text-center text-sm text-muted">{{ __('You can sign in after your store is activated.') }}</p>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        {{-- Who signs in --}}
        <section aria-labelledby="access-title" class="py-16 sm:py-24">
            <div class="shell">
                <div class="max-w-2xl">
                    <h2 id="access-title" class="text-title">{{ __('A workspace for each shop') }}</h2>
                    <p class="text-lead mt-4 text-muted">{{ __('Each shop has its own inventory, sales and staff accounts.') }}</p>
                </div>

                <dl class="mt-12 grid grid-cols-1 gap-8 md:grid-cols-3 md:gap-0">
                    @foreach ([
                        ['building-storefront', __('Store owner'), __('Runs the store, reviews its numbers and sets staff permissions.')],
                        ['identification', __('Staff'), __('Signs in to record sales or clock in, depending on the role assigned.')],
                        ['shield-check', __('Platform administrator'), __('Reviews store registrations, activates shops and manages platform commission.')],
                    ] as [$icon, $role, $text])
                        <div class="md:border-line md:px-8 md:not-first:border-s md:first:ps-0 md:last:pe-0">
                            <dt class="flex items-center gap-2.5 text-base font-semibold">
                                <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-5 shrink-0 text-muted" aria-hidden="true" />
                                {{ $role }}
                            </dt>
                            <dd class="mt-2 text-sm leading-relaxed text-muted">{{ $text }}</dd>
                        </div>
                    @endforeach
                </dl>

                <div class="mt-16 flex flex-col gap-6 rounded-2xl bg-ink p-6 text-on-ink sm:p-10 md:flex-row md:items-center md:justify-between">
                    <p class="max-w-xl text-xl leading-snug font-medium text-balance sm:text-2xl">{{ __('Register your shop, then sign in once it’s activated.') }}</p>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="#register" class="btn h-12 bg-on-ink px-6 text-base text-ink hover:opacity-90">{{ __('Register your store') }}</a>
                        <a href="{{ route('filament.business.auth.login') }}" class="btn h-12 border border-on-ink/30 px-6 text-base text-on-ink hover:border-on-ink/70">{{ __('Store sign-in') }}</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-line">
        <div class="shell flex flex-col gap-4 py-8 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
            <span class="flex items-center gap-2 font-medium text-ink">
                <span class="brand-mark size-6 rounded-md text-xs" aria-hidden="true">{{ mb_substr($brand, 0, 1) }}</span>
                {{ $brand }}
            </span>
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                <a href="{{ route('locale.switch', $otherLocale) }}" lang="{{ $otherLocale }}" hreflang="{{ $otherLocale }}" class="hover:text-ink">{{ $isArabic ? 'English' : 'العربية' }}</a>
                <a href="{{ route('filament.business.auth.login') }}" class="hover:text-ink">{{ __('Store sign-in') }}</a>
            </div>
        </div>
    </footer>
</body>
</html>
