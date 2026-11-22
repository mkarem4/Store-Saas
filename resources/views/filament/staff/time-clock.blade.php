@php
    $open = $this->openEntry();
    $minutes = $this->todayMinutes();
    $timezone = \App\Filament\Business\BusinessPanel::business()?->timezone;
@endphp
<x-filament-panels::page>
    <div x-data="{
            busy: false,
            needsLocation: @js($this->needsLocation()),
            go(method) {
                this.busy = true;
                const done = (lat, lng) => $wire.call(method, lat, lng).finally(() => this.busy = false);
                if (! this.needsLocation || ! navigator.geolocation) { return done(null, null); }
                navigator.geolocation.getCurrentPosition(
                    (p) => done(p.coords.latitude, p.coords.longitude),
                    () => done(null, null),
                    { enableHighAccuracy: true, timeout: 10000 },
                );
            },
         }"
         style="max-width: 420px; margin: 0 auto; text-align: center; padding: 24px; border: 1px solid rgba(127,127,127,.25); border-radius: 16px;">
        <div style="font-size: 13px; opacity: .7;">{{ now($timezone)->translatedFormat('l, j F Y') }}</div>
        <div wire:poll.30s style="font-size: 44px; font-weight: 700; margin: 8px 0;">{{ now($timezone)->format('H:i') }}</div>

        @if ($open)
            <x-filament::badge color="success" size="lg">{{ __('Clocked in since :time', ['time' => $open->clock_in_at->setTimezone($timezone)->format('H:i')]) }}</x-filament::badge>
        @else
            <x-filament::badge color="gray" size="lg">{{ __('Not clocked in') }}</x-filament::badge>
        @endif

        <div style="margin: 20px 0; display: grid;">
            @if ($open)
                <x-filament::button size="xl" color="danger" icon="heroicon-o-arrow-left-start-on-rectangle" x-on:click="go('clockOut')" x-bind:disabled="busy">{{ __('Clock out') }}</x-filament::button>
            @else
                <x-filament::button size="xl" color="success" icon="heroicon-o-arrow-right-end-on-rectangle" x-on:click="go('clockIn')" x-bind:disabled="busy">{{ __('Clock in') }}</x-filament::button>
            @endif
        </div>

        <div style="font-size: 14px;">{{ __('Today: :hours h', ['hours' => number_format($minutes / 60, 2)]) }}</div>
        @if ($this->needsLocation())
            <div style="font-size: 12px; opacity: .6; margin-top: 6px;">{{ __('Your location is checked against the store\'s address.') }}</div>
        @endif
    </div>
</x-filament-panels::page>
