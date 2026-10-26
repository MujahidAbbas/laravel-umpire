<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;
use MujahidAbbas\Umpire\Events\InvokingTool;
use MujahidAbbas\Umpire\Exceptions\AuthorizationNotEnforced;
use MujahidAbbas\Umpire\Tests\Fixtures\Models\Ticket;
use MujahidAbbas\Umpire\Tests\Fixtures\Policies\TicketPolicy;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\GuardedServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\MisconfiguredServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\AliasingRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\DeleteCustomerTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\ExportTicketHistoryTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\IssueGoodwillCreditTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\IssueRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\LookupTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\OverridingRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\SubclassOverridingRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\UnguardedRefundTool;

// #[Authorize] is enforced through shouldRegister(), which laravel/mcp asks before it lists,
// searches or calls a tool. A refused direct call is JSON-RPC -32602 "not found"; inside
// execute_tools it is an isError result. Neither reaches handle(), so neither is audited.

afterEach(function (): void {
    MisconfiguredServer::$tool = null;
    MisconfiguredServer::$inCatalog = false;
    TicketPolicy::$exporters = [];
});

function allowRefundsFor(Authenticatable $refunder): void
{
    Gate::define('issue-refunds', fn (Authenticatable $user): bool => $user->getAuthIdentifier() === $refunder->getAuthIdentifier());
}

it('lists and runs a tool for a user the Gate allows', function (string $guard): void {
    [$agent, $token] = agentWithToken($guard);
    allowRefundsFor($agent);

    expect(listedTools("mcp/{$guard}/guarded", $token))->toContain('lookup-ticket', 'issue-refund');

    $result = rpcResult(postMcp("mcp/{$guard}/guarded", toolCallMessage('issue-refund'), $token));

    expect($result['isError'])->toBeFalse()
        ->and($result['content'][0]['text'])->toBe('issue-refund done');
})->with('guards');

it('hides a tool from a user the Gate denies and refuses a direct call as not found', function (string $guard): void {
    Event::fake([InvokingTool::class]);
    [, $token] = agentWithToken($guard);
    allowRefundsFor(agent());

    expect(listedTools("mcp/{$guard}/guarded", $token))->toContain('lookup-ticket')->not->toContain('issue-refund');

    $error = postMcp("mcp/{$guard}/guarded", toolCallMessage('issue-refund'), $token, 400)[0]['error'];

    expect($error['code'])->toBe(-32602)
        ->and($error['message'])->toBe('Tool [issue-refund] not found.');
    Event::assertNotDispatched(InvokingTool::class);
})->with('guards');

it('lets search_tools find and execute_tools run a catalog tool for an allowed user', function (string $guard): void {
    [$agent, $token] = agentWithToken($guard);
    allowRefundsFor($agent);

    $search = toolSearchOutput(rpcResult(postMcp("mcp/{$guard}/guarded-catalog", toolCallMessage('search_tools', ['query' => 'refund']), $token)));
    $execute = rpcResult(postMcp("mcp/{$guard}/guarded-catalog", executeToolsMessage([['name' => 'issue-refund', 'arguments' => []]]), $token));

    expect(array_column($search['tools'], 'name'))->toBe(['issue-refund'])
        ->and($execute['isError'])->toBeFalse()
        ->and(toolSearchOutput($execute)['results'][0]['content'][0]['text'])->toBe('issue-refund done');
})->with('guards');

it('hides a catalog tool from search_tools and refuses it in execute_tools for a denied user', function (string $guard): void {
    Event::fake([InvokingTool::class]);
    [, $token] = agentWithToken($guard);
    allowRefundsFor(agent());

    $search = toolSearchOutput(rpcResult(postMcp("mcp/{$guard}/guarded-catalog", toolCallMessage('search_tools', ['query' => 'refund']), $token)));
    $execute = rpcResult(postMcp("mcp/{$guard}/guarded-catalog", executeToolsMessage([['name' => 'issue-refund', 'arguments' => []]]), $token));

    expect($search['tools'])->toBe([])
        ->and($execute['isError'])->toBeTrue()
        ->and(toolSearchOutput($execute)['results'][0]['content'][0]['text'])->toBe('Tool [issue-refund] is not available.');
    Event::assertNotDispatched(InvokingTool::class);
})->with('guards');

