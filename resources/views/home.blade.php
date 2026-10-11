<x-inupress::layout :title="config('app.name')">
    <h1>{{ config('app.name') }}</h1>

    <p>Inupress is installed and running.</p>

    <p>
        No theme is installed yet, so this page comes from the core's fallback layout.
        Install one with:
    </p>

    <p><code>composer require inupress/theme-default</code></p>

    <p><a href="{{ url('admin') }}">Admin panel</a></p>
</x-inupress::layout>
