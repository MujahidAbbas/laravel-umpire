<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Passport\Passport;
use MujahidAbbas\Umpire\Events\InvokingTool;
use MujahidAbbas\Umpire\Events\ToolFailed;
use MujahidAbbas\Umpire\Events\ToolInvoked;
use MujahidAbbas\Umpire\Exceptions\ToolStreamAbandoned;
use MujahidAbbas\Umpire\Models\ToolCall;
use MujahidAbbas\Umpire\Tests\Fixtures\Models\Agent;
use MujahidAbbas\Umpire\Tests\Fixtures\Models\SanctumAgent;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\HelpdeskServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\LookupTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\SummarizeQueueTool;
use MujahidAbbas\Umpire\Umpire;

// The audit table is written by a listener on ToolInvoked and ToolFailed, so it records exactly
// what the events carry, on both call paths. A failed write is reported and changes nothing.

beforeEach(function (): void {
    config(['umpire.audit.enabled' => true]);
    Exceptions::fake();
});

afterEach(function (): void {
    Umpire::resolveTenantUsing(null);
});

dataset('call paths', [
    'direct call' => 'direct',
    'execute_tools' => 'execute_tools',
]);

/**
 * Call a tool over HTTP, directly or through the catalog's execute_tools, and return the tool's
 * own result.
 *
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>
 */
function callTool(string $path, string $tool, array $arguments = [], ?string $guard = null, ?string $token = null): array
{
    $prefix = $guard === null ? 'mcp' : "mcp/{$guard}";

    if ($path === 'direct') {
        return rpcResult(postMcp("{$prefix}/helpdesk", toolCallMessage($tool, $arguments), $token));
    }

    $result = rpcResult(postMcp("{$prefix}/catalog", executeToolsMessage([['name' => $tool, 'arguments' => $arguments]]), $token));

    return toolSearchOutput($result)['results'][0];
}

it('records a call with the id its events carry', function (string $path): void {
    $started = null;
    Event::listen(InvokingTool::class, function (InvokingTool $event) use (&$started): void {
        $started = $event;
    });
    $ticket = fake()->uuid();

    callTool($path, 'lookup-ticket', ['ticket' => $ticket]);

    $row = ToolCall::query()->sole();

    expect($row->id)->toBe($started->id)
        ->and($row->tool)->toBe('lookup-ticket')
        ->and($row->arguments)->toBe(['ticket' => $ticket])
        ->and($row->user_type)->toBeNull()
        ->and($row->user_id)->toBeNull()
        ->and($row->client_id)->toBeNull()
        ->and($row->token_id)->toBeNull()
        ->and($row->tenant_id)->toBeNull()
        ->and($row->is_error)->toBeFalse()
        ->and($row->exception)->toBeNull()
        ->and($row->duration_ms)->toBeFloat()->toBeGreaterThanOrEqual(0.0)
        ->and($row->created_at->isToday())->toBeTrue();
})->with('call paths');

it('records how each call settled', function (string $path, string $tool, array $arguments, bool $isError, ?string $exception): void {
    $result = callTool($path, $tool, $arguments);

    $row = ToolCall::query()->sole();

    expect($result['isError'])->toBe($isError)
        ->and($row->tool)->toBe($tool)
        ->and($row->is_error)->toBe($isError)
        ->and($row->exception)->toBe($exception);
})->with('call paths')->with([
    'success' => ['lookup-ticket', [], false, null],
    'an isError result' => ['ticket-stats', [], true, null],
    'an exception' => ['escalate-ticket', [], true, RuntimeException::class],
    'a validation failure' => ['create-ticket', [], true, ValidationException::class],
    'a completed stream' => ['summarize-queue', ['tickets' => 2], false, null],
    'an exception mid-stream' => ['export-tickets', [], true, RuntimeException::class],
]);

it('records a stream abandoned before it finishes', function (): void {
    // execute_tools always drains a stream, so only a direct call can be abandoned.
    app()->make(HelpdeskServer::class, ['transport' => new FakeTransporter]);
    app()->make(CallTool::class);
    app()->instance('mcp.request', new Request(['tickets' => 3]));

    $stream = app()->call([app()->make(SummarizeQueueTool::class), 'handle']);
    $stream->current();
    unset($stream);

    $row = ToolCall::query()->sole();

    expect($row->tool)->toBe('summarize-queue')
        ->and($row->arguments)->toBe(['tickets' => 3])
        ->and($row->is_error)->toBeTrue()
        ->and($row->exception)->toBe(ToolStreamAbandoned::class);
});

