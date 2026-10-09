<?php

declare(strict_types=1);

namespace Inupress\Core\Commands;

use Illuminate\Console\Command;
use Inupress\Contracts\HookRegistry;
use Inupress\Contracts\Hooks;
use Inupress\Core\PluginManager;

abstract class ToggleCommand extends Command
{
    abstract protected function enabling(): bool;

    public function handle(PluginManager $plugins, HookRegistry $hooks): int
    {
        $id = (string) $this->argument('plugin');

        if (! isset($plugins->discovered()->plugins[$id])) {
            $this->components->error("Plugin [$id] is not installed. Run composer require and inupress:discover.");

            return self::FAILURE;
        }

        $this->enabling() ? $plugins->state()->enable($id) : $plugins->state()->disable($id);

        // Routes, config and views are cached per set of providers, so they must be rebuilt.
        foreach (['route:clear', 'config:clear', 'view:clear'] as $command) {
            $this->callSilently($command);
        }

        $hooks->doAction($this->enabling() ? Hooks::PLUGIN_ENABLED : Hooks::PLUGIN_DISABLED, $id);

        $this->components->info("Plugin [$id] ".($this->enabling() ? 'enabled' : 'disabled').'. Caches cleared; run optimize again in production.');

        return self::SUCCESS;
    }
}
