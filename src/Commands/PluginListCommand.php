<?php

declare(strict_types=1);

namespace Inupress\Core\Commands;

use Illuminate\Console\Command;
use Inupress\Contracts\PluginRepository;

final class PluginListCommand extends Command
{
    protected $signature = 'inupress:plugin:list';

    protected $description = 'List installed Inupress plugins (Composer packages of type inupress-plugin)';

    public function handle(PluginRepository $plugins): int
    {
        $rows = array_map(
            fn ($p) => [$p->id, $p->version],
            array_values($plugins->all()),
        );

        if ($rows === []) {
            $this->components->info('No plugins installed. Install one with: composer require vendor/plugin');

            return self::SUCCESS;
        }

        $this->table(['Plugin', 'Version'], $rows);

        return self::SUCCESS;
    }
}
