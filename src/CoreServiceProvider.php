<?php

declare(strict_types=1);

namespace Inupress\Core;

use Inupress\Core\Discovery\ManifestScanner;
use Inupress\Core\Discovery\PluginCache;
use Inupress\Core\Resolver\DependencyResolver;
use Inupress\Core\State\PluginState;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class CoreServiceProvider extends PackageServiceProvider
{
    public static string $name = 'inupress';

    public function configurePackage(Package $package): void
    {
        $package->name(self::$name);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ManifestScanner::class, fn ($app) => new ManifestScanner($app->basePath('vendor')));
        $this->app->singleton(PluginCache::class, fn ($app) => new PluginCache($app->bootstrapPath('cache/inupress-plugins.php')));
        $this->app->singleton(PluginState::class, fn ($app) => new PluginState($app->storagePath('inupress/state.json')));
        $this->app->singleton(DependencyResolver::class);
    }
}
