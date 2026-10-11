<?php

declare(strict_types=1);

namespace Inupress\Core\Themes;

use Illuminate\Support\Facades\Log;
use Inupress\Contracts\Theme;
use JsonException;

/**
 * A theme described by its theme.json manifest. Works for a Composer package and for a directory
 * under themes/ alike: the core only needs the root path and the manifest.
 */
final class ManifestTheme implements Theme
{
    public const DEFAULT_VIEWS = 'resources/views';

    /**
     * @param  list<string>  $styles  paths relative to the theme root
     * @param  list<string>  $scripts  paths relative to the theme root
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private readonly string $root,
        private readonly ?string $parent = null,
        private readonly string $views = self::DEFAULT_VIEWS,
        private readonly array $styles = [],
        private readonly array $scripts = [],
    ) {}

    /**
     * Reads <root>/theme.json. A missing or unreadable manifest is not fatal: the theme then loads
     * no assets and only overrides views.
     */
    public static function fromDirectory(string $id, string $root): self
    {
        $root = rtrim(realpath($root) ?: $root, '/\\');
        $manifest = self::readManifest($id, $root.'/theme.json');

        $parent = $manifest['parent'] ?? null;

        return new self(
            id: is_string($manifest['id'] ?? null) && $manifest['id'] !== '' ? $manifest['id'] : $id,
            name: is_string($manifest['name'] ?? null) && $manifest['name'] !== '' ? $manifest['name'] : $id,
            root: $root,
            parent: is_string($parent) && $parent !== '' ? $parent : null,
            views: is_string($manifest['views'] ?? null) && $manifest['views'] !== '' ? $manifest['views'] : self::DEFAULT_VIEWS,
            styles: self::fileList($manifest['styles'] ?? []),
            scripts: self::fileList($manifest['scripts'] ?? []),
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function path(string $relative = ''): string
    {
        return $relative === '' ? $this->root : $this->root.'/'.ltrim($relative, '/\\');
    }

    public function parent(): ?string
    {
        return $this->parent;
    }

    public function views(): string
    {
        return $this->path($this->views);
    }

    /**
     * @return list<string>
     */
    public function styles(): array
    {
        return array_map(fn (string $file): string => $this->path($file), $this->styles);
    }

    /**
     * @return list<string>
     */
    public function scripts(): array
    {
        return array_map(fn (string $file): string => $this->path($file), $this->scripts);
    }

    /**
     * @return array<string, mixed>
     */
    private static function readManifest(string $id, string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        try {
            $manifest = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            Log::warning("Inupress: the manifest of theme [$id] is not valid JSON: {$e->getMessage()}");

            return [];
        }

        return is_array($manifest) ? $manifest : [];
    }

    /**
     * @return list<string>
     */
    private static function fileList(mixed $files): array
    {
        if (! is_array($files)) {
            return [];
        }

        $out = [];

        foreach ($files as $file) {
            if (is_string($file) && $file !== '') {
                $out[] = $file;
            }
        }

        return $out;
    }
}
