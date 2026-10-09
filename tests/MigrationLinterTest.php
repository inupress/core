<?php

declare(strict_types=1);

use Inupress\Core\Migrations\MigrationLinter;

function inupressMigrationDir(array $files): string
{
    $dir = sys_get_temp_dir().'/inupress-mig-'.bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);

    foreach ($files as $name => $code) {
        file_put_contents($dir.'/'.$name, $code);
    }

    return $dir;
}

function inupressMigration(string $schemaCalls, bool $withDown = true): string
{
    $down = $withDown ? 'public function down(): void {}' : '';

    return "<?php\nreturn new class extends Illuminate\\Database\\Migrations\\Migration {\n"
        ."public function up(): void { $schemaCalls }\n$down\n};\n";
}

it('derives the table prefix from the plugin id', function () {
    expect(MigrationLinter::prefixFor('inupress/blog'))->toBe('blog_')
        ->and(MigrationLinter::prefixFor('acme/menu-builder'))->toBe('menu_builder_')
        ->and(MigrationLinter::prefixFor('single'))->toBe('single_');
});

it('accepts a clean migration', function () {
    $dir = inupressMigrationDir(['2026_01_01_000000_a.php' => inupressMigration("Schema::create('blog_posts', fn () => null);")]);

    expect((new MigrationLinter)->lint($dir, 'blog_'))->toBe([]);
});

it('rejects a migration without down()', function () {
    $dir = inupressMigrationDir(['m.php' => inupressMigration("Schema::create('blog_posts', fn () => null);", withDown: false)]);

    $problems = (new MigrationLinter)->lint($dir, 'blog_');

    expect($problems)->toHaveCount(1)->and($problems[0])->toContain('down()');
});

it('rejects tables without the plugin prefix', function (string $call) {
    $dir = inupressMigrationDir(['m.php' => inupressMigration($call)]);

    expect((new MigrationLinter)->lint($dir, 'blog_'))->toHaveCount(1);
})->with([
    'create' => ["Schema::create('posts', fn () => null);"],
    'table' => ["Schema::table('users', fn () => null);"],
    'dropIfExists' => ['Schema::dropIfExists("users");'],
    'rename' => ["Schema::rename('blog_posts', 'posts');"],
]);

it('reports every offending table', function () {
    $dir = inupressMigrationDir(['m.php' => inupressMigration("Schema::create('a', fn () => null); Schema::create('b', fn () => null);")]);

    expect((new MigrationLinter)->lint($dir, 'blog_'))->toHaveCount(2);
});

it('returns nothing for an empty directory', function () {
    expect((new MigrationLinter)->lint(inupressMigrationDir([]), 'blog_'))->toBe([]);
});
