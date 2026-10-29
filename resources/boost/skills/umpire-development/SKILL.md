---
name: umpire-development
description: "Sets up and works with Umpire for Laravel (mujahidabbas/laravel-umpire) on laravel/mcp servers. Activates when restricting MCP tools, resources or prompts to some users with Gate abilities or policies; adding #[Authorize] or AuthorizesWithGate to a tool, resource or prompt, or #[Redact] to a tool; listening for InvokingTool, ToolInvoked or ToolFailed; enabling, querying, pruning or tenant-scoping the mcp_tool_calls audit table or the ToolCall model; cleaning up OAuth clients left by MCP dynamic client registration with mcp:purge-clients; writing tests for protected or audited tools; or debugging AuthorizationNotEnforced, ToolCannotBeAudited, a tool, resource or prompt missing from its list, or a QueryException on /oauth/register."
license: MIT
---
# Umpire for Laravel

Umpire adds three things to laravel/mcp servers: per-tool authorization through the Gate, tool-call events with an optional audit table, and a purge for OAuth clients that MCP dynamic client registration left behind. Requires PHP 8.3+, Laravel 13 and laravel/mcp 1.1+. The registration marker and the purge need Passport; authorization and the audit work with any guard.

## Installation

```bash
composer require mujahidabbas/laravel-umpire
php artisan vendor:publish --tag=umpire-migrations
php artisan migrate
```