it('shows a guest only the tools without #[Authorize]', function (): void {
    allowRefundsFor(agent());
    Gate::define('manage-customers', fn (?Authenticatable $user): bool => true);
    Gate::define('delete-records', fn (?Authenticatable $user): bool => true);

    expect(listedTools('mcp/guarded'))->toBe(['lookup-ticket', 'delete-customer']);
});

it('requires every #[Authorize] on the tool, passing each its arguments', function (bool $manage, bool $delete, bool $registered): void {
    Gate::define('manage-customers', fn (Authenticatable $user): bool => $manage);
    Gate::define('delete-records', fn (Authenticatable $user, string $records): bool => $delete && $records === 'customers');

    $tools = GuardedServer::actingAs(agent())->tools();

    $registered
        ? $tools->assertRegistered(DeleteCustomerTool::class)
        : $tools->assertNotRegistered(DeleteCustomerTool::class);
})->with([
    'both allow' => [true, true, true],
    'the first denies' => [false, true, false],
    'the second denies' => [true, false, false],
]);

it('passes a class name to the Gate so the model\'s policy decides', function (): void {
    Gate::policy(Ticket::class, TicketPolicy::class);
    $exporter = agent();
    TicketPolicy::$exporters = [$exporter->getKey()];

    GuardedServer::actingAs($exporter)->tools()->assertRegistered(ExportTicketHistoryTool::class);
    GuardedServer::actingAs(agent())->tools()->assertNotRegistered(ExportTicketHistoryTool::class);
});

it('enforces a parent tool\'s #[Authorize] on a subclass that declares none', function (): void {
    $refunder = agent();
    allowRefundsFor($refunder);

    GuardedServer::actingAs($refunder)->tool(IssueGoodwillCreditTool::class)->assertSee('issue-goodwill-credit done');
    GuardedServer::actingAs(agent())->tool(IssueGoodwillCreditTool::class)->assertNotRegistered();
    GuardedServer::actingAs(agent())->tool(IssueRefundTool::class)->assertNotRegistered();
});

it('refuses every request on a server whose #[Authorize] would not be enforced', function (string $tool, string $reason): void {
    MisconfiguredServer::$tool = $tool;

    expect(fn () => MisconfiguredServer::tools())->toThrow(AuthorizationNotEnforced::class, $reason)
        ->and(fn () => MisconfiguredServer::tool(LookupTicketTool::class))->toThrow(AuthorizationNotEnforced::class, $reason);
})->with([
    'attribute without the trait' => [UnguardedRefundTool::class, 'does not use the'],
    'its own shouldRegister()' => [OverridingRefundTool::class, 'defines its own shouldRegister()'],
    'the trait method aliased and wrapped' => [AliasingRefundTool::class, 'defines its own shouldRegister()'],
    'a subclass overriding shouldRegister()' => [SubclassOverridingRefundTool::class, 'defines its own shouldRegister()'],
]);

it('refuses a server whose ToolSearch catalog holds such a tool', function (): void {
    MisconfiguredServer::$tool = UnguardedRefundTool::class;
    MisconfiguredServer::$inCatalog = true;

    MisconfiguredServer::tool(LookupTicketTool::class);
})->throws(AuthorizationNotEnforced::class, UnguardedRefundTool::class);

it('answers tools/list on such a server with a JSON-RPC error and reports the reason', function (): void {
    config(['app.debug' => false]);
    Exceptions::fake();
    MisconfiguredServer::$tool = OverridingRefundTool::class;

    $error = postMcp('mcp/misconfigured', listToolsMessage(), status: 500)[0]['error'];

    expect($error['code'])->toBe(-32603);
    Exceptions::assertReported(fn (AuthorizationNotEnforced $exception): bool => str_contains($exception->getMessage(), OverridingRefundTool::class));
});
