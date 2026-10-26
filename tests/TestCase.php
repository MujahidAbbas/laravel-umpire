<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests;

use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\McpServiceProvider;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\CatalogServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\DynamicServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\HelpdeskServer;
use MujahidAbbas\Umpire\UmpireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            McpServiceProvider::class,
            UmpireServiceProvider::class,
        ];
    }

    protected function defineRoutes($router): void
    {
        Mcp::web('mcp/helpdesk', HelpdeskServer::class);
        Mcp::web('mcp/catalog', CatalogServer::class);
        Mcp::web('mcp/dynamic', DynamicServer::class);
    }
}
