<?php

declare(strict_types=1);

namespace Inupress\Core\Filament;

use Filament\Pages\Page;
use Inupress\Contracts\PluginManifest;
use Inupress\Contracts\PluginRepository;

final class PluginsPage extends Page
{
    protected static ?string $slug = 'plugins';

    protected static ?string $title = 'Plugins';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected string $view = 'inupress::pages.plugins';

    /**
     * @return list<PluginManifest>
     */
    public function getPlugins(): array
    {
        return array_values(app(PluginRepository::class)->all());
    }
}
