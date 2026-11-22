<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>body { font-family: 'DejaVu Sans'; font-size: 11px; } .yr-grid, .yr-cols { display: block; } .yr-card, .yr-box { display: inline-block; width: 30%; margin: 0 1% 8px 0; vertical-align: top; } .yr-box { width: 46%; }</style>
    @include('reports._styles')
</head>
<body>
    <h2>{{ $business->translatedName() }} — {{ __('Report') }} {{ $from->toDateString() }} → {{ $until->toDateString() }}</h2>
    @include('reports._tables')
</body>
</html>
