@php($name = (string) filament()->getBrandName())
<span class="flex min-w-0 items-center gap-2.5 text-base font-semibold tracking-tight text-gray-950 dark:text-white">
    <span class="yl-brand-mark" aria-hidden="true">{{ mb_substr($name, 0, 1) }}</span>
    <span class="truncate">{{ $name }}</span>
</span>
