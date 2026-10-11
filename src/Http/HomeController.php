<?php

declare(strict_types=1);

namespace Inupress\Core\Http;

use Illuminate\Contracts\View\View;

/**
 * The home page. It renders the view the active theme provides (`inupress::home`), or the core's
 * fallback when no theme is installed.
 */
final class HomeController
{
    public function __invoke(): View
    {
        return view('inupress::home');
    }
}
