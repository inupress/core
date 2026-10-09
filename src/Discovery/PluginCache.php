<?php

declare(strict_types=1);

namespace Inupress\Core\Discovery;

/**
 * PHP-file cache of the discovery result (bootstrap/cache/inupress-plugins.php).
 * It holds the plugins that exist, never whether they are enabled.
 */
final class PluginCache
{
    public function __construct(private readonly string $file) {}

    public function path(): string
    {
        return $this->file;
    }

    public function write(DiscoveryResult $result): void
    {
        $dir = dirname($this->file);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $php = '<?php return '.var_export(['plugins' => $result->plugins, 'problems' => $result->problems], true).";\n";
        $tmp = $this->file.'.'.getmypid().'.tmp';
        file_put_contents($tmp, $php);
        rename($tmp, $this->file);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($this->file, true);
        }
    }

    public function read(): ?DiscoveryResult
    {
        if (! is_file($this->file)) {
            return null;
        }

        $data = @include $this->file;

        if (! is_array($data) || ! is_array($data['plugins'] ?? null)) {
            return null;
        }

        return new DiscoveryResult($data['plugins'], is_array($data['problems'] ?? null) ? $data['problems'] : []);
    }

    public function forget(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }
}
