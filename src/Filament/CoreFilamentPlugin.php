<?php

declare(strict_types=1);

namespace Inupress\Core\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class CoreFilamentPlugin implements Plugin
{
    public function getId(): string
    {
        return 'inupress';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([PluginsPage::class]);
    }

    public function boot(Panel $panel): void {}
}
