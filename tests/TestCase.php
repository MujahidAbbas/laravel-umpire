<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests;

use Illuminate\Encryption\Encrypter;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Passport\PassportServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;
use MujahidAbbas\Umpire\Tests\Fixtures\Models\Agent;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\CatalogServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\DynamicServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\GuardedCatalogServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\GuardedServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\HelpdeskServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\MisconfiguredServer;
use MujahidAbbas\Umpire\UmpireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

use function Orchestra\Testbench\default_migration_path;

abstract class TestCase extends Orchestra
{
    use LazilyRefreshDatabase;

    /**
     * A directory of the test's own for published config and migrations, used from the next
     * refreshApplication() and deleted after the test. Nothing may be published into the
     * skeleton, which every parallel worker loads and migrates from.
     */
    protected ?string $publishPath = null;

    protected function tearDown(): void
    {
        if ($this->publishPath !== null) {
            (new Filesystem)->deleteDirectory($this->publishPath);
        }

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            McpServiceProvider::class,
            PassportServiceProvider::class,
            SanctumServiceProvider::class,
            UmpireServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(Encrypter::generateKey('AES-256-CBC')));
        $app['config']->set('auth.guards.api', ['driver' => 'passport', 'provider' => 'users']);
        $app['config']->set('auth.providers.users.model', Agent::class);
        $app['config']->set('mcp.redirect_domains', ['*']);

        if ($this->publishPath !== null) {
            $app->useConfigPath($this->publishPath.'/config');
            $app->useDatabasePath($this->publishPath.'/database');
        }
    }

    /**
     * Registered with the migrator itself: once a non-memory database (PostgreSQL, MySQL) has
     * been refreshed, Testbench's loadMigrationsFrom() hands a list to migrate as arguments.
     */
    protected function defineDatabaseMigrations(): void
    {
        foreach ($this->migrationPaths() as $path) {
            $this->app['migrator']->path($path);
        }
    }

    /**
     * @return list<string>
     */
    protected function migrationPaths(): array
    {
        return [
            default_migration_path(),
            dirname(__DIR__).'/database/migrations',
            dirname(__DIR__).'/vendor/laravel/passport/database/migrations',
            dirname(__DIR__).'/vendor/laravel/sanctum/database/migrations',
        ];
    }

    protected function defineRoutes($router): void
    {
        Mcp::oauthRoutes();

        Mcp::web('mcp/helpdesk', HelpdeskServer::class);
        Mcp::web('mcp/catalog', CatalogServer::class);
        Mcp::web('mcp/dynamic', DynamicServer::class);
        Mcp::web('mcp/guarded', GuardedServer::class);
        Mcp::web('mcp/misconfigured', MisconfiguredServer::class);

        foreach (['passport' => 'auth:api', 'sanctum' => 'auth:sanctum'] as $guard => $middleware) {
            Mcp::web("mcp/{$guard}/helpdesk", HelpdeskServer::class)->middleware($middleware);
            Mcp::web("mcp/{$guard}/catalog", CatalogServer::class)->middleware($middleware);
            Mcp::web("mcp/{$guard}/guarded", GuardedServer::class)->middleware($middleware);
            Mcp::web("mcp/{$guard}/guarded-catalog", GuardedCatalogServer::class)->middleware($middleware);
        }
    }
}
