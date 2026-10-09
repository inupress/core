<?php

declare(strict_types=1);

use Inupress\Core\Discovery\DiscoveryResult;
use Inupress\Core\Discovery\PluginCache;
use Inupress\Core\State\PluginState;

function inupressTmp(string $name): string
{
    return sys_get_temp_dir().'/inupress-'.bin2hex(random_bytes(4)).'/'.$name;
}

it('enables first-party plugins by default and others not', function () {
    $state = new PluginState(inupressTmp('state.json'));

    expect($state->isEnabled('inupress/pages'))->toBeTrue()
        ->and($state->isEnabled('acme/blog'))->toBeFalse();
});

it('stores explicit overrides and they beat the default', function () {
    $file = inupressTmp('state.json');
    $state = new PluginState($file);

    $state->enable('acme/blog');
    $state->disable('inupress/pages');

    $fresh = new PluginState($file);

    expect($fresh->isEnabled('acme/blog'))->toBeTrue()
        ->and($fresh->isEnabled('inupress/pages'))->toBeFalse()
        ->and($fresh->overrides())->toBe(['acme/blog' => true, 'inupress/pages' => false]);
});

it('treats a broken state file as empty', function () {
    $file = inupressTmp('state.json');
    mkdir(dirname($file), 0777, true);
    file_put_contents($file, '{nope');

    expect((new PluginState($file))->overrides())->toBe([]);
});

it('round-trips the discovery cache', function () {
    $cache = new PluginCache(inupressTmp('cache/plugins.php'));
    $entry = [
        'id' => 'a/b', 'package' => 'a/b', 'name' => 'a/b', 'version' => '1.0.0', 'provider' => 'A\\B\\P',
        'requires' => ['core' => '^0.1'], 'provides' => [], 'path' => null, 'dev' => false,
    ];

    expect($cache->read())->toBeNull();

    $cache->write(new DiscoveryResult(['a/b' => $entry], ['some problem']));
    $read = $cache->read();

    expect($read?->plugins)->toBe(['a/b' => $entry])
        ->and($read?->problems)->toBe(['some problem']);

    $cache->forget();
    expect($cache->read())->toBeNull();
});

it('ignores a corrupt cache file', function () {
    $file = inupressTmp('cache/plugins.php');
    mkdir(dirname($file), 0777, true);
    file_put_contents($file, '<?php return "garbage";');

    expect((new PluginCache($file))->read())->toBeNull();
});
