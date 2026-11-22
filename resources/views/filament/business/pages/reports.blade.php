<x-filament-panels::page>
    @include('reports._styles')
    {{ $this->form }}
    <div wire:loading.class="opacity-50">
        @include('reports._tables', $this->reportData())
    </div>
</x-filament-panels::page>
