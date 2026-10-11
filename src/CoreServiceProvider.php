<?php

declare(strict_types=1);

namespace Inupress\Core;

use Filament\PanelRegistry;
use Illuminate\Support\Facades\Blade;
use Inupress\Contracts\HookRegistry;
use Inupress\Contracts\PluginRepository;
use Inupress\Contracts\ProvidesFilament;
use Inupress\Core\Commands\PluginListCommand;
use Inupress\Core\Commands\PluginMigrateCommand;
use Inupress\Core\Commands\ThemePublishCommand;
use Inupress\Core\Filament\CoreFilamentPlugin;
use Inupress\Core\Filament\PanelPlugins;
use Inupress\Core\Hooks\HookManager;
use Inupress\Core\Migrations\MigrationLinter;
use Inupress\Core\Plugins\ComposerPluginRepository;
use Inupress\Core\Themes\ThemeAssets;
use Inupress\Core\Themes\ThemeManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class CoreServiceProvider extends PackageServiceProvider implements ProvidesFilament
{
    public static string $name = 'inupress';

    public function filamentPlugin(): CoreFilamentPlugin
    {
        return new CoreFilamentPlugin;
    }

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile('inupress')
            ->hasViews()
            ->hasRoute('web')
            ->hasCommands([
                PluginListCommand::class,
                PluginMigrateCommand::class,
                ThemePublishCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(ComposerPluginRepository::class);
        $this->app->alias(ComposerPluginRepository::class, PluginRepository::class);
        $this->app->singleton(MigrationLinter::class);
        $this->app->singleton(HookManager::class);
        $this->app->alias(HookManager::class, HookRegistry::class);
        $this->app->singleton(ThemeManager::class);
        $this->app->singleton(ThemeAssets::class);

        // Panel plugins of installed Inupress plugins are added to the default panel automatically.
        $this->app->afterResolving(
            PanelRegistry::class,
            fn (PanelRegistry $registry) => PanelPlugins::attach($registry, $this->app),
        );
    }

    public function packageBooted(): void
    {
        Blade::directive('inupressStyles', static fn (): string => self::assetDirective(ThemeAssets::STYLES));
        Blade::directive('inupressScripts', static fn (): string => self::assetDirective(ThemeAssets::SCRIPTS));

        // A missing theme asset is copied on the spot, so a fresh install is styled without a build step.
        $this->app->make(ThemeAssets::class)->publishIfMissing();

        // Themes win over plugin and core views; this runs after every provider booted, so the view
        // namespaces of installed plugins exist by now.
        $this->app->booted(fn () => $this->app->make(ThemeManager::class)->applyToViews());
    }

    /**
     * The Blade directive body for @inupressStyles / @inupressScripts: the link and script tags of
     * the active theme chain, in load order.
     */
    private static function assetDirective(string $kind): string
    {
        return '<?php echo app(\\'.ThemeAssets::class.'::class)->tags(\''.$kind.'\'); ?>';
    }
}
