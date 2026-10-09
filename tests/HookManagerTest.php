<?php

declare(strict_types=1);

use Inupress\Core\Hooks\HookManager;

it('runs actions by priority, then by registration order', function () {
    $hooks = new HookManager;
    $log = [];

    $hooks->addAction('x', function () use (&$log) {
        $log[] = 'late';
    }, 20);
    $hooks->addAction('x', function () use (&$log) {
        $log[] = 'first';
    });
    $hooks->addAction('x', function () use (&$log) {
        $log[] = 'second';
    });
    $hooks->addAction('x', function () use (&$log) {
        $log[] = 'early';
    }, 1);

    $hooks->doAction('x');

    expect($log)->toBe(['early', 'first', 'second', 'late']);
});

it('passes arguments to actions', function () {
    $hooks = new HookManager;
    $got = null;
    $hooks->addAction('x', function (...$args) use (&$got) {
        $got = $args;
    });

    $hooks->doAction('x', 1, 'two');

    expect($got)->toBe([1, 'two']);
});

it('chains filters and passes extra args', function () {
    $hooks = new HookManager;
    $hooks->addFilter('f', fn (string $v, string $suffix) => $v.'-a'.$suffix, 5);
    $hooks->addFilter('f', fn (string $v) => $v.'-b', 10);

    expect($hooks->applyFilters('f', 'v', '!'))->toBe('v-a!-b');
});

it('returns the value untouched when nothing is hooked', function () {
    $hooks = new HookManager;

    expect($hooks->applyFilters('none', 'same'))->toBe('same');
    $hooks->doAction('none');
    expect($hooks->hasAction('none'))->toBeFalse()->and($hooks->hasFilter('none'))->toBeFalse();
});

it('reports registered hooks', function () {
    $hooks = new HookManager;
    $hooks->addAction('a', fn () => null);
    $hooks->addFilter('f', fn ($v) => $v);

    expect($hooks->hasAction('a'))->toBeTrue()
        ->and($hooks->hasFilter('f'))->toBeTrue()
        ->and($hooks->hasAction('f'))->toBeFalse();
});
