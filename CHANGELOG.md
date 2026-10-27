# Release Notes

## [Unreleased](https://github.com/MujahidAbbas/laravel-umpire/compare/v1.0.0...main)

## v1.0.0 - 2026-10-27

The first release.

- `#[Authorize]` and the `AuthorizesWithGate` trait: per-tool authorization through Laravel's Gate, for any guard. A tool that carries the attribute without enforcing it throws `AuthorizationNotEnforced` on every request to its server.
- Tool-call events `InvokingTool`, `ToolInvoked` and `ToolFailed`, for direct calls and calls through tool search's `execute_tools`, with redacted arguments, the user, the OAuth client, the access token and the duration. Streamed responses are passed through untouched.
- Argument redaction through `umpire.redact` and `#[Redact]`.
- An optional audit table (`mcp_tool_calls`) with the `ToolCall` model, retention through `model:prune`, and `Umpire::resolveTenantUsing()`.
- A marker for OAuth clients created through laravel/mcp's dynamic client registration (`mcp_registered_clients`), and `mcp:purge-clients --unused` to delete recorded clients that never received an access token.
- A Laravel Boost guideline and the `umpire-development` skill.
