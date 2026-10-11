<?php

declare(strict_types=1);

namespace Inupress\Core\Themes;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\Log;
use Illuminate\View\Factory as ViewFactory;
use Inupress\Contracts\PluginRepository;
use Inupress\Contracts\Theme;
use Inupress\Core\CoreServiceProvider;

/**
 * Resolves the active theme and the themes it inherits from.
 *
 * The active theme is configuration (`inupress.theme`): a Composer package name or a directory
 * under `inupress.themes_path`. A theme that cannot be resolved is logged, not thrown — a site
 * with a broken theme renders unstyled instead of dying. See docs/decisions/0010-themes.md.
 */
final class ThemeManager
{
    /** The Composer package type of a theme package. */
    public const TYPE = 'inupress-theme';

    /** The depth guard against a manifest cycle. */
    private const MAX_DEPTH = 16;

    /** @var list<Theme>|null */
    private ?array $chain = null;

    public function __construct(
        private readonly PluginRepository $plugins,
        private readonly ViewFactory $view,
    ) {}

    /**
     * The configured theme — the most specific one in the chain — or null when there is none.
     */
    public function active(): ?Theme
    {
        $chain = $this->chain();

        return $chain === [] ? null : $chain[array_key_last($chain)];
    }

    /**
     * The whole chain, base theme first: every theme in the list is overridden by the next one.
     *
     * @return list<Theme>
     */
    public function chain(): array
    {
        if ($this->chain !== null) {
            return $this->chain;
        }

        $id = config('inupress.theme');

        if (! is_string($id) || $id === '') {
            return $this->chain = [];
        }

        /** @var array<string, true> $seen */
        $seen = [];
        /** @var list<Theme> $found flavour order: the configured theme first, its parents after it */
        $found = [];

        while ($id !== null) {
            if (isset($seen[$id]) || count($found) >= self::MAX_DEPTH) {
                Log::warning("Inupress: theme inheritance stops at [$id] (already visited or too deep).");

                break;
            }

            $seen[$id] = true;
            $theme = $this->resolve($id);

            if ($theme === null) {
                Log::warning("Inupress: theme [$id] is not installed; the site falls back to the core layout.");

                break;
            }

            $seen[$theme->id()] = true;
            $found[] = $theme;
            $id = $theme->parent();
        }

        return $this->chain = array_reverse($found);
    }

    /**
     * Lets the theme win over plugin and core views: every theme in the chain is prepended to every
     * public view namespace, the child last so that it lands in front. Call this after all service
     * providers booted, otherwise plugin view namespaces do not exist yet.
     */
    public function applyToViews(): void
    {
        $finder = $this->view->getFinder();

        foreach ($this->viewNamespaces() as $namespace) {
            foreach ($this->chain() as $theme) {
                $path = $theme->views();

                if (is_dir($path)) {
                    $finder->prependNamespace($namespace, $path);
                }
            }
        }
    }

    /**
     * The view namespaces a theme may override: the core's own, plus one per installed plugin.
     *
     * @return list<string>
     */
    public function viewNamespaces(): array
    {
        $namespaces = [CoreServiceProvider::$name];

        foreach (array_keys($this->plugins->all()) as $id) {
            $namespaces[] = self::viewNamespace($id);
        }

        return array_values(array_unique($namespaces));
    }

    /**
     * The view namespace of a plugin: its package name with the slash replaced by a dash.
     * AGENTS.md requires every plugin provider to declare that name in `public static string $name`,
     * and an architecture test checks it.
     */
    public static function viewNamespace(string $pluginId): string
    {
        return str_replace('/', '-', $pluginId);
    }

    private function resolve(string $id): ?Theme
    {
        $root = str_contains($id, '/') ? $this->packagePath($id) : $this->directoryPath($id);

        if ($root === null || ! is_dir($root)) {
            return null;
        }

        return ManifestTheme::fromDirectory($id, $root);
    }

    private function packagePath(string $package): ?string
    {
        if (! InstalledVersions::isInstalled($package)) {
            return null;
        }

        $path = InstalledVersions::getInstallPath($package);

        return $path === null ? null : (realpath($path) ?: $path);
    }

    private function directoryPath(string $id): ?string
    {
        $base = config('inupress.themes_path');

        if (! is_string($base) || $base === '') {
            return null;
        }

        return rtrim($base, '/\\').DIRECTORY_SEPARATOR.$id;
    }
}
