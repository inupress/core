<?php

declare(strict_types=1);

use Inupress\Core\Discovery\ManifestScanner;

function inupressFakeVendor(array $installed, bool $raw = false): string
{
    $dir = sys_get_temp_dir().'/inupress-vendor-'.bin2hex(random_bytes(4));
    mkdir($dir.'/composer', 0777, true);
    file_put_contents($dir.'/composer/installed.json', $raw ? json_encode($installed) : json_encode($installed));

    return $dir;
}

function inupressPkg(string $name, array $inupress, array $extra = [], array $more = []): array
{
    return array_merge([
        'name' => $name,
        'version' => '1.0.0',
        'install-path' => '../'.$name,
        'extra' => ['inupress' => $inupress] + $extra,
    ], $more);
}

it('finds plugins in Composer 2 format and ignores other packages', function () {
    $vendor = inupressFakeVendor(['packages' => [
        inupressPkg('acme/blog', ['id' => 'acme/blog', 'provider' => 'Acme\\Blog\\P', 'requires' => ['core' => '^0.1'], 'provides' => ['blog']]),
        ['name' => 'other/lib', 'version' => '2.0.0'],
    ], 'dev' => true, 'dev-package-names' => []]);

    $result = (new ManifestScanner($vendor))->scan();

    expect($result->problems)->toBe([])
        ->and(array_keys($result->plugins))->toBe(['acme/blog'])
        ->and($result->plugins['acme/blog']['requires'])->toBe(['core' => '^0.1'])
        ->and($result->plugins['acme/blog']['provides'])->toBe(['blog'])
        ->and($result->plugins['acme/blog']['dev'])->toBeFalse();
});

it('supports the Composer 1 format (plain list)', function () {
    $vendor = inupressFakeVendor([
        inupressPkg('acme/blog', ['id' => 'acme/blog', 'provider' => 'Acme\\Blog\\P']),
    ]);

    expect((new ManifestScanner($vendor))->scan()->plugins)->toHaveKey('acme/blog');
});

it('marks dev packages', function () {
    $vendor = inupressFakeVendor(['packages' => [
        inupressPkg('acme/dev', ['id' => 'acme/dev', 'provider' => 'Acme\\Dev\\P']),
    ], 'dev-package-names' => ['acme/dev']]);

    expect((new ManifestScanner($vendor))->scan()->plugins['acme/dev']['dev'])->toBeTrue();
});

it('uses the branch alias for dev versions', function () {
    $vendor = inupressFakeVendor(['packages' => [
        inupressPkg('acme/blog', ['id' => 'acme/blog', 'provider' => 'X'], ['branch-alias' => ['dev-main' => '0.1.x-dev']], ['version' => 'dev-main']),
    ]]);

    expect((new ManifestScanner($vendor))->scan()->plugins['acme/blog']['version'])->toBe('0.1.x-dev');
});

it('rejects a provider that is also in extra.laravel.providers', function () {
    $vendor = inupressFakeVendor(['packages' => [
        inupressPkg('acme/bad', ['id' => 'acme/bad', 'provider' => 'Acme\\Bad\\P'], ['laravel' => ['providers' => ['Acme\\Bad\\P']]]),
    ]]);

    $result = (new ManifestScanner($vendor))->scan();

    expect($result->plugins)->toBe([])
        ->and($result->problems[0])->toContain('extra.laravel.providers');
});

it('allows a helper provider in extra.laravel.providers', function () {
    $vendor = inupressFakeVendor(['packages' => [
        inupressPkg('acme/ok', ['id' => 'acme/ok', 'provider' => 'Acme\\Ok\\P'], ['laravel' => ['providers' => ['Acme\\Ok\\Helper']]]),
    ]]);

    $result = (new ManifestScanner($vendor))->scan();

    expect($result->problems)->toBe([])->and($result->plugins)->toHaveKey('acme/ok');
});

it('reports packages with a missing id or provider', function () {
    $vendor = inupressFakeVendor(['packages' => [
        inupressPkg('acme/nope', ['id' => 'acme/nope']),
    ]]);

    $result = (new ManifestScanner($vendor))->scan();

    expect($result->plugins)->toBe([])->and($result->problems)->toHaveCount(1);
});

it('reports duplicate plugin ids', function () {
    $vendor = inupressFakeVendor(['packages' => [
        inupressPkg('acme/one', ['id' => 'acme/same', 'provider' => 'A']),
        inupressPkg('acme/two', ['id' => 'acme/same', 'provider' => 'B']),
    ]]);

    $result = (new ManifestScanner($vendor))->scan();

    expect($result->plugins)->toHaveCount(1)->and($result->problems)->toHaveCount(1);
});

it('does not throw on a missing or broken installed.json', function () {
    $missing = sys_get_temp_dir().'/inupress-none-'.bin2hex(random_bytes(4));
    expect((new ManifestScanner($missing))->scan()->problems)->toHaveCount(1);

    $vendor = inupressFakeVendor([]);
    file_put_contents($vendor.'/composer/installed.json', '{broken');
    expect((new ManifestScanner($vendor))->scan()->problems)->toHaveCount(1);
});

it('resolves relative and absolute install paths', function () {
    $vendor = inupressFakeVendor([]);
    mkdir($vendor.'/acme/rel', 0777, true);
    $abs = sys_get_temp_dir().'/inupress-abs-'.bin2hex(random_bytes(4));
    mkdir($abs, 0777, true);

    file_put_contents($vendor.'/composer/installed.json', json_encode(['packages' => [
        inupressPkg('acme/rel', ['id' => 'acme/rel', 'provider' => 'A'], [], ['install-path' => '../acme/rel']),
        inupressPkg('acme/abs', ['id' => 'acme/abs', 'provider' => 'B'], [], ['install-path' => $abs]),
    ]]));

    $plugins = (new ManifestScanner($vendor))->scan()->plugins;

    expect($plugins['acme/rel']['path'])->toBe(realpath($vendor.'/acme/rel'))
        ->and($plugins['acme/abs']['path'])->toBe(realpath($abs));
});
