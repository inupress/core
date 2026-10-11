<?php

declare(strict_types=1);

return [
    /*
     * The active theme: a Composer package name ("inupress/theme-default") or the name of a
     * directory under themes_path ("my-child"). An unresolvable theme is logged, not fatal:
     * the core ships a minimal shell so the site still renders. See docs/decisions/0010-themes.md.
     */
    'theme' => env('INUPRESS_THEME', 'inupress/theme-default'),

    /*
     * Where themes that live in the application itself are kept (a child theme, usually).
     */
    'themes_path' => env('INUPRESS_THEMES_PATH', base_path('themes')),
];