it('stores the redacted arguments', function (string $path): void {
    $email = fake()->safeEmail();

    callTool($path, 'verify-customer', [
        'email' => $email,
        'security_answer' => fake()->word(),
        'profile' => ['password' => fake()->password()],
    ]);

    // toEqual: MySQL's json type does not keep key order.
    expect(ToolCall::query()->sole()->arguments)->toEqual([
        'email' => $email,
        'security_answer' => '[redacted]',
        'profile' => ['password' => '[redacted]'],
    ]);
})->with('call paths');

it('stores the user, client and token of a Passport or Sanctum call', function (string $guard, string $path): void {
    [$agent, $token] = agentWithToken($guard);

    callTool($path, 'lookup-ticket', guard: $guard, token: $token);

    $issued = $guard === 'passport' ? Passport::token()->newQuery()->sole() : $agent->tokens()->sole();
    [$clientId, $tokenId] = $guard === 'passport'
        ? [(string) $issued->client_id, (string) $issued->getKey()]
        : [(string) $issued->getKey(), (string) $issued->getKey()];
    $row = ToolCall::query()->sole();

    expect($row->user_type)->toBe($guard === 'passport' ? Agent::class : SanctumAgent::class)
        ->and($row->user_id)->toBe((string) $agent->getKey())
        ->and($row->client_id)->toBe($clientId)->not->toBe('')
        ->and($row->token_id)->toBe($tokenId)->not->toBe('');
})->with('guards')->with('call paths');

it('stores the class name and identifier of a user that is not an Eloquent model', function (): void {
    $id = fake()->uuid();

    HelpdeskServer::actingAs(new GenericUser(['id' => $id]))->tool(LookupTicketTool::class);

    $row = ToolCall::query()->sole();

    expect($row->user_type)->toBe(GenericUser::class)
        ->and($row->user_id)->toBe($id);
});

it('stores the tenant the hook resolves from the settling event', function (string $path): void {
    $seen = [];
    Umpire::resolveTenantUsing(function (ToolInvoked|ToolFailed $event) use (&$seen): int|string {
        $seen[] = $event::class;

        return $event instanceof ToolFailed ? 42 : 'northwind-support';
    });

    callTool($path, 'lookup-ticket');
    callTool($path, 'escalate-ticket');

    expect(ToolCall::query()->orderBy('id')->pluck('tenant_id', 'tool')->all())->toBe([
        'lookup-ticket' => 'northwind-support',
        'escalate-ticket' => '42',
    ])->and($seen)->toBe([ToolInvoked::class, ToolFailed::class]);
})->with('call paths');

it('writes nothing while the audit table is disabled', function (string $path): void {
    config(['umpire.audit.enabled' => false]);

    callTool($path, 'lookup-ticket');
    callTool($path, 'escalate-ticket');

    expect(ToolCall::query()->count())->toBe(0);
})->with('call paths');

it('reports a failed write and leaves the tool\'s response unchanged', function (string $path, Closure $break, string $reported): void {
    // The first query opens the test's transaction, so the write below happens inside one.
    expect(ToolCall::query()->count())->toBe(0);
    $break();

    $result = callTool($path, 'lookup-ticket');

    // The connection still answers: on PostgreSQL a failed insert outside a savepoint would
    // abort the surrounding transaction (here, the test's own).
    expect($result['isError'])->toBeFalse()
        ->and($result['content'][0]['text'])->toBe('lookup-ticket done')
        ->and(DB::scalar('select 1'))->toBe(1);
    Exceptions::assertReportedCount(1);
    Exceptions::assertReported($reported);
})->with('call paths')->with([
    'the table is missing' => [fn () => config(['umpire.audit.table' => 'mcp_tool_calls_missing']), QueryException::class],
    'the tenant hook throws' => [fn () => Umpire::resolveTenantUsing(fn (): never => throw new LogicException('No tenant in scope.')), LogicException::class],
]);

it('prunes rows older than the retention with model:prune --model', function (): void {
    config(['umpire.audit.retention_days' => 90]);

    $this->travelTo(now()->subDays(91));
    HelpdeskServer::tool(LookupTicketTool::class);
    $this->travelTo(now()->addDays(2));
    HelpdeskServer::tool(SummarizeQueueTool::class);
    $this->travelBack();
    HelpdeskServer::tool(LookupTicketTool::class);

    $this->artisan('model:prune', ['--model' => [ToolCall::class]])->assertSuccessful();

    expect(ToolCall::query()->orderBy('id')->pluck('tool')->all())->toBe(['summarize-queue', 'lookup-ticket']);
});

it('keeps every row when the retention is null', function (): void {
    config(['umpire.audit.retention_days' => null]);

    $this->travelTo(now()->subYears(3));
    HelpdeskServer::tool(LookupTicketTool::class);
    $this->travelBack();

    $this->artisan('model:prune', ['--model' => [ToolCall::class]])->assertSuccessful();

    expect(ToolCall::query()->count())->toBe(1);
});
