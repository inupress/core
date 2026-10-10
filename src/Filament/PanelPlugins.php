<?php

declare(strict_types=1);

namespace Inupress\Core\Filament;

use Filament\Contracts\Plugin as FilamentPlugin;
use Filament\Exceptions\NoDefaultPanelSetException;
use Filament\PanelRegistry;
use Illuminate\Contracts\Foundation\Application;
use Inupress\Contracts\ProvidesFilament;
use LogicException;

/**
 * Adds the Filament plugin of every booted plugin provider that implements ProvidesFilament
 * to the default panel, so installing a plugin with Composer is enough.
 */
final class PanelPlugins
{
    public static function attach(PanelRegistry $registry, Application $app): void
    {
        $plugins = [];

        foreach ($app->getProviders(ProvidesFilament::class) as $provider) {
            $plugin = $provider->filamentPlugin();

            if (! $plugin instanceof FilamentPlugin) {
                throw new LogicException(sprintf(
                    '%s::filamentPlugin() must return an instance of %s, %s given.',
                    $provider::class,
                    FilamentPlugin::class,
                    get_debug_type($plugin),
                ));
            }

            if (isset($plugins[$plugin->getId()])) {
                throw new LogicException("Two plugins use the Filament plugin id [{$plugin->getId()}].");
            }

            $plugins[$plugin->getId()] = $plugin;
        }

        if ($plugins === []) {
            return;
        }

        try {
            $panel = $registry->getDefault();
        } catch (NoDefaultPanelSetException) {
            return;
        }

        ksort($plugins);

        foreach ($plugins as $id => $plugin) {
            if (! $panel->hasPlugin($id)) {
                $panel->plugin($plugin);
            }
        }
    }
}
