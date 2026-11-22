@php($current = app()->getLocale())
<div class="yl-locale flex items-center gap-1 text-sm">
    @foreach (['en' => 'EN', 'ar' => 'ع'] as $locale => $label)
        <a href="{{ route('locale.switch', $locale) }}"
           @class(['px-2 py-1 rounded-md', 'yl-locale-active' => $current === $locale, 'font-semibold bg-gray-100 dark:bg-white/10' => $current === $locale, 'text-gray-500' => $current !== $locale])>{{ $label }}</a>
    @endforeach
</div>
