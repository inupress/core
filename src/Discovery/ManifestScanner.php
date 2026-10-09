<?php

declare(strict_types=1);

namespace Inupress\Core\Discovery;

/**
 * Reads vendor/composer/installed.json and returns packages that carry `extra.inupress`.
 * Never throws on bad input: problems are returned in the result so the site keeps booting.
 *
 * @phpstan-import-type PluginEntry from DiscoveryResult
 */
final class ManifestScanner
{
    public function __construct(private readonly string $vendorDir) {}

    public function scan(): DiscoveryResult
    {
        $file = $this->vendorDir.'/composer/installed.json';

        if (! is_file($file)) {
            return new DiscoveryResult([], ["installed.json not found: $file"]);
        }

        $raw = json_decode((string) file_get_contents($file), true);

        if (! is_array($raw)) {
            return new DiscoveryResult([], ["installed.json is not valid JSON: $file"]);
        }

        /** @var array<int, mixed> $packages Composer 2 wraps the list in "packages", Composer 1 does not */
        $packages = is_array($raw['packages'] ?? null) ? $raw['packages'] : $raw;
        $devNames = is_array($raw['dev-package-names'] ?? null) ? $raw['dev-package-names'] : [];

        $plugins = [];
        $problems = [];

        foreach ($packages as $package) {
            if (! is_array($package) || ! is_array($package['extra']['inupress'] ?? null)) {
                continue;
            }

            $name = (string) ($package['name'] ?? '?');
            $meta = $package['extra']['inupress'];
            $id = $meta['id'] ?? null;
            $provider = $meta['provider'] ?? null;

            if (! is_string($id) || $id === '' || ! is_string($provider) || $provider === '') {
                $problems[] = "[$name] extra.inupress.id and extra.inupress.provider are required.";

                continue;
            }

            $laravelProviders = $package['extra']['laravel']['providers'] ?? [];
            if (is_array($laravelProviders) && in_array($provider, $laravelProviders, true)) {
                $problems[] = "[$name] provider [$provider] is also listed in extra.laravel.providers; "
                    .'Laravel would boot it even when the plugin is disabled. Remove it from extra.laravel.providers.';

                continue;
            }

            if (isset($plugins[$id])) {
                $problems[] = "[$name] plugin id [$id] is already declared by package [{$plugins[$id]['package']}].";

                continue;
            }

            $plugins[$id] = [
                'id' => $id,
                'package' => $name,
                'name' => is_string($meta['name'] ?? null) ? $meta['name'] : $name,
                'version' => $this->effectiveVersion($package),
                'provider' => $provider,
                'requires' => $this->stringMap($meta['requires'] ?? []),
                'provides' => array_values(array_map(strval(...), is_array($meta['provides'] ?? null) ? $meta['provides'] : [])),
                'path' => $this->installPath($package),
                'dev' => in_array($name, $devNames, true),
            ];
        }

        ksort($plugins);

        return new DiscoveryResult($plugins, $problems);
    }

    /**
     * Path packages are installed as "dev-main"; use the branch alias ("0.1.x-dev") so that
     * semver constraints between plugins can be checked in the development monorepo too.
     *
     * @param  array<string, mixed>  $package
     */
    private function effectiveVersion(array $package): string
    {
        $version = (string) ($package['version'] ?? '0.0.0');

        if (str_starts_with($version, 'dev-')) {
            $alias = $package['extra']['branch-alias'][$version] ?? null;

            if (is_string($alias)) {
                return $alias;
            }
        }

        return $version;
    }

    /**
     * @param  array<string, mixed>  $package
     */
    private function installPath(array $package): ?string
    {
        $relative = $package['install-path'] ?? null;

        if (! is_string($relative) || $relative === '') {
            return null;
        }

        $isAbsolute = str_starts_with($relative, '/') || str_starts_with($relative, '\\') || preg_match('/^[A-Za-z]:[\\\\\/]/', $relative) === 1;
        $path = realpath($isAbsolute ? $relative : $this->vendorDir.'/composer/'.$relative);

        return $path === false ? null : $path;
    }

    /**
     * @return array<string, string>
     */
    private function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $k => $v) {
            $out[(string) $k] = (string) $v;
        }

        return $out;
    }
}
