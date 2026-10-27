<?php

declare(strict_types=1);

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Gate;
use MujahidAbbas\Umpire\Events\InvokingTool;
use MujahidAbbas\Umpire\Exceptions\AuthorizationNotEnforced;
use MujahidAbbas\Umpire\Tests\Fixtures\Prompts\DraftRefundReplyPrompt;
use MujahidAbbas\Umpire\Tests\Fixtures\Prompts\OverridingRefundReplyPrompt;
use MujahidAbbas\Umpire\Tests\Fixtures\Prompts\UnguardedRefundReplyPrompt;
use MujahidAbbas\Umpire\Tests\Fixtures\Resources\CustomerRecordResource;
use MujahidAbbas\Umpire\Tests\Fixtures\Resources\OverridingRefundPolicyResource;
use MujahidAbbas\Umpire\Tests\Fixtures\Resources\RefundPolicyResource;
use MujahidAbbas\Umpire\Tests\Fixtures\Resources\UnguardedCustomerRecordResource;
use MujahidAbbas\Umpire\Tests\Fixtures\Resources\UnguardedRefundPolicyResource;
use MujahidAbbas\Umpire\Tests\Fixtures\Resources\VipCustomerListResource;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\GuardedServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\MisconfiguredServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\LookupTicketTool;
use PHPUnit\Framework\ExpectationFailedException;

// #[Authorize] on a resource, resource template or prompt goes through the same shouldRegister()
// as on a tool: laravel/mcp asks it before it lists, reads or gets one, and before
// completion/complete resolves one. A refused read or get is JSON-RPC -32602 "not found". The
// audit stays tool-only.

afterEach(function (): void {
    MisconfiguredServer::$resource = null;
    MisconfiguredServer::$prompt = null;
});

/**
 * Let one user through every ability the guarded resources and prompts declare.
 */
function allowContentFor(Authenticatable $allowed): void
{
    foreach (['issue-refunds', 'view-customers', 'view-vip-customers'] as $ability) {
        Gate::define($ability, fn (Authenticatable $user): bool => $user->getAuthIdentifier() === $allowed->getAuthIdentifier());
    }
}

/**
 * One field of every item a resources/list, resources/templates/list or prompts/list call returned.
 *
 * @return list<string>
 */
function listedBy(string $uri, string $method, string $field, ?string $token = null): array
{
    $key = ['resources/list' => 'resources', 'resources/templates/list' => 'resourceTemplates', 'prompts/list' => 'prompts'][$method];

    return array_column(rpcResult(postMcp($uri, rpcMessage($method), $token))[$key], $field);
}

it('lists, reads and gets guarded resources and prompts for a user the Gate allows', function (string $guard): void {
    Event::fake([InvokingTool::class]);
    [$agent, $token] = agentWithToken($guard);
    allowContentFor($agent);
    $server = "mcp/{$guard}/guarded";

    expect(listedBy($server, 'resources/list', 'uri', $token))
        ->toBe(['helpdesk://queue/summary', 'helpdesk://policies/refunds', 'helpdesk://customers/vip'])
        ->and(listedBy($server, 'resources/templates/list', 'uriTemplate', $token))->toBe(['helpdesk://customers/{id}'])
        ->and(listedBy($server, 'prompts/list', 'name', $token))->toBe(['summarize-ticket', 'draft-refund-reply']);

    $policy = rpcResult(postMcp($server, rpcMessage('resources/read', ['uri' => 'helpdesk://policies/refunds']), $token));
    $record = rpcResult(postMcp($server, rpcMessage('resources/read', ['uri' => 'helpdesk://customers/C-1001']), $token));
    $prompt = rpcResult(postMcp($server, rpcMessage('prompts/get', ['name' => 'draft-refund-reply']), $token));

    expect($policy['contents'][0]['text'])->toBe('refund-policy contents')
        ->and($record['contents'][0]['text'])->toBe('customer-record C-1001')
        ->and($prompt['messages'][0]['content']['text'])->toBe('draft-refund-reply done');
    Event::assertNotDispatched(InvokingTool::class);
})->with('guards');

it('hides guarded resources, templates and prompts from a user the Gate denies', function (string $guard): void {
    [, $token] = agentWithToken($guard);
    allowContentFor(agent());
    $server = "mcp/{$guard}/guarded";

    expect(listedBy($server, 'resources/list', 'uri', $token))->toBe(['helpdesk://queue/summary'])
        ->and(listedBy($server, 'resources/templates/list', 'uriTemplate', $token))->toBe([])
        ->and(listedBy($server, 'prompts/list', 'name', $token))->toBe(['summarize-ticket']);
})->with('guards');

