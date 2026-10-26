<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Audit;

use BadMethodCallException;
use Closure;
use Generator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Attributes\Redact;
use MujahidAbbas\Umpire\Events\InvokingTool;
use MujahidAbbas\Umpire\Events\ToolFailed;
use MujahidAbbas\Umpire\Events\ToolInvoked;
use MujahidAbbas\Umpire\Exceptions\ToolCannotBeAudited;
use MujahidAbbas\Umpire\Exceptions\ToolStreamAbandoned;
use MujahidAbbas\Umpire\Support\Attributes;
use ReflectionProperty;
use Throwable;
use Traversable;
use WeakMap;

/**
 * Wraps each tool's handle() in a container method binding, so direct tool calls and tool
 * search (execute_tools) are both observed: core invokes both through Container::call().
 *
 * @internal
 *
 * @phpstan-type ToolCall array{id: string, tool: string, arguments: array<string, mixed>, user: ?Authenticatable, clientId: ?string, tokenId: ?string}
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
        $bound = $this->bound[$app] ?? [];

        $pending = array_filter(
            $this->toolClassesFor($app),
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
     * The tool classes of the server that will handle the current request, including tools added
     * in boot(), or none when no server has been resolved in this container.
     *
     * @return list<string>
     */
    public function toolClassesFor(Container $app): array
    {
        $server = $this->servers[$app] ?? null;

        return $server === null ? [] : $this->toolClasses($server);
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

        // Captured now: core forgets mcp.request before a streamed response is iterated.
        $call = $this->describe($tool, $app);

        $events->dispatch(new InvokingTool(...$call));

        $startedAt = hrtime(true);

        try {
            $handle = [$tool, 'handle'];

            if (! is_callable($handle)) {
                throw new BadMethodCallException(sprintf('Tool [%s] does not define a handle() method.', $tool::class));
            }

            // A closure, not [$tool, 'handle'], so Container::call() skips this method binding.
            $result = $app->call(Closure::fromCallable($handle));
        } catch (Throwable $exception) {
            $events->dispatch(new ToolFailed(...$call, durationMs: $this->elapsedMs($startedAt), exception: $exception));

            throw $exception;
        }

        if ($result instanceof Traversable) {
            return $this->stream($result, $events, $call, $startedAt);
        }

        $events->dispatch(new ToolInvoked(...$call, durationMs: $this->elapsedMs($startedAt), isError: $this->isError($result)));

        return $result;
    }

    /**
     * Pass a streamed response through untouched, settling the call once it is exhausted.
     *
     * @param  Traversable<mixed, mixed>  $responses
     * @param  ToolCall  $call
     * @return Generator<mixed, mixed, mixed, mixed>
     */
    protected function stream(Traversable $responses, Dispatcher $events, array $call, int $startedAt): Generator
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

            $events->dispatch(new ToolFailed(...$call, durationMs: $this->elapsedMs($startedAt), exception: $exception));

            throw $exception;
        } finally {
            if (! $completed && ! $failed) {
                $events->dispatch(new ToolFailed(...$call, durationMs: $this->elapsedMs($startedAt), exception: ToolStreamAbandoned::make()));
            }
        }

        $events->dispatch(new ToolInvoked(...$call, durationMs: $this->elapsedMs($startedAt), isError: $isError));

        return $responses instanceof Generator ? $responses->getReturn() : null;
    }

    /**
     * The payload every event of this call shares.
     *
     * @return ToolCall
     */
    protected function describe(Tool $tool, Container $app): array
    {
        $request = $app->bound('mcp.request') ? $this->request($app) : null;
        $user = $request?->user();

        [$clientId, $tokenId] = $this->credentials($user);

        return [
            'id' => (string) Str::ulid(),
            'tool' => $tool->name(),
            'arguments' => $this->redact($request?->all() ?? [], $this->redactedKeys($tool, $app)),
            'user' => $user,
            'clientId' => $clientId,
            'tokenId' => $tokenId,
        ];
    }

    /**
     * The client and token the user authenticated with, read without requiring Passport or Sanctum.
     *
     * @return array{?string, ?string}
     */
    protected function credentials(?Authenticatable $user): array
    {
        if ($user === null) {
            return [null, null];
        }

        $traits = class_uses_recursive($user);

        // Passport keeps the access token's attributes: oauth_client_id, oauth_access_token_id.
        if (isset($traits['Laravel\Passport\HasApiTokens']) && method_exists($user, 'token')) {
            $token = $user->token();

            return [$this->identifier($token->oauth_client_id ?? null), $this->identifier($token->oauth_access_token_id ?? null)];
        }

        // Sanctum: the personal access token is both client and token. Cookie auth has neither.
        if (isset($traits['Laravel\Sanctum\HasApiTokens']) && method_exists($user, 'currentAccessToken')) {
            $token = $user->currentAccessToken();
            $id = $token instanceof Model ? $this->identifier($token->getKey()) : null;

            return [$id, $id];
        }

        return [null, null];
    }

    protected function identifier(mixed $value): ?string
    {
        return is_string($value) || is_int($value) ? (string) $value : null;
    }

    /**
     * The global umpire.redact keys plus the tool's #[Redact] keys, lower-cased.
     *
     * @return array<string, true>
     */
    protected function redactedKeys(Tool $tool, Container $app): array
    {
        /** @var list<string> $keys */
        $keys = (array) $app->make('config')->get('umpire.redact', []);

        foreach (Attributes::all($tool, Redact::class) as $redact) {
            array_push($keys, ...$redact->keys);
        }

        return array_fill_keys(array_map(strtolower(...), $keys), true);
    }

    /**
     * Replace the value of every matching key, at any depth.
     *
     * @template TKey of array-key
     *
     * @param  array<TKey, mixed>  $arguments
     * @param  array<string, true>  $keys
     * @return array<TKey, mixed>
     */
    protected function redact(array $arguments, array $keys): array
    {
        foreach ($arguments as $key => $value) {
            if (isset($keys[strtolower((string) $key)])) {
                $arguments[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $arguments[$key] = $this->redact($value, $keys);
            }
        }

        return $arguments;
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
