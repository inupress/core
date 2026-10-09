<?php

declare(strict_types=1);

namespace Inupress\Core\Migrations;

/**
 * Static check of a plugin's migrations (files are never executed):
 * every migration has down(), and every table it touches starts with the plugin prefix.
 */
final class MigrationLinter
{
    /** "inupress/blog" => "blog_" */
    public static function prefixFor(string $pluginId): string
    {
        $short = str_contains($pluginId, '/') ? substr($pluginId, (int) strrpos($pluginId, '/') + 1) : $pluginId;

        return preg_replace('/[^a-z0-9]+/', '_', strtolower($short)).'_';
    }

    /**
     * @return list<string> problems, empty when the directory is clean
     */
    public function lint(string $dir, string $prefix): array
    {
        $problems = [];
        $files = glob(rtrim($dir, '/\\').'/*.php') ?: [];
        sort($files);

        foreach ($files as $file) {
            $name = basename($file);
            $code = (string) file_get_contents($file);

            if (preg_match('/function\s+down\s*\(/i', $code) !== 1) {
                $problems[] = "$name: missing down() method.";
            }

            preg_match_all(
                '/Schema::(?:connection\([^)]*\)->)?(create|table|drop|dropIfExists|rename|hasTable|hasColumn)\s*\(\s*([\'"])([^\'"]+)\2/',
                $code,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as $m) {
                if (! str_starts_with($m[3], $prefix)) {
                    $problems[] = "$name: table [{$m[3]}] must start with [$prefix].";
                }
            }

            // rename(from, to): the target name must be prefixed too.
            preg_match_all('/Schema::rename\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*([\'"])([^\'"]+)\1/', $code, $renames, PREG_SET_ORDER);

            foreach ($renames as $m) {
                if (! str_starts_with($m[2], $prefix)) {
                    $problems[] = "$name: table [{$m[2]}] must start with [$prefix].";
                }
            }
        }

        return $problems;
    }
}
