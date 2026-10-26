# Umpire for Laravel

Per-tool Gate authorization, tool-call audit events and a safe stale-client purge for
[laravel/mcp](https://github.com/laravel/mcp) servers.

laravel/mcp gives you MCP servers, tools and an OAuth flow on Passport. Umpire adds three things
it leaves to you:

- **Authorization per tool.** Put `#[Authorize('issue-refunds')]` on a tool and users the Gate
  denies no longer see it or call it. A tool that carries the attribute without enforcing it
  stops the server, so it never runs unprotected.
- **An audit trail.** Every tool call dispatches `InvokingTool`, then `ToolInvoked` or
  `ToolFailed`, with the redacted arguments, the user, the OAuth client, the access token and
  the duration. An optional table keeps one row per call.
- **Cleanup of abandoned client registrations.** Every MCP client that connects through dynamic
  client registration creates a Passport client, and failed or abandoned logins leave them
  behind. `mcp:purge-clients` deletes the ones Umpire recorded at registration that never
  received an access token, and only lists them until you pass `--force`.

## Requirements

- PHP 8.3 or later
- Laravel 13
- laravel/mcp 1.1 or later
- Laravel Passport, for the registration marker and `mcp:purge-clients`. Authorization and the
  audit work with any guard: Passport, Sanctum or your own.

## Installation

```bash
composer require mujahidabbas/laravel-umpire
```

Publish the migrations and run them:

```bash
php artisan vendor:publish --tag=umpire-migrations
php artisan migrate
```

This creates `mcp_tool_calls`, for the audit table, and `mcp_registered_clients`, on Passport's
database connection, for the registration marker. Run both even if you leave the audit table
off: the marker records every registration by default.

To change a default, publish the config file to `config/umpire.php`:

```bash
php artisan vendor:publish --tag=umpire-config
```

## Authorization per tool

Define the ability with the Gate as usual, then put `#[Authorize]` on the tool and use the
`AuthorizesWithGate` trait:

```php
// app/Providers/AppServiceProvider.php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('issue-refunds', fn (User $user): bool => $user->is_supervisor);
}
```

```php
namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

#[Description('Refund the order behind a ticket. Supervisors only.')]
#[Authorize('issue-refunds')]
class IssueRefundTool extends Tool
{
    use AuthorizesWithGate;

    public function handle(Request $request): Response
    {
        return Response::text('Refund queued for '.$request->string('reference')->value().'.');
    }

    public function schema(JsonSchema $schema): array
    {
        return ['reference' => $schema->string()->required()];
    }
}
```

laravel/mcp asks a tool's `shouldRegister()` before it lists, searches or calls the tool. The
trait's `shouldRegister()` checks every `#[Authorize]` on the tool against the Gate, for the
user of the MCP request. For a user the Gate denies:

- the tool is missing from `tools/list` and from tool search's `search_tools`;
- a direct `tools/call` fails with JSON-RPC error `-32602` (HTTP 400) and the message
  `Tool [issue-refund-tool] not found.`, as if the tool did not exist;
- a call through tool search's `execute_tools` returns an error result with the message
  `Tool [issue-refund-tool] is not available.`

The attribute takes the ability first and then any arguments, which reach the Gate as they do
with the `can` middleware. A class name selects a policy:

```php
#[Authorize('export', Ticket::class)] // TicketPolicy::export($user)
```

- Repeat the attribute to require several abilities. Every one must pass.
- Attributes on parent classes count, so a tool that extends a protected tool stays protected.
- A guest is denied unless the gate or policy method accepts a null user (`?User $user`).

### Fail closed

The attribute does nothing without the trait, because laravel/mcp only ever calls
`shouldRegister()`. So on every MCP request Umpire checks the server's tools. When a tool has
`#[Authorize]` and its `shouldRegister()` is not the trait's (the trait is missing, or the tool
or a subclass defines its own), Umpire throws
`MujahidAbbas\Umpire\Exceptions\AuthorizationNotEnforced`. Every request to that server then
fails with HTTP 500 and the exception is reported, until the tool is fixed. With `app.debug`
off the response is JSON-RPC error `-32603`; with it on, laravel/mcp rethrows the exception. laravel/mcp's test helpers throw it too, so a test of any tool on the server catches the
mistake.

If you want your own `shouldRegister()` logic, leave `#[Authorize]` off that tool and call the
Gate yourself.

## Tool-call events

Every call to a tool on your servers dispatches `InvokingTool` before `handle()` runs, then
`ToolInvoked` when it returns or `ToolFailed` when it throws. All three live in
`MujahidAbbas\Umpire\Events`.

