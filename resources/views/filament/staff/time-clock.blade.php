@php
    $open = $this->openEntry();
    $minutes = $this->todayMinutes();
    $timezone = \App\Filament\Business\BusinessPanel::business()?->timezone;
@endphp

<x-filament-panels::page>
    <div
        class="mx-auto w-full max-w-md"
        x-data="{
            busy: false,
            needsLocation: @js($this->needsLocation()),
            go(method) {
                this.busy = true
                const done = (lat, lng) => $wire.call(method, lat, lng).finally(() => (this.busy = false))
                if (! this.needsLocation || ! navigator.geolocation) {
                    return done(null, null)
                }
                navigator.geolocation.getCurrentPosition(
                    (p) => done(p.coords.latitude, p.coords.longitude),
                    () => done(null, null),
                    { enableHighAccuracy: true, timeout: 10000 },
                )
            },
        }"
    >
        <x-filament::section>
            <div class="flex flex-col items-center gap-5 py-4 text-center">
                <div wire:poll.30s>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ now($timezone)->translatedFormat('l j F') }}</p>
                    <p class="yl-figure mt-1 text-5xl font-semibold text-gray-950 dark:text-white">{{ now($timezone)->format('H:i') }}</p>
                </div>

                @if ($open)
                    <x-filament::badge color="success" icon="heroicon-m-check-circle" size="lg">
                        {{ __('Clocked in since :time', ['time' => $open->clock_in_at->setTimezone($timezone)->format('H:i')]) }}
                    </x-filament::badge>
                @else
                    <x-filament::badge color="gray" size="lg">{{ __('Not clocked in') }}</x-filament::badge>
                @endif

                <div class="grid w-full">
                    @if ($open)
                        <x-filament::button size="xl" color="gray" icon="heroicon-o-arrow-left-start-on-rectangle" x-on:click="go('clockOut')" x-bind:disabled="busy">
                            {{ __('Clock out') }}
                        </x-filament::button>
                    @else
                        <x-filament::button size="xl" icon="heroicon-o-arrow-right-end-on-rectangle" x-on:click="go('clockIn')" x-bind:disabled="busy">
                            {{ __('Clock in') }}
                        </x-filament::button>
                    @endif
                </div>

                <div class="yl-tear" aria-hidden="true"></div>

                <dl class="yl-figure flex w-full justify-between text-sm">
                    <dt class="text-gray-500 dark:text-gray-400">{{ __('Worked today') }}</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ intdiv($minutes, 60) }}:{{ str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) }} {{ __('h') }}</dd>
                </dl>

                @if ($this->needsLocation())
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('Your location is checked against the store\'s address.') }}</p>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
