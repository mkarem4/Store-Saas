@if (session()->has(\App\Http\Controllers\ImpersonationController::SESSION_KEY))
    <div style="background: var(--warning-500, #f59e0b); color: #111827;" class="px-4 py-2 text-sm font-medium flex items-center justify-center gap-3">
        <span>{{ __('You are signed in as :name (:store).', ['name' => auth()->user()?->name, 'store' => auth()->user()?->business?->translatedName()]) }}</span>
        <a href="{{ route('impersonate.leave') }}" class="underline font-semibold">{{ __('Return to admin') }}</a>
    </div>
@endif
