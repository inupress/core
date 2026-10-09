<?php

declare(strict_types=1);

namespace Inupress\Core\Discovery;

/**
 * @phpstan-type PluginEntry array{
 *     id: string, package: string, name: string, version: string, provider: string,
 *     requires: array<string, string>, provides: list<string>, path: ?string, dev: bool
 * }
 */
final readonly class DiscoveryResult
{
    /**
     * @param  array<string, PluginEntry>  $plugins  keyed by plugin id
     * @param  list<string>  $problems  human-readable discovery problems
     */
    public function __construct(
        public array $plugins,
        public array $problems = [],
    ) {}
}
