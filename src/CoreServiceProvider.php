<?php

declare(strict_types=1);

namespace Inupress\Core;

use Inupress\Contracts\HookRegistry;
use Inupress\Contracts\PluginRepository;
use Inupress\Core\Commands\ClearCommand;
use Inupress\Core\Commands\DiscoverCommand;
use Inupress\Core\Commands\PluginDisableCommand;
use Inupress\Core\Commands\PluginEnableCommand;
use Inupress\Core\Commands\PluginListCommand;
use Inupress\Core\Commands\PluginMigrateCommand;
use Inupress\Core\Discovery\ManifestScanner;
use Inupress\Core\Discovery\PluginCache;
use Inupress\Core\Hooks\HookManager;
use Inupress\Core\Migrations\MigrationLinter;
use Inupress\Core\Resolver\DependencyResolver;
use Inupress\Core\State\PluginState;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class CoreServiceProvider extends PackageServiceProvider
{
    public static string $name = 'inupress';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasCommands([
                ClearCommand::class,
                DiscoverCommand::class,
                PluginListCommand::class,
                PluginMigrateCommand::class,
                PluginEnableCommand::class,
                PluginDisableCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ManifestScanner::class, fn ($app) => new ManifestScanner($app->basePath('vendor')));
        $this->app->singleton(PluginCache::class, fn ($app) => new PluginCache($app->bootstrapPath('cache/inupress-plugins.php')));
        $this->app->singleton(PluginState::class, fn ($app) => new PluginState($app->storagePath('inupress/state.json')));
        $this->app->singleton(DependencyResolver::class);
        $this->app->singleton(MigrationLinter::class);
        $this->app->singleton(PluginManager::class);
        $this->app->alias(PluginManager::class, PluginRepository::class);
        $this->app->singleton(HookManager::class);
        $this->app->alias(HookManager::class, HookRegistry::class);

        // Core registers before app providers, so enabled plugins register right after it.
        $this->app->make(PluginManager::class)->registerAll();
    }

    public function packageBooted(): void
    {
        // optimize rebuilds the plugin cache, optimize:clear removes it (same mechanism as Filament).
        if ($this->app->runningInConsole()) {
            $this->optimizes(optimize: 'inupress:discover', clear: 'inupress:clear', key: 'inupress');
        }

        // Plain "artisan migrate" also sees migrations of enabled plugins (and only of those).
        $plugins = $this->app->make(PluginManager::class);

        foreach (array_keys($plugins->enabled()) as $id) {
            $dir = $plugins->migrationPath($id);

            if ($dir !== null) {
                $this->loadMigrationsFrom($dir);
            }
        }
    }
}
