<?php

declare(strict_types=1);

namespace Inupress\Core\Commands;

final class PluginEnableCommand extends ToggleCommand
{
    protected $signature = 'inupress:plugin:enable {plugin : Plugin id, e.g. acme/blog}';

    protected $description = 'Enable a plugin';

    protected function enabling(): bool
    {
        return true;
    }
}
