<?php

declare(strict_types=1);

namespace Inupress\Core\Plugins;

use Composer\InstalledVersions;
use Inupress\Contracts\PluginManifest;
use Inupress\Contracts\PluginRepository;

/**
 * Plugins are Composer packages of type "inupress-plugin". Composer is the only source of truth:
 * installed means active, `composer remove` means gone. Laravel boots their service providers
 * (extra.laravel.providers) through package:discover, exactly like any Filament plugin.
 */
final class ComposerPluginRepository implements PluginRepository
{
    public const TYPE = 'inupress-plugin';

    /** @var array<string, PluginManifest>|null */
    private ?array $plugins = null;

    /**
     * @return array<string, PluginManifest>
     */
    public function all(): array
    {
        if ($this->plugins !== null) {
            return $this->plugins;
        }

        $plugins = [];

        foreach (InstalledVersions::getInstalledPackagesByType(self::TYPE) as $name) {
            $path = InstalledVersions::getInstallPath($name);

            $plugins[$name] = new PluginManifest(
                id: $name,
                name: $name,
                version: (string) InstalledVersions::getPrettyVersion($name),
                path: $path === null ? '' : (realpath($path) ?: $path),
            );
        }

        ksort($plugins);

        return $this->plugins = $plugins;
    }

    public function has(string $id): bool
    {
        return isset($this->all()[$id]);
    }

    public function get(string $id): ?PluginManifest
    {
        return $this->all()[$id] ?? null;
    }
}
