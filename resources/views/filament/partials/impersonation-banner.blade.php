@if (session()->has(\App\Http\Controllers\ImpersonationController::SESSION_KEY))
    {{-- Signed in as a store owner from the platform: always visible, one clear way back. --}}
    <div role="status" class="yl-impersonation">
        <x-filament::icon icon="heroicon-o-eye" class="size-5 shrink-0" />
        <span>{{ __('You are signed in as :name (:store).', ['name' => auth()->user()?->name, 'store' => auth()->user()?->business?->translatedName()]) }}</span>
        <x-filament::button tag="a" size="sm" color="gray" :href="route('impersonate.leave')" icon="heroicon-m-arrow-uturn-left">
            {{ __('Return to admin') }}
        </x-filament::button>
    </div>
@endif