Run the migrations even when the audit table stays off: the registration marker (`mcp_registered_clients`, on Passport's connection) records by default, and without its table every registration reports a `QueryException`. Publish `config/umpire.php` with `--tag=umpire-config` only to change a default.

## Protect a tool

1. Define the ability: `Gate::define('issue-refunds', fn (User $user): bool => $user->is_supervisor);` in a service provider's `boot()`, or a policy method.
2. Add the attribute and the trait to the tool:

```php
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

#[Authorize('issue-refunds')]
class IssueRefundTool extends Tool
{
    use AuthorizesWithGate;

    public function handle(Request $request): Response
    {
        return Response::text('Refund queued for '.$request->string('reference')->value().'.');
    }
}
```

- Arguments after the ability go to the Gate as with the `can` middleware. A class name selects a policy: `#[Authorize('export', Ticket::class)]` calls `TicketPolicy::export($user)`.
- Repeat the attribute to require several abilities; all must pass. Attributes on parent classes apply to subclasses.
- A guest is denied unless the gate or policy accepts a nullable user.
- A denied user does not see the tool in `tools/list` or `search_tools`. A direct call fails with JSON-RPC `-32602` `Tool [issue-refund-tool] not found.`; through `execute_tools` it is an error result `Tool [issue-refund-tool] is not available.`
- Do not add a `shouldRegister()` to a tool, resource or prompt with `#[Authorize]`. Umpire compares the method with the trait's and throws `AuthorizationNotEnforced` on every request to that server when they differ. If a class needs custom logic, remove `#[Authorize]` and check the Gate in your own `shouldRegister()`.
- The same attribute and trait protect a resource, a resource template or a prompt. A denied user does not see it in `resources/list`, `resources/templates/list` or `prompts/list`. `resources/read` fails with JSON-RPC `-32602` `Resource [<uri>] not found.`, naming the URI the client asked for, and `prompts/get` with `Prompt [<name>] not found.`. Tool-call events and the audit table cover tools only.
- laravel/mcp looks for a static resource before it tries the templates, so a template whose pattern matches a hidden resource's URI answers for it. Give a protected resource a URI no template matches.

## Observe tool calls

Every tool call dispatches `InvokingTool`, then `ToolInvoked` (it returned) or `ToolFailed` (it threw, or a streamed response was abandoned), from `MujahidAbbas\Umpire\Events`. Each carries `id` (a ULID shared by the call's events), `tool`, the redacted `arguments`, `user`, `clientId` and `tokenId`. The settle events add `durationMs`, and `isError` or `exception`.

```php
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use MujahidAbbas\Umpire\Events\ToolFailed;

Event::listen(function (ToolFailed $event): void {
    Log::warning('MCP tool failed', ['call' => $event->id, 'tool' => $event->tool, 'exception' => $event->exception::class]);
});
```

- Calls `#[Authorize]` refuses never reach `handle()`, so they dispatch no events.
- Never add a container method binding (`bindMethod()`) on a tool's `handle()`: Umpire audits through one. An existing binding makes Umpire refuse every tool call on the server with `ToolCannotBeAudited`; one added after Umpire's silently turns the audit off for that tool.

## Redact arguments

Values of keys in `umpire.redact` (passwords, secrets, tokens, API keys, `authorization` by default) become `[redacted]` before any event is dispatched. Add per-tool keys with `#[Redact('security_answer')]` from `MujahidAbbas\Umpire\Attributes`. Keys match at any depth and ignore case; parent classes' keys apply.

## Audit table

Set `'audit' => ['enabled' => true, ...]` in `config/umpire.php`. Each settled call writes one `MujahidAbbas\Umpire\Models\ToolCall` row in `mcp_tool_calls`: `id`, `tool`, `arguments`, `user_type`, `user_id`, `client_id`, `token_id`, `tenant_id`, `is_error`, `exception` (class name only), `duration_ms`, `created_at`.

```php
use MujahidAbbas\Umpire\Models\ToolCall;

ToolCall::query()->where('tool', 'issue-refund-tool')->where('created_at', '>=', now()->subDay())->get();
```

- Retention: `umpire.audit.retention_days` (90; `null` keeps every row). Schedule `Schedule::command('model:prune', ['--model' => [ToolCall::class]])->daily();`. `--model` limits pruning to the listed classes, so list the app's own prunable models in the same array.
- Tenant: `Umpire::resolveTenantUsing(fn (ToolInvoked|ToolFailed $event) => $event->user?->team_id);` in a service provider's `boot()`. The value is stored as a string.
- MySQL's `json` column does not keep the key order of `arguments`.

## Purge abandoned client registrations

Umpire records each client created through laravel/mcp's `mcp.oauth.register` route. `mcp:purge-clients --unused` lists recorded clients older than `--older-than` (default `24h`, minimum `1h`, digits plus `m`, `h` or `d`) that have no access tokens left; `--force` deletes them with their auth codes. It never touches clients Umpire did not record.

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('mcp:purge-clients --unused --force')
    ->daily()
    ->appendOutputTo(storage_path('logs/mcp-purge-clients.log'));
```

- In production `--force` asks for confirmation only in a terminal; under the scheduler it deletes without asking.
- Without `appendOutputTo()`, the scheduler sends the output to `/dev/null`.
- `passport:purge` deletes revoked tokens and tokens expired for more than a week, so their clients are listed too; Claude Code registers a new client on its next login. On Passport before 13.10 it also deletes expired access tokens whose refresh token is still valid: if access tokens expire sooner than refresh tokens there, upgrade Passport before scheduling the purge.
- Never clean up MCP clients by deleting `oauth_clients` rows directly: a client that received a token is in use.

## Testing

laravel/mcp's server test helpers run through Umpire, so authorization and events behave as in production:

```php
use App\Mcp\Servers\HelpdeskServer;
use App\Mcp\Tools\IssueRefundTool;
use Illuminate\Support\Facades\Event;
use MujahidAbbas\Umpire\Events\ToolInvoked;

HelpdeskServer::actingAs($agent)->tools()->assertNotRegistered(IssueRefundTool::class);
HelpdeskServer::actingAs($agent)->tool(IssueRefundTool::class, ['reference' => 'HD-1001'])->assertNotRegistered();

Event::fake([ToolInvoked::class]);
HelpdeskServer::actingAs($supervisor)->tool(IssueRefundTool::class, ['reference' => 'HD-1001'])->assertOk();
Event::assertDispatched(ToolInvoked::class, fn (ToolInvoked $event): bool => $event->tool === 'issue-refund-tool');
```

For a refused resource or prompt, `->resource(RefundPolicyResource::class)->assertNotRegistered()` and `->prompt(DraftRefundReplyPrompt::class)->assertNotRegistered()` work, but not for a resource template: laravel/mcp's assertion looks for the template while the error names the URI that was read. Assert that error instead:

```php
HelpdeskServer::actingAs($agent)->resource(CustomerRecordResource::class, ['id' => 'C-1001'])
    ->assertHasErrors(['Resource [helpdesk://customers/C-1001] not found.']);
```

Over HTTP, a call to tool search's `execute_tools` (on a server whose tools sit in a `ToolSearch` catalog) answers with a server-sent event stream, so `assertSee()` sees an empty body. Read `streamedContent()` instead:

```php
$response = $this->withToken($token)->postJson('/mcp/helpdesk', [
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'tools/call',
    'params' => [
        'name' => 'execute_tools',
        'arguments' => ['calls' => [['name' => 'issue-refund-tool', 'arguments' => ['reference' => 'HD-1001']]]],
    ],
]);

$this->assertStringContainsString('Tool [issue-refund-tool] is not available.', $response->streamedContent());
```

A misconfigured `#[Authorize]` tool, resource or prompt makes these helpers throw `AuthorizationNotEnforced`, so any test of the server catches it.

## Troubleshooting

- `AuthorizationNotEnforced`: the named tool, resource or prompt has `#[Authorize]` but lacks `AuthorizesWithGate`, or it (or a parent or subclass) defines `shouldRegister()`, or its `#[Authorize]` is not Umpire's (a missing `use MujahidAbbas\Umpire\Attributes\Authorize;` line, or Laravel's controller attribute), or sits on a method or a trait. The message names which. Fix the class; the server fails every request until then.
- `ToolCannotBeAudited`: something binds the tool's `handle()` with `bindMethod()`. Remove that binding.
- A tool, resource or prompt missing for a user who should see it: check the Gate ability or policy for that user, including a guest (`null` user).
- `QueryException` on `/oauth/register`: the `mcp_registered_clients` table is missing. Publish and run the migrations, or set `umpire.clients.record` to `false`. Registration still succeeds.
- A 500 on laravel/mcp's consent screen: its view calls `@vite(['resources/css/app.css'])`. Build the assets, or remove that line from the published view (`--tag=mcp-views`). This is laravel/mcp, not Umpire.
- Claude Code refuses OAuth over plain `http` except on `localhost`: serve locally on `localhost` or over https.
