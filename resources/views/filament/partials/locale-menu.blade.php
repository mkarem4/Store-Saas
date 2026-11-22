@php($current = app()->getLocale())
<div class="yl-locale-menu">
    <span>{{ __('Language') }}</span>
    <span class="yl-segmented">
        @foreach (['en' => 'EN', 'ar' => 'العربية'] as $locale => $label)
            <a href="{{ route('locale.switch', $locale) }}" @if ($current === $locale) aria-current="true" @endif lang="{{ $locale }}">{{ $label }}</a>
        @endforeach
    </span>
</div>
