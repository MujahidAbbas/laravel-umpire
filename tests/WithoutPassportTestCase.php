<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests;

use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\McpServiceProvider;
use Laravel\Passport\Passport;
use Laravel\Sanctum\SanctumServiceProvider;
use MujahidAbbas\Umpire\Tests\Fixtures\Models\SanctumAgent;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\HelpdeskServer;
use MujahidAbbas\Umpire\UmpireServiceProvider;

use function Orchestra\Testbench\default_migration_path;

/**
 * An app with Sanctum and no Laravel Passport. Every other test needs Passport, so these run
 * only after `composer remove laravel/passport --dev`, as CI's without-Passport job does.
 */
abstract class WithoutPassportTestCase extends TestCase
{
    protected function setUp(): void
    {
        if (class_exists(Passport::class)) {
            $this->markTestSkipped('Needs an install without laravel/passport.');
        }

        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            McpServiceProvider::class,
            SanctumServiceProvider::class,
            UmpireServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // The parent's Agent uses Passport's HasApiTokens and cannot load here.
        $app['config']->set('auth.providers.users.model', SanctumAgent::class);
    }

    protected function migrationPaths(): array
    {
        return [
            default_migration_path(),
            dirname(__DIR__).'/database/migrations',
            dirname(__DIR__).'/vendor/laravel/sanctum/database/migrations',
        ];
    }

    protected function defineRoutes($router): void
    {
        Mcp::web('mcp/sanctum/helpdesk', HelpdeskServer::class)->middleware('auth:sanctum');
    }
}
