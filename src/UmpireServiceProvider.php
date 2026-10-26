<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire;

use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Methods\CallTool;
use MujahidAbbas\Umpire\Audit\ToolCallInterceptor;
use MujahidAbbas\Umpire\Authorization\EnforcementCheck;
use MujahidAbbas\Umpire\Console\PurgeClientsCommand;
use MujahidAbbas\Umpire\Events\ToolFailed;
use MujahidAbbas\Umpire\Events\ToolInvoked;
use MujahidAbbas\Umpire\Listeners\RecordRegisteredClient;
use MujahidAbbas\Umpire\Listeners\RecordToolCall;

class UmpireServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/umpire.php', 'umpire');

        $this->app->singleton(ToolCallInterceptor::class);
        $this->app->singleton(EnforcementCheck::class);

        $this->app->afterResolving(Server::class, function (Server $server, Container $app): void {
            $app->make(ToolCallInterceptor::class)->remember($server, $app);
        });

        // CallTool resolves on every tools/call (execute_tools included), after the server's
        // boot(), so tools a server adds there are bound before they run.
        $this->app->afterResolving(CallTool::class, function (CallTool $method, Container $app): void {
            $app->make(ToolCallInterceptor::class)->intercept($app);
        });

        // Every MCP request resolves exactly one Method (tools/list, tools/call, ping...), after
        // the server's boot(). Refuse it if a tool's #[Authorize] would not be enforced.
        $this->app->afterResolving(Method::class, function (Method $method, Container $app): void {
            $app->make(EnforcementCheck::class)->verify($app->make(ToolCallInterceptor::class)->toolClassesFor($app));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $events = $this->app->make(Dispatcher::class);
        $events->listen([ToolInvoked::class, ToolFailed::class], RecordToolCall::class);
        // RequestHandled ships only in laravel/framework; a class name never autoloads it here.
        $events->listen(RequestHandled::class, RecordRegisteredClient::class);

        if ($this->app->runningInConsole()) {
            $this->commands([PurgeClientsCommand::class]);

            $this->publishes([
                __DIR__.'/../config/umpire.php' => $this->app->configPath('umpire.php'),
            ], 'umpire-config');

            $this->publishMigration('create_mcp_tool_calls_table.php');
            $this->publishMigration('create_mcp_registered_clients_table.php');
        }
    }

    /**
     * Publish a migration under the umpire-migrations tag, at most once. With
     * database.migrations.update_date_on_publish on, vendor:publish checks whether the target
     * exists and then gives it a fresh timestamp, so a second publish would duplicate it. A
     * migration already published is mapped to its own file instead, outside
     * publishesMigrations(), so even --force overwrites it in place.
     */
    protected function publishMigration(string $migration): void
    {
        $from = __DIR__.'/../database/migrations/'.$migration;
        $published = glob($this->app->databasePath('migrations/*_'.$migration)) ?: [];

        if ($published !== []) {
            $this->publishes([$from => $published[0]], 'umpire-migrations');

            return;
        }

        $this->publishesMigrations([
            $from => $this->app->databasePath('migrations/'.Carbon::now()->format('Y_m_d_His').'_'.$migration),
        ], 'umpire-migrations');
    }
}
