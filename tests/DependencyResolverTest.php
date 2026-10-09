<?php

declare(strict_types=1);

use Inupress\Core\Resolver\DependencyResolver;

function inupressInstalled(array $map): array
{
    $out = [];
    foreach ($map as $id => $spec) {
        $out[$id] = ['version' => $spec['version'] ?? '1.0.0', 'requires' => $spec['requires'] ?? []];
    }

    return $out;
}

it('orders dependencies first', function () {
    $installed = inupressInstalled([
        'a/seo' => ['requires' => ['a/pages' => '^1.0']],
        'a/pages' => [],
        'a/blog' => ['requires' => ['a/pages' => '^1.0']],
    ]);

    $r = (new DependencyResolver)->resolve($installed, ['a/seo', 'a/blog', 'a/pages'], '0.1.0');

    expect($r->skipped)->toBe([])
        ->and($r->order)->toBe(['a/pages', 'a/blog', 'a/seo']);
});

it('skips a plugin whose dependency is missing', function () {
    $r = (new DependencyResolver)->resolve(inupressInstalled(['a/x' => ['requires' => ['a/ghost' => '^1.0']]]), ['a/x'], '0.1.0');

    expect($r->order)->toBe([])->and($r->skipped['a/x'])->toContain('not installed');
});

it('skips a plugin whose dependency is disabled, and cascades', function () {
    $installed = inupressInstalled([
        'a/base' => [],
        'a/mid' => ['requires' => ['a/base' => '^1.0']],
        'a/top' => ['requires' => ['a/mid' => '^1.0']],
    ]);

    $r = (new DependencyResolver)->resolve($installed, ['a/mid', 'a/top'], '0.1.0');

    expect($r->order)->toBe([])
        ->and($r->skipped)->toHaveKeys(['a/mid', 'a/top']);
});

it('skips a plugin on incompatible core version', function () {
    $r = (new DependencyResolver)->resolve(inupressInstalled(['a/x' => ['requires' => ['core' => '^99.0']]]), ['a/x'], '0.1.0');

    expect($r->order)->toBe([])->and($r->skipped['a/x'])->toContain('core');
});

it('accepts core ^0.1 for core 0.1.0', function () {
    $r = (new DependencyResolver)->resolve(inupressInstalled(['a/x' => ['requires' => ['core' => '^0.1']]]), ['a/x'], '0.1.0');

    expect($r->order)->toBe(['a/x']);
});

it('skips a plugin on incompatible dependency version', function () {
    $installed = inupressInstalled([
        'a/base' => ['version' => '1.0.0'],
        'a/x' => ['requires' => ['a/base' => '^2.0']],
    ]);

    $r = (new DependencyResolver)->resolve($installed, ['a/base', 'a/x'], '0.1.0');

    expect($r->order)->toBe(['a/base'])->and($r->skipped['a/x'])->toContain('^2.0');
});

it('handles dev branch alias versions', function () {
    $installed = inupressInstalled([
        'a/base' => ['version' => '0.1.x-dev'],
        'a/x' => ['requires' => ['a/base' => '^0.1']],
    ]);

    expect((new DependencyResolver)->resolve($installed, ['a/base', 'a/x'], '0.1.0')->order)->toBe(['a/base', 'a/x']);
});

it('detects cycles and still loads independent plugins', function () {
    $installed = inupressInstalled([
        'a/one' => ['requires' => ['a/two' => '*']],
        'a/two' => ['requires' => ['a/one' => '*']],
        'a/free' => [],
    ]);

    $r = (new DependencyResolver)->resolve($installed, ['a/one', 'a/two', 'a/free'], '0.1.0');

    expect($r->order)->toBe(['a/free'])
        ->and($r->skipped)->toHaveKeys(['a/one', 'a/two']);
});

it('reports requested plugins that are not installed', function () {
    $r = (new DependencyResolver)->resolve([], ['a/gone'], '0.1.0');

    expect($r->order)->toBe([])->and($r->skipped['a/gone'])->toBe('not installed');
});

it('survives an invalid constraint', function () {
    $installed = inupressInstalled([
        'a/base' => [],
        'a/x' => ['requires' => ['a/base' => 'not a constraint!!']],
    ]);

    $r = (new DependencyResolver)->resolve($installed, ['a/base', 'a/x'], '0.1.0');

    expect($r->order)->toBe(['a/base'])->and($r->skipped)->toHaveKey('a/x');
});
