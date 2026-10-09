<?php

declare(strict_types=1);

namespace Inupress\Core\Commands;

use Illuminate\Console\Command;
use Inupress\Core\PluginManager;

final class DiscoverCommand extends Command
{
    protected $signature = 'inupress:discover';

    protected $description = 'Scan installed packages for Inupress plugins and rebuild the plugin cache';

    public function handle(PluginManager $plugins): int
    {
        $result = $plugins->discover();

        foreach ($result->problems as $problem) {
            $this->components->warn($problem);
        }

        $this->components->info(count($result->plugins).' plugin(s) discovered.');

        return self::SUCCESS;
    }
}
