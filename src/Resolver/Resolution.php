<?php

declare(strict_types=1);

namespace Inupress\Core\Resolver;

final readonly class Resolution
{
    /**
     * @param  list<string>  $order  plugin ids in load order (dependencies first)
     * @param  array<string, string>  $skipped  plugin id => reason it will not load
     */
    public function __construct(
        public array $order,
        public array $skipped = [],
    ) {}
}
