<?php

declare(strict_types=1);

namespace Inupress\Core\Commands;

use Illuminate\Console\Command;
use Inupress\Core\Themes\ThemeAssets;
use Inupress\Core\Themes\ThemeManager;

final class ThemePublishCommand extends Command
{
    protected $signature = 'inupress:theme:publish {--force : Copy every file, even when public/ already has it}';

    protected $description = 'Copy the active theme CSS and JS into public/vendor';

    public function handle(ThemeManager $themes, ThemeAssets $assets): int
    {
        $theme = $themes->active();

        if ($theme === null) {
            $this->components->info('No theme is active (see inupress.theme in config/inupress.php).');

            return self::SUCCESS;
        }

        $copied = $assets->publish((bool) $this->option('force'));

        $this->components->info("Published [$copied] file(s) of theme [{$theme->id()}].");

        return self::SUCCESS;
    }
}
