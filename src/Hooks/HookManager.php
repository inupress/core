<?php

declare(strict_types=1);

namespace Inupress\Core\Hooks;

use Inupress\Contracts\HookRegistry;

final class HookManager implements HookRegistry
{
    /** @var array<string, array<int, list<callable>>> */
    private array $actions = [];

    /** @var array<string, array<int, list<callable>>> */
    private array $filters = [];

    public function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        $this->actions[$hook][$priority][] = $callback;
    }

    public function doAction(string $hook, mixed ...$args): void
    {
        foreach ($this->callbacks($this->actions, $hook) as $callback) {
            $callback(...$args);
        }
    }

    public function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->filters[$hook][$priority][] = $callback;
    }

    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        foreach ($this->callbacks($this->filters, $hook) as $callback) {
            $value = $callback($value, ...$args);
        }

        return $value;
    }

    public function hasAction(string $hook): bool
    {
        return ! empty($this->actions[$hook]);
    }

    public function hasFilter(string $hook): bool
    {
        return ! empty($this->filters[$hook]);
    }

    /**
     * Lower priority first, registration order inside one priority.
     *
     * @param  array<string, array<int, list<callable>>>  $map
     * @return list<callable>
     */
    private function callbacks(array $map, string $hook): array
    {
        $byPriority = $map[$hook] ?? [];
        ksort($byPriority);

        return array_merge(...array_values($byPriority ?: [[]]));
    }
}
