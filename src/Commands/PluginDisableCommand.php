<?php

declare(strict_types=1);

namespace Inupress\Core\Commands;

final class PluginDisableCommand extends ToggleCommand
{
    protected $signature = 'inupress:plugin:disable {plugin : Plugin id, e.g. acme/blog}';

    protected $description = 'Disable a plugin (data is kept)';

    protected function enabling(): bool
    {
        return false;
    }
}
