<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

dataset('migrations', [
    'audit table' => ['create_mcp_tool_calls_table', 'umpire.audit.table'],
    'registered clients' => ['create_mcp_registered_clients_table', 'umpire.clients.table'],
]);

it('publishes each migration once however often the tag is published', function (array $secondRun): void {
    $this->publishPath = sys_get_temp_dir().'/umpire-'.Str::random(12);

    // Each vendor:publish runs in a process of its own: the provider builds the publish map at
    // boot, and Laravel never clears the publishable migration paths within a process.
    $publish = function (array $options): void {
        (new ReflectionProperty(ServiceProvider::class, 'publishableMigrationPaths'))->setValue(null, []);
        $this->refreshApplication();
        $this->artisan('vendor:publish', ['--tag' => 'umpire-migrations', ...$options])->assertSuccessful();
    };

    $publish([]);
    $this->travel(5)->minutes();
    $publish($secondRun);

    $files = File::glob($this->publishPath.'/database/migrations/*.php');
    $published = collect($files)
        ->mapWithKeys(fn (string $path): array => [preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($path)) => File::get($path)]);

    expect($files)->toHaveCount(2)
        ->and($published->keys()->sort()->values()->all())->toBe(['create_mcp_registered_clients_table.php', 'create_mcp_tool_calls_table.php']);

    foreach ($published as $name => $contents) {
        expect($contents)->toBe(File::get(dirname(__DIR__, 2).'/database/migrations/'.$name));
    }
})->with([
    'twice' => [[]],
    'again with --force' => [['--force' => true]],
]);

it('refuses to migrate when the umpire config is missing', function (string $migration, string $key): void {
    config(['umpire' => null]);

    $migration = require dirname(__DIR__, 2)."/database/migrations/{$migration}.php";

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, "The {$key} config value is missing");
})->with('migrations');

it('creates the registered clients table on Passport\'s connection', function (?string $connection): void {
    config(['passport.connection' => $connection]);

    $migration = require dirname(__DIR__, 2).'/database/migrations/create_mcp_registered_clients_table.php';

    expect($migration->getConnection())->toBe($connection);
})->with([
    'the default' => [null],
    'a connection of its own' => ['passport'],
]);

it('records, lists and purges clients on Passport\'s own connection', function (): void {
    // The first query migrates the default connection, before Passport's moves elsewhere.
    expect(DB::table('mcp_registered_clients')->count())->toBe(0);

    $database = sys_get_temp_dir().'/umpire-'.Str::random(12).'.sqlite';
    touch($database);
    config([
        'database.connections.passport' => ['driver' => 'sqlite', 'database' => $database, 'foreign_key_constraints' => true],
        'passport.connection' => 'passport',
    ]);

    try {
        $this->artisan('migrate', ['--database' => 'passport', '--realpath' => true, '--path' => [
            dirname(__DIR__, 2).'/vendor/laravel/passport/database/migrations',
            dirname(__DIR__, 2).'/database/migrations/create_mcp_registered_clients_table.php',
        ]])->assertSuccessful();

        $this->travelTo(now()->subDays(2));
        $id = registerClient();
        $this->travelBack();

        expect(DB::connection('passport')->table('mcp_registered_clients')->pluck('client_id')->all())->toBe([$id])
            ->and(DB::table('mcp_registered_clients')->count())->toBe(0);

        $this->artisan('mcp:purge-clients', ['--unused' => true, '--force' => true])
            ->expectsOutput('Deleted 1 client.')
            ->assertSuccessful();

        expect(Passport::client()->newQuery()->count())->toBe(0)
            ->and(DB::connection('passport')->table('mcp_registered_clients')->count())->toBe(0);
    } finally {
        DB::purge('passport');
        File::delete($database);
    }
});
