<?php

declare(strict_types=1);

namespace Inupress\Core\Commands;

use Illuminate\Console\Command;
use Inupress\Core\Discovery\PluginCache;

final class ClearCommand extends Command
{
    protected $signature = 'inupress:clear';

    protected $description = 'Remove the plugin discovery cache (rebuilt by inupress:discover or optimize)';

    public function handle(PluginCache $cache): int
    {
        $cache->forget();

        $this->components->info('Plugin cache cleared.');

        return self::SUCCESS;
    }
}
