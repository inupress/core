<?php

declare(strict_types=1);

namespace Inupress\Core;

use Filament\PanelRegistry;
use Inupress\Contracts\HookRegistry;
use Inupress\Contracts\PluginRepository;
use Inupress\Core\Commands\PluginListCommand;
use Inupress\Core\Commands\PluginMigrateCommand;
use Inupress\Core\Filament\PanelPlugins;
use Inupress\Core\Hooks\HookManager;
use Inupress\Core\Migrations\MigrationLinter;
use Inupress\Core\Plugins\ComposerPluginRepository;
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
                PluginListCommand::class,
                PluginMigrateCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ComposerPluginRepository::class);
        $this->app->alias(ComposerPluginRepository::class, PluginRepository::class);
        $this->app->singleton(MigrationLinter::class);
        $this->app->singleton(HookManager::class);
        $this->app->alias(HookManager::class, HookRegistry::class);

        // Panel plugins of installed Inupress plugins are added to the default panel automatically.
        $this->app->afterResolving(
            PanelRegistry::class,
            fn (PanelRegistry $registry) => PanelPlugins::attach($registry, $this->app),
        );
    }
}
