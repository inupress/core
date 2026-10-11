<?php

declare(strict_types=1);

namespace Inupress\Core\Themes;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inupress\Contracts\Theme;
use Throwable;

/**
 * The theme's CSS and JS are copied into public/vendor/<theme id>/ so the web server sends them
 * directly. The copy is a build artifact (git ignores it); `php artisan inupress:theme:publish`
 * refreshes it, and a missing file is copied during boot so a fresh install is styled immediately.
 */
final class ThemeAssets
{
    public const STYLES = 'styles';

    public const SCRIPTS = 'scripts';

    public function __construct(private readonly ThemeManager $themes) {}

    /**
     * Public-relative paths of the active chain's stylesheets, base theme first.
     *
     * @return list<string>
     */
    public function styles(): array
    {
        return $this->collect(self::STYLES);
    }

    /**
     * Public-relative paths of the active chain's scripts, base theme first.
     *
     * @return list<string>
     */
    public function scripts(): array
    {
        return $this->collect(self::SCRIPTS);
    }

    /**
     * The link and script tags for the active chain, ready to print in a layout.
     */
    public function tags(string $kind): string
    {
        $tags = [];

        foreach ($kind === self::SCRIPTS ? $this->scripts() : $this->styles() as $file) {
            $url = e(asset($file));

            $tags[] = $kind === self::SCRIPTS
                ? '<script src="'.$url.'" defer></script>'
                : '<link rel="stylesheet" href="'.$url.'">';
        }

        return implode("\n    ", $tags);
    }

    /**
     * Copies the theme files into public/. Returns how many files were written.
     */
    public function publish(bool $force = false): int
    {
        $copied = 0;

        foreach ($this->themes->chain() as $theme) {
            foreach (array_merge($theme->styles(), $theme->scripts()) as $file) {
                if (! is_file($file)) {
                    Log::warning("Inupress: theme [{$theme->id()}] declares a missing file [$file].");

                    continue;
                }

                $target = public_path($this->publicPath($theme, $file));

                if (! $force && is_file($target) && filemtime($target) >= (int) filemtime($file)) {
                    continue;
                }

                try {
                    File::ensureDirectoryExists(dirname($target));
                    File::copy($file, $target);
                } catch (Throwable $e) {
                    Log::warning("Inupress: cannot publish the theme asset [$file]: {$e->getMessage()}");

                    continue;
                }

                $copied++;
            }
        }

        return $copied;
    }

    /**
     * Publishes what is not there yet; used during boot so a fresh install is styled.
     */
    public function publishIfMissing(): void
    {
        $this->publish(force: false);
    }

    /**
     * @return list<string>
     */
    private function collect(string $kind): array
    {
        $files = [];

        foreach ($this->themes->chain() as $theme) {
            foreach ($kind === self::SCRIPTS ? $theme->scripts() : $theme->styles() as $file) {
                $files[] = $this->publicPath($theme, $file);
            }
        }

        return $files;
    }

    private function publicPath(Theme $theme, string $file): string
    {
        $root = rtrim($theme->path(), '/\\');
        $relative = str_starts_with($file, $root)
            ? ltrim(substr($file, strlen($root)), '/\\')
            : basename($file);

        return 'vendor/'.$theme->id().'/'.str_replace('\\', '/', $relative);
    }
}
