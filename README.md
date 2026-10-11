# inupress/core

The core of the Inupress CMS: the plugin repository, hooks, migration lint and the admin panel wiring.

This package is developed in the `inupress/framework` monorepo (`packages/core`). Report issues and send changes there.

## What it does

- **Finds plugins.** `Inupress\Contracts\PluginRepository` is implemented on top of Composer: a plugin is any installed package of `"type": "inupress-plugin"`. There is no cache, no state file and no enable/disable switch.
- **Runs hooks.** `Inupress\Contracts\HookRegistry` is implemented by `HookManager` (priorities, actions, filters).
- **Attaches panel plugins.** A plugin provider that implements `Inupress\Contracts\ProvidesFilament` gets its Filament plugin added to the default panel automatically.
- **Lints plugin migrations.** `php artisan inupress:plugin:migrate` refuses to run when a migration has no `down()` or touches a table outside the plugin prefix.
- **Admin page.** A **Plugins** page (`/admin/plugins`) lists installed plugins.

## Commands

| Command | Purpose |
|---|---|
| `inupress:plugin:list` | List installed plugins |
| `inupress:plugin:migrate [plugin] [--rollback]` | Lint, then run (or roll back) plugin migrations |

## Requirements

PHP 8.3 or newer, Laravel 12 or 13, Filament 5, Composer 2. Tested on PHP 8.4 and Laravel 13 only.

The public API lives in [`inupress/contracts`](https://packagist.org/packages/inupress/contracts). Plugins must not depend on anything else from this package.

## License

MIT
