<?php

declare(strict_types=1);

namespace Inupress\Core\Resolver;

use Composer\Semver\Semver;
use Throwable;

/**
 * Decides which of the requested plugins can load and in which order.
 * A plugin that cannot load is skipped with a reason, together with everything that depends on it.
 */
final class DependencyResolver
{
    /**
     * @param  array<string, array{requires: array<string, string>, version: string}>  $installed  every discovered plugin
     * @param  list<string>  $requested  ids that should be enabled
     */
    public function resolve(array $installed, array $requested, string $coreVersion): Resolution
    {
        $skipped = [];
        $candidates = [];

        foreach ($requested as $id) {
            if (! isset($installed[$id])) {
                $skipped[$id] = 'not installed';

                continue;
            }
            $candidates[$id] = true;
        }

        // Drop plugins with unmet requirements until nothing changes (this cascades to dependents).
        do {
            $changed = false;

            foreach (array_keys($candidates) as $id) {
                $reason = $this->unmetRequirement($id, $installed, $candidates, $coreVersion);

                if ($reason !== null) {
                    $skipped[$id] = $reason;
                    unset($candidates[$id]);
                    $changed = true;
                }
            }
        } while ($changed);

        // Kahn's algorithm; sorted so the order is deterministic.
        $order = [];
        $remaining = array_keys($candidates);
        sort($remaining);

        while ($remaining !== []) {
            $progress = false;

            // Take the first ready plugin (alphabetically) and restart, so the order is stable.
            foreach ($remaining as $i => $id) {
                $deps = array_diff(array_keys($installed[$id]['requires']), ['core']);

                if (array_diff($deps, $order) === []) {
                    $order[] = $id;
                    unset($remaining[$i]);
                    $progress = true;

                    break;
                }
            }

            if (! $progress) {
                foreach ($remaining as $id) {
                    $skipped[$id] = 'part of a dependency cycle or depends on one';
                }
                break;
            }
        }

        ksort($skipped);

        return new Resolution($order, $skipped);
    }

    /**
     * @param  array<string, array{requires: array<string, string>, version: string}>  $installed
     * @param  array<string, true>  $candidates
     */
    private function unmetRequirement(string $id, array $installed, array $candidates, string $coreVersion): ?string
    {
        foreach ($installed[$id]['requires'] as $dep => $constraint) {
            if ($dep === 'core') {
                $version = $coreVersion;
            } elseif (! isset($installed[$dep])) {
                return "requires [$dep], which is not installed";
            } elseif (! isset($candidates[$dep])) {
                return "requires [$dep], which is disabled or cannot load";
            } else {
                $version = $installed[$dep]['version'];
            }

            try {
                $ok = Semver::satisfies($version, $constraint);
            } catch (Throwable) {
                return "has an invalid version constraint [$constraint] for [$dep]";
            }

            if (! $ok) {
                return "requires [$dep] $constraint, but $version is installed";
            }
        }

        return null;
    }
}
