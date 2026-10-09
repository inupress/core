<?php

declare(strict_types=1);

namespace Inupress\Core\State;

/**
 * Explicit enable/disable overrides in storage/inupress/state.json: {"overrides": {"vendor/name": true}}.
 * Without an override, first-party plugins (id prefix "inupress/") are enabled and everything else is not.
 */
final class PluginState
{
    public const FIRST_PARTY_PREFIX = 'inupress/';

    public function __construct(private readonly string $file) {}

    public function isEnabled(string $id): bool
    {
        return $this->overrides()[$id] ?? str_starts_with($id, self::FIRST_PARTY_PREFIX);
    }

    public function enable(string $id): void
    {
        $this->set($id, true);
    }

    public function disable(string $id): void
    {
        $this->set($id, false);
    }

    /**
     * @return array<string, bool>
     */
    public function overrides(): array
    {
        if (! is_file($this->file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($this->file), true);
        $overrides = is_array($data) && is_array($data['overrides'] ?? null) ? $data['overrides'] : [];

        return array_map(boolval(...), $overrides);
    }

    private function set(string $id, bool $enabled): void
    {
        $overrides = $this->overrides();
        $overrides[$id] = $enabled;
        ksort($overrides);

        $dir = dirname($this->file);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($this->file, json_encode(['overrides' => $overrides], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n", LOCK_EX);
    }
}
