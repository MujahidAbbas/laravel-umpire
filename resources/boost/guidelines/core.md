# Umpire for Laravel

Umpire (`mujahidabbas/laravel-umpire`) adds per-tool Gate authorization, tool-call audit events and a stale OAuth client purge to laravel/mcp servers. For setup, tests and troubleshooting, use the `umpire-development` skill.

- Restrict a tool to some users with `#[Authorize('ability')]` from `MujahidAbbas\Umpire\Attributes` plus `use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;` in the tool. Define the ability with the Gate or a policy (`#[Authorize('export', Ticket::class)]`). Repeated attributes must all pass, and parent classes' attributes apply.
- Never define `shouldRegister()` on a tool that has `#[Authorize]`, and never leave out the trait: Umpire then throws `AuthorizationNotEnforced` and the whole server fails every request. For custom logic, drop the attribute and write `shouldRegister()` yourself.
- `#[Authorize]` works on tools only, not on resources or prompts.
- To observe tool calls, listen for `MujahidAbbas\Umpire\Events\InvokingTool`, `ToolInvoked` and `ToolFailed`. Do not add a container method binding (`bindMethod()`) on a tool's `handle()`: an existing one makes Umpire refuse every tool call with `ToolCannotBeAudited`, and one added later silently turns the audit off for that tool.
- Keep secrets out of events and the audit table with `#[Redact('field')]` on the tool or the `umpire.redact` config list. Keys match at any depth and ignore case.
- Audit rows are `MujahidAbbas\Umpire\Models\ToolCall` (table `mcp_tool_calls`, on when `umpire.audit.enabled` is true). Prune them with `model:prune --model="MujahidAbbas\Umpire\Models\ToolCall"`; plain `model:prune` does not find them.
- Never delete rows from `oauth_clients` by hand to clean up MCP registrations. Use `php artisan mcp:purge-clients --unused`, which only lists; add `--force` to delete. It only touches clients Umpire recorded at registration that never received an access token.
