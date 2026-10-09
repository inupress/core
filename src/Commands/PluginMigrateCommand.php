<?php

declare(strict_types=1);

namespace Inupress\Core\Commands;

use Illuminate\Console\Command;
use Inupress\Contracts\PluginRepository;
use Inupress\Core\Migrations\MigrationLinter;

final class PluginMigrateCommand extends Command
{
    protected $signature = 'inupress:plugin:migrate
        {plugin? : Plugin id; all installed plugins when omitted}
        {--rollback : Roll back the last batch of the plugin migrations}
        {--force : Run in production without confirmation}';

    protected $description = 'Lint and run (or roll back) plugin migrations; nothing runs if a plugin breaks the rules';

    public function handle(PluginRepository $plugins, MigrationLinter $linter): int
    {
        $ids = $this->argument('plugin') !== null ? [(string) $this->argument('plugin')] : array_keys($plugins->all());
        $targets = [];
        $failed = false;

        foreach ($ids as $id) {
            $plugin = $plugins->get($id);

            if ($plugin === null) {
                $this->components->error("Plugin [$id] is not installed.");

                return self::FAILURE;
            }

            $dir = $plugin->path.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';

            if (! is_dir($dir)) {
                continue;
            }

            foreach ($linter->lint($dir, MigrationLinter::prefixFor($id)) as $problem) {
                $this->components->error("[$id] $problem");
                $failed = true;
            }

            $targets[$id] = $dir;
        }

        if ($failed) {
            $this->components->error('Nothing was migrated. Fix the problems above.');

            return self::FAILURE;
        }

        foreach ($targets as $id => $dir) {
            $this->components->info(($this->option('rollback') ? 'Rolling back ' : 'Migrating ')."[$id]");

            $code = $this->call($this->option('rollback') ? 'migrate:rollback' : 'migrate', [
                '--path' => $dir,
                '--realpath' => true,
                '--force' => (bool) $this->option('force'),
            ]);

            if ($code !== self::SUCCESS) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