it('refuses a denied resources/read or prompts/get as not found', function (string $guard, string $method, array $params, string $message): void {
    [, $token] = agentWithToken($guard);
    allowContentFor(agent());

    $error = postMcp("mcp/{$guard}/guarded", rpcMessage($method, $params), $token, 400)[0]['error'];

    expect($error['code'])->toBe(-32602)
        ->and($error['message'])->toBe($message);
})->with('guards')->with([
    'a resource' => ['resources/read', ['uri' => 'helpdesk://policies/refunds'], 'Resource [helpdesk://policies/refunds] not found.'],
    'a resource template' => ['resources/read', ['uri' => 'helpdesk://customers/C-1001'], 'Resource [helpdesk://customers/C-1001] not found.'],
    'a prompt' => ['prompts/get', ['name' => 'draft-refund-reply'], 'Prompt [draft-refund-reply] not found.'],
]);

it('refuses completion/complete for a resource or prompt the Gate denies', function (): void {
    $allowed = agent();
    allowContentFor($allowed);

    GuardedServer::actingAs($allowed)->completion(DraftRefundReplyPrompt::class, 'reference')->assertHasNoErrors();
    GuardedServer::actingAs($allowed)->completion(CustomerRecordResource::class, 'id')->assertHasNoErrors();

    GuardedServer::actingAs(agent())->completion(DraftRefundReplyPrompt::class, 'reference')
        ->assertHasErrors(['Prompt [draft-refund-reply] not found.']);
    GuardedServer::actingAs(agent())->completion(CustomerRecordResource::class, 'id')
        ->assertHasErrors(['Resource [helpdesk://customers/{id}] not found.']);
});

it('serves a refused resource\'s URI from an allowed template that matches it', function (): void {
    // laravel/mcp looks for a static resource first and then tries the templates, so hiding
    // helpdesk://customers/vip leaves helpdesk://customers/{id} to answer for it.
    Gate::define('view-customers', fn (Authenticatable $user): bool => true);
    Gate::define('view-vip-customers', fn (Authenticatable $user): bool => false);

    GuardedServer::actingAs(agent())->resources()->assertNotRegistered(VipCustomerListResource::class);
    GuardedServer::actingAs(agent())->resource(VipCustomerListResource::class)
        ->assertSee('customer-record vip')
        ->assertDontSee('vip-customer-list contents');
});

it('needs assertHasErrors() with the expanded URI to test a refused template', function (): void {
    GuardedServer::actingAs(agent())->resource(RefundPolicyResource::class)->assertNotRegistered();
    GuardedServer::actingAs(agent())->prompt(DraftRefundReplyPrompt::class)->assertNotRegistered();

    // laravel/mcp's assertNotRegistered() looks for the template, the error names the URI read.
    $template = GuardedServer::actingAs(agent())->resource(CustomerRecordResource::class, ['id' => 'C-1001']);

    $template->assertHasErrors(['Resource [helpdesk://customers/C-1001] not found.']);
    expect(fn () => $template->assertNotRegistered())->toThrow(ExpectationFailedException::class);
});

it('refuses every request on a server whose resource or prompt #[Authorize] would not be enforced', function (string $slot, string $class, string $reason): void {
    MisconfiguredServer::${$slot} = $class;

    expect(fn () => MisconfiguredServer::tools())->toThrow(AuthorizationNotEnforced::class, $reason)
        ->and(fn () => MisconfiguredServer::resources())->toThrow(AuthorizationNotEnforced::class, $class)
        ->and(fn () => MisconfiguredServer::prompts())->toThrow(AuthorizationNotEnforced::class, $class)
        ->and(fn () => MisconfiguredServer::tool(LookupTicketTool::class))->toThrow(AuthorizationNotEnforced::class, $reason);
})->with([
    'a resource without the trait' => ['resource', UnguardedRefundPolicyResource::class, 'does not use the'],
    'a resource template without the trait' => ['resource', UnguardedCustomerRecordResource::class, 'does not use the'],
    'a resource with its own shouldRegister()' => ['resource', OverridingRefundPolicyResource::class, 'defines its own shouldRegister()'],
    'a prompt without the trait' => ['prompt', UnguardedRefundReplyPrompt::class, 'does not use the'],
    'a prompt with its own shouldRegister()' => ['prompt', OverridingRefundReplyPrompt::class, 'defines its own shouldRegister()'],
]);

it('checks a resource the server registers as an instance', function (): void {
    MisconfiguredServer::$resource = new UnguardedRefundPolicyResource;

    MisconfiguredServer::tools();
})->throws(AuthorizationNotEnforced::class, UnguardedRefundPolicyResource::class);

it('answers prompts/list on such a server with a JSON-RPC error and reports the reason', function (): void {
    config(['app.debug' => false]);
    Exceptions::fake();
    MisconfiguredServer::$prompt = OverridingRefundReplyPrompt::class;

    $error = postMcp('mcp/misconfigured', rpcMessage('prompts/list'), status: 500)[0]['error'];

    expect($error['code'])->toBe(-32603);
    Exceptions::assertReported(fn (AuthorizationNotEnforced $exception): bool => str_contains($exception->getMessage(), OverridingRefundReplyPrompt::class));
});
