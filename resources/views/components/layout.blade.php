@props([
    'title' => null,
    'description' => null,
])

{{--
    The shell every public page is wrapped in. The active theme overrides this view by shipping
    resources/views/components/layout.blade.php. Deliberately unstyled: it exists so a site without
    a theme still renders. See docs/decisions/0010-themes.md.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $title ?? config('app.name') }}</title>

    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif

    @inupressStyles
    @stack('head')
    @stack('styles')
</head>
<body>
    <main>
        {{ $slot }}
    </main>

    @stack('scripts')
    @inupressScripts
</body>
</html>
