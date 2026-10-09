<?php

declare(strict_types=1);

namespace Inupress\Core;

use Illuminate\Contracts\Foundation\Application;
use Inupress\Contracts\PluginManifest;
use Inupress\Contracts\PluginRepository;
use Inupress\Core\Discovery\DiscoveryResult;
use Inupress\Core\Discovery\ManifestScanner;
use Inupress\Core\Discovery\PluginCache;
use Inupress\Core\Resolver\DependencyResolver;
use Inupress\Core\State\PluginState;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Registers the service providers of enabled plugins, and only those.
 * A broken plugin is skipped with a log entry; it never takes the site down.
 *
 * @phpstan-import-type PluginEntry from DiscoveryResult
 */
final class PluginManager implements PluginRepository
{
    /** @var array<string, PluginManifest> */
    private array $loaded = [];

    /** @var array<string, string> plugin id => reason it did not load */
    private array $skipped = [];

    private bool $registered = false;

    public function __construct(
        private readonly Application $app,
        private readonly ManifestScanner $scanner,
        private readonly PluginCache $cache,
        private readonly PluginState $state,
        private readonly DependencyResolver $resolver,
    ) {}

    /**
     * Cached result when present, otherwise a live scan (nothing is written).
     */
    public function discovered(): DiscoveryResult
    {
        return $this->cache->read() ?? $this->scanner->scan();
    }

    public function discover(): DiscoveryResult
    {
        $result = $this->scanner->scan();
        $previous = $this->cache->read();

        // A broken installed.json must not wipe a good cache.
        if ($previous !== null && $result->plugins === [] && $result->problems !== []) {
            return $previous;
        }

        $this->cache->write($result);

        return $result;
    }

    public function registerAll(): void
    {
        if ($this->registered) {
            return;
        }
        $this->registered = true;

        $result = $this->discovered();
        $plugins = $result->plugins;

        foreach ($result->problems as $problem) {
            $this->warn($problem);
        }

        $requested = [];
        foreach ($plugins as $id => $entry) {
            if (! $this->state->isEnabled($id)) {
                continue;
            }
            if ($entry['dev'] && $this->app->environment('production')) {
                $this->skipped[$id] = 'dev package in production';

                continue;
            }
            $requested[] = $id;
        }

        // Ids enabled in state.json whose package is gone (e.g. deploy without dev) are ignored silently.
        $resolution = $this->resolver->resolve($plugins, $requested, Core::VERSION);

        foreach ($resolution->skipped as $id => $reason) {
            $this->skipped[$id] = $reason;
            $this->warn("Plugin [$id] was not loaded: $reason.");
        }

        foreach ($resolution->order as $id) {
            $this->load($plugins[$id]);
        }
    }

    /**
     * @param  PluginEntry  $entry
     */
    private function load(array $entry): void
    {
        $provider = $entry['provider'];

        if (! class_exists($provider)) {
            $this->skipped[$entry['id']] = "provider class [$provider] not found";
            $this->warn("Plugin [{$entry['id']}] was not loaded: provider class [$provider] not found.");

            return;
        }

        try {
            $this->app->register($provider);
        } catch (Throwable $e) {
            $this->skipped[$entry['id']] = 'provider failed: '.$e->getMessage();
            $this->warn("Plugin [{$entry['id']}] failed to register: ".$e->getMessage());

            return;
        }

        $this->loaded[$entry['id']] = new PluginManifest(
            id: $entry['id'],
            name: $entry['name'],
            version: $entry['version'],
            provider: $provider,
            requires: $entry['requires'],
            provides: $entry['provides'],
        );
    }

    /** @return array<string, PluginManifest> */
    public function enabled(): array
    {
        return $this->loaded;
    }

    public function has(string $id): bool
    {
        return isset($this->loaded[$id]);
    }

    public function get(string $id): ?PluginManifest
    {
        return $this->loaded[$id] ?? null;
    }

    /** @return array<string, string> */
    public function skipped(): array
    {
        return $this->skipped;
    }

    public function state(): PluginState
    {
        return $this->state;
    }

    private function warn(string $message): void
    {
        try {
            $this->app->make(LoggerInterface::class)->warning('[inupress] '.$message);
        } catch (Throwable) {
            // logging must never break boot
        }
    }
}
