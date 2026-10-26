<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire;

use Illuminate\Container\Container;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Methods\CallTool;
use MujahidAbbas\Umpire\Audit\ToolCallInterceptor;

class UmpireServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ToolCallInterceptor::class);

        $this->app->afterResolving(Server::class, function (Server $server, Container $app): void {
            $app->make(ToolCallInterceptor::class)->remember($server, $app);
        });

        // CallTool resolves on every tools/call (execute_tools included), after the server's
        // boot(), so tools a server adds there are bound before they run.
        $this->app->afterResolving(CallTool::class, function (CallTool $method, Container $app): void {
            $app->make(ToolCallInterceptor::class)->intercept($app);
        });
    }
}