| Property     | Type               | Events                   | Holds                                                                     |
|--------------|--------------------|--------------------------|---------------------------------------------------------------------------|
| `id`         | `string`           | all                      | A ULID shared by the events of one call                                   |
| `tool`       | `string`           | all                      | The tool's name                                                           |
| `arguments`  | `array`            | all                      | The tool arguments, redacted (see [Redaction](#redaction))                |
| `user`       | `?Authenticatable` | all                      | The user of the MCP request                                               |
| `clientId`   | `?string`          | all                      | Passport: the OAuth client id. Sanctum: the personal access token id      |
| `tokenId`    | `?string`          | all                      | Passport: the access token id. Sanctum: the personal access token id      |
| `durationMs` | `float`            | `ToolInvoked`, `ToolFailed` | Milliseconds from the start of `handle()` until the call settled       |
| `isError`    | `bool`             | `ToolInvoked`            | Whether the tool returned an error response                               |
| `exception`  | `Throwable`        | `ToolFailed`             | What `handle()` threw, or `ToolStreamAbandoned`                           |

```php
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use MujahidAbbas\Umpire\Events\ToolFailed;

Event::listen(function (ToolFailed $event): void {
    Log::warning('MCP tool failed', [
        'call' => $event->id,
        'tool' => $event->tool,
        'client' => $event->clientId,
        'exception' => $event->exception::class,
    ]);
});
```

- Direct `tools/call` requests and calls through tool search's `execute_tools` are both
  covered. `search_tools` and `execute_tools` themselves are not reported, only the tools they
  run.
- An exception from `handle()`, a validation exception included, is a `ToolFailed`. Umpire
  rethrows it, so laravel/mcp handles it exactly as it would without Umpire.
- A streamed tool, one whose `handle()` returns a generator, settles when the stream ends. If
  the client goes away first, `ToolFailed` carries
  `MujahidAbbas\Umpire\Exceptions\ToolStreamAbandoned`, which is never thrown or reported.
- laravel/mcp's server has no MCP session id, so the access token id is the closest thing to
  one.

## Audit table

Turn the table on in `config/umpire.php`:

```php
'audit' => [
    'enabled' => true,
    'table' => 'mcp_tool_calls',
    'retention_days' => 90,
],
```

Each call then writes one row when it settles: the call's ULID as `id`, `tool`, the redacted
`arguments`, `user_type`, `user_id`, `client_id`, `token_id`, `tenant_id`, `is_error`,
`exception`, `duration_ms` and `created_at`. `exception` holds the class name only, never the
message, because a message cannot be redacted. A failed insert is reported and never changes
the tool's result.

Rows are `MujahidAbbas\Umpire\Models\ToolCall` models:

```php
use MujahidAbbas\Umpire\Models\ToolCall;

$refunds = ToolCall::query()
    ->where('tool', 'issue-refund-tool')
    ->where('created_at', '>=', now()->subDay())
    ->get();
```

### Redaction

The values of the keys in `umpire.redact` become `[redacted]` before any event is dispatched,
so listeners and the audit table never see them. The default list covers passwords, secrets,
tokens, API keys and `authorization`. A tool adds its own keys with `#[Redact]`:

```php
use MujahidAbbas\Umpire\Attributes\Redact;

#[Redact('security_answer')]
class VerifyCustomerTool extends Tool
{
    // ...
}
```

Keys match at any depth and ignore case. `#[Redact]` on a parent class applies to its
subclasses.

### Retention

`model:prune` only looks in `app/Models`, so name the model when you schedule it:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;
use MujahidAbbas\Umpire\Models\ToolCall;

Schedule::command('model:prune', ['--model' => [ToolCall::class]])->daily();
```

`--model` limits pruning to the classes you list, so if you already schedule `model:prune` for
your own models, add them to the same array. Rows older than `retention_days` are deleted; set
it to `null` to keep every row.

### Tenant

To store a tenant id with each row, register a resolver in a service provider's `boot()`. It
receives the `ToolInvoked` or `ToolFailed` event and returns an id or `null`:

```php
use MujahidAbbas\Umpire\Events\ToolFailed;
use MujahidAbbas\Umpire\Events\ToolInvoked;
use MujahidAbbas\Umpire\Umpire;

Umpire::resolveTenantUsing(fn (ToolInvoked|ToolFailed $event) => $event->user?->team_id);
```

## Registration marker and `mcp:purge-clients`

`Mcp::oauthRoutes()` registers laravel/mcp's dynamic client registration endpoint,
`POST /oauth/register`. laravel/mcp registers it without authentication, and every successful
call creates a Passport client. A client that abandons the login, or retries after a failure,
leaves its registration behind, and nothing in Passport's `oauth_clients` row tells it apart
from a public client you created with `passport:client --public`.

So Umpire records the id of every client created through that route (each `201` response from
the `mcp.oauth.register` route) in `mcp_registered_clients`. It records by default; set
`umpire.clients.record` to `false` to stop. A failed insert is reported and never breaks the
registration.

List what a purge would delete:

```bash
php artisan mcp:purge-clients --unused
```

```
+--------------------------------------+-----------------+---------------------+
| Client ID                            | Name            | Registered at       |
+--------------------------------------+-----------------+---------------------+
| 01a178a7-3297-702f-afd1-6d7af2fc1f8c | Stale assistant | 2026-10-24 14:39:45 |
| 01a178a7-55ab-72d3-b6b6-65838a3b8e45 | Stale assistant | 2026-10-24 14:39:54 |
+--------------------------------------+-----------------+---------------------+
2 clients would be deleted. Run with --force to delete them.
```

Then delete them:

```bash
php artisan mcp:purge-clients --unused --force
```

| Option         | Meaning                                                                                       |
|----------------|-----------------------------------------------------------------------------------------------|
| `--unused`     | Required. Selects recorded clients that never received an access token, revoked or not.      |
| `--older-than` | Only clients registered longer ago than this. Digits followed by `m`, `h` or `d`, at least `1h`. Default `24h`. |
| `--dry-run`    | List without deleting. This is already the default; it cannot be combined with `--force`.    |
| `--force`      | Delete the listed clients.                                                                    |

Each client is deleted in its own transaction, together with its authorization codes and its
marker. The delete checks for tokens again, so a client that receives one after the listing is
kept. The purge never touches a client Umpire did not record: clients made with
`passport:client`, Passport's personal access client, and anything registered before you
installed Umpire. It refuses to run unless Passport's clients, tokens and authorization codes
and the marker table share one database connection.

In production, `--force` asks `Delete N clients from production? This cannot be undone.` when
it runs in a terminal. With `-n`, or without a terminal, as under the scheduler, it deletes
without asking. So the scheduled line is:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('mcp:purge-clients --unused --force')
    ->daily()
    ->appendOutputTo(storage_path('logs/mcp-purge-clients.log'));
```

Without `appendOutputTo()` (or `sendOutputTo()`), the scheduler sends the command's output to
`/dev/null`, and the list of deleted clients is lost.

## Limitations

- **Where Umpire looks for tools.** Umpire finds a server's tools in its `$tools` property,
  including tools added in `boot()`. It does not see tools supplied by an overridden
  `createContext()`, or calls handled by a custom `tools/call` method that does not extend
  laravel/mcp's `CallTool`. Both the audit and the `#[Authorize]` check share this limit.
- **Your own binding on `handle()`.** Umpire audits a tool through a container method binding
  on its `handle()`. If a tool already has one when Umpire binds, Umpire refuses every tool call
  on that server with `ToolCannotBeAudited` (HTTP 500, reported) rather than run it unaudited. A binding your app makes after Umpire's replaces it silently,
  and that tool goes unaudited. This is not supported.
- **`#[Authorize]` covers tools only**, not resources or prompts.
- **Refused calls are never audited.** A call `#[Authorize]` refuses never reaches `handle()`,
  so it dispatches no events and writes no row.
- **Two refusal shapes.** A refused direct call is JSON-RPC `-32602` "not found"; inside
  `execute_tools` it is an error result. Clients and tests see different shapes.
- **Recording is on by default.** An app that never migrates `mcp_registered_clients` gets a
  reported `QueryException` on every registration (the registration itself still succeeds).
  Migrate, or set `umpire.clients.record` to `false`.
- **MySQL reorders argument keys.** MySQL's `json` column does not keep the order of an
  object's keys, so `arguments` can come back in a different order than the tool received.
- **Published migrations are copies.** An app that published the migrations keeps its copies
  when a later release changes them, until it publishes again with `--force`.

## Notes from laravel/mcp and Claude Code

These come from laravel/mcp and from Claude Code, not from Umpire, but you will meet them while
setting up OAuth for an MCP server:

- laravel/mcp's consent screen (`resources/views/mcp/authorize.blade.php`, published with
  `php artisan vendor:publish --tag=mcp-views`) calls `@vite(['resources/css/app.css'])`. An
  app without built assets gets a 500 on the consent screen. Build the assets, or remove that
  line from the published view.
- Claude Code refuses OAuth token requests over plain `http` except on `localhost`. To test
  locally, serve the app on `localhost` (`php artisan serve`) or over https (`herd secure`).

## Support

Report bugs in [Issues](https://github.com/MujahidAbbas/laravel-umpire/issues), and take
questions and ideas to [GitHub Discussions](https://github.com/MujahidAbbas/laravel-umpire/discussions).
Read [CONTRIBUTING](.github/CONTRIBUTING.md) before opening a pull request, and report
vulnerabilities as described in [SECURITY](.github/SECURITY.md).

## Changelog

See [CHANGELOG](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE](LICENSE.md).
