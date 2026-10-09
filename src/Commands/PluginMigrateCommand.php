<?php

declare(strict_types=1);

namespace Inupress\Core\Commands;

use Illuminate\Console\Command;
use Inupress\Core\Migrations\MigrationLinter;
use Inupress\Core\PluginManager;

final class PluginMigrateCommand extends Command
{
    protected $signature = 'inupress:plugin:migrate
        {plugin? : Plugin id; all enabled plugins when omitted}
        {--rollback : Roll back the last batch of the plugin migrations}
        {--force : Run in production without confirmation}';

    protected $description = 'Run (or roll back) migrations of enabled plugins, after linting them';

    public function handle(PluginManager $plugins, MigrationLinter $linter): int
    {
        $ids = $this->argument('plugin') !== null ? [(string) $this->argument('plugin')] : array_keys($plugins->enabled());
        $targets = [];
        $failed = false;

        foreach ($ids as $id) {
            if (! $plugins->has($id)) {
                $this->components->error("Plugin [$id] is not enabled.");

                return self::FAILURE;
            }

            $dir = $plugins->migrationPath($id);

            if ($dir === null) {
                $this->components->twoColumnDetail($id, 'no migrations');

                continue;
            }

            // Lint everything first: nothing runs if any plugin is dirty.
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
