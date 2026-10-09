<?php

declare(strict_types=1);

namespace Inupress\Core\Commands;

use Illuminate\Console\Command;
use Inupress\Core\PluginManager;

final class PluginListCommand extends Command
{
    protected $signature = 'inupress:plugin:list';

    protected $description = 'List discovered plugins and their status';

    public function handle(PluginManager $plugins): int
    {
        $rows = [];

        foreach ($plugins->discovered()->plugins as $id => $entry) {
            $status = match (true) {
                $plugins->has($id) => 'enabled',
                isset($plugins->skipped()[$id]) => 'skipped: '.$plugins->skipped()[$id],
                default => 'disabled',
            };

            $rows[] = [$id, $entry['version'], $status];
        }

        if ($rows === []) {
            $this->components->info('No plugins discovered. Run inupress:discover after composer require.');

            return self::SUCCESS;
        }

        $this->table(['Plugin', 'Version', 'Status'], $rows);

        return self::SUCCESS;
    }
}
