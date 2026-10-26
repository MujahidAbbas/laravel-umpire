<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Audit;

use BadMethodCallException;
use Closure;
use Generator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Events\ToolCallFailed;
use MujahidAbbas\Umpire\Events\ToolCallStarting;
use MujahidAbbas\Umpire\Events\ToolCallSucceeded;
use MujahidAbbas\Umpire\Exceptions\ToolCannotBeAudited;
use ReflectionProperty;
use RuntimeException;
use Throwable;
use Traversable;
use WeakMap;

/**
 * Wraps each tool's handle() in a container method binding, so direct tool calls and tool
 * search (execute_tools) are both observed: core invokes both through Container::call().
 *
 * @internal
 */
class ToolCallInterceptor
{
    /**
     * The last server resolved in each container (keyed weakly, so Octane sandboxes don't leak).
     *
     * @var WeakMap<Container, Server>
     */
    protected WeakMap $servers;

    /**
     * The tool classes this interceptor has bound in each container.
     *
     * @var WeakMap<Container, array<string, true>>
     */
    protected WeakMap $bound;

    public function __construct()
    {
        $this->servers = new WeakMap;
        $this->bound = new WeakMap;
    }

    /**
     * Remember the server that will handle the next tool call.
     */
    public function remember(Server $server, Container $app): void
    {
        $this->servers[$app] = $server;
    }

    /**
     * Bind the interceptor on handle() for every tool the server declares, including tools added
     * in boot(). Fails closed: a tool whose handle() already has another binding cannot be
     * audited, so the call is refused instead of running unrecorded.
     *
     * @throws ToolCannotBeAudited
     */
    public function intercept(Container $app): void
    {
        $server = $this->servers[$app] ?? null;

        if ($server === null) {
            return;
        }

        $bound = $this->bound[$app] ?? [];

        $pending = array_filter(
            $this->toolClasses($server),
            fn (string $class): bool => ! (isset($bound[$class]) && $app->hasMethodBinding("{$class}@handle")),
        );

        foreach ($pending as $class) {
            if ($app->hasMethodBinding("{$class}@handle")) {
                throw ToolCannotBeAudited::becauseHandleIsBound($class);
            }
        }

        foreach ($pending as $class) {
            $app->bindMethod([$class, 'handle'], fn (Tool $tool, Container $app): mixed => $this->call($tool, $app));

            $bound[$class] = true;
        }

        $this->bound[$app] = $bound;
    }

    /**
     * Read the tool classes from the server's protected $tools, flattening ToolSearch groups.
     *
     * @return list<string>
     */
    protected function toolClasses(Server $server): array
    {
        /** @var array<int|string, Tool|string|array<int, Tool|string>> $tools */
        $tools = (new ReflectionProperty(Server::class, 'tools'))->getValue($server);

        $classes = [];

        foreach ($tools as $tool) {
            foreach (is_array($tool) ? $tool : [$tool] as $entry) {
                $classes[] = is_string($entry) ? $entry : $entry::class;
            }
        }

        return array_values(array_unique($classes));
    }

    protected function call(Tool $tool, Container $app): mixed
    {
        $events = $app->make(Dispatcher::class);
        $name = $tool->name();

        // Captured now: core forgets mcp.request before a streamed response is iterated.
        $arguments = $app->bound('mcp.request') ? $this->request($app)->all() : [];

        $events->dispatch(new ToolCallStarting($name, $arguments));

        $startedAt = hrtime(true);

        try {
            $handle = [$tool, 'handle'];

            if (! is_callable($handle)) {
                throw new BadMethodCallException(sprintf('Tool [%s] does not define a handle() method.', $tool::class));
            }

            // A closure, not [$tool, 'handle'], so Container::call() skips this method binding.
            $result = $app->call(Closure::fromCallable($handle));
        } catch (Throwable $exception) {
            $events->dispatch(new ToolCallFailed($name, $arguments, $this->elapsedMs($startedAt), $exception));

            throw $exception;
        }

        if ($result instanceof Traversable) {
            return $this->stream($result, $events, $name, $arguments, $startedAt);
        }

        $events->dispatch(new ToolCallSucceeded($name, $arguments, $this->elapsedMs($startedAt), $this->isError($result)));

        return $result;
    }

    /**
     * Pass a streamed response through untouched, settling the call once it is exhausted.
     *
     * @param  Traversable<mixed, mixed>  $responses
     * @param  array<string, mixed>  $arguments
     * @return Generator<mixed, mixed, mixed, mixed>
     */
    protected function stream(Traversable $responses, Dispatcher $events, string $name, array $arguments, int $startedAt): Generator
    {
        $isError = false;
        $completed = false;
        $failed = false;

        try {
            foreach ($responses as $key => $response) {
                $isError = $isError || $this->isError($response);

                yield $key => $response;
            }

            $completed = true;
        } catch (Throwable $exception) {
            $failed = true;

            $events->dispatch(new ToolCallFailed($name, $arguments, $this->elapsedMs($startedAt), $exception));

            throw $exception;
        } finally {
            if (! $completed && ! $failed) {
                $events->dispatch(new ToolCallFailed(
                    $name, $arguments, $this->elapsedMs($startedAt),
                    new RuntimeException('The tool stream was abandoned before it finished.'),
                ));
            }
        }

        $events->dispatch(new ToolCallSucceeded($name, $arguments, $this->elapsedMs($startedAt), $isError));

        return $responses instanceof Generator ? $responses->getReturn() : null;
    }

    protected function isError(mixed $result): bool
    {
        return match (true) {
            $result instanceof Response => $result->isError(),
            $result instanceof ResponseFactory => $result->responses()->contains(fn (Response $response): bool => $response->isError()),
            is_array($result) => collect($result)->contains(fn (mixed $item): bool => $this->isError($item)),
            default => false,
        };
    }

    protected function request(Container $app): Request
    {
        /** @var Request */
        return $app->make('mcp.request');
    }

    protected function elapsedMs(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1e6, 3);
    }
}
