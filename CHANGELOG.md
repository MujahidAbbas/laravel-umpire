# Release Notes

## [Unreleased](https://github.com/MujahidAbbas/laravel-umpire/compare/v1.1.0...main)

- An `#[Authorize]` that Umpire never reads now fails closed. That covers an attribute that is not Umpire's (Laravel's controller attribute `Illuminate\Routing\Attributes\Controllers\Authorize`, or `#[Authorize]` without its `use MujahidAbbas\Umpire\Attributes\Authorize;` line, which PHP resolves to a class that does not exist), and Umpire's own attribute on a method or on a trait the class uses. Until now these were ignored, and the tool, resource or prompt was served to every user. An unimported one also broke `tools/list` with PHP's "Attribute class not found" error while calls to the tool still ran.
- After this upgrade, a server with one of these mistakes fails every MCP request (HTTP 500) with `AuthorizationNotEnforced` until the class is fixed. The message names the attribute it found and where it was declared.
- `mcp:purge-clients --unused` selects recorded clients with no access tokens left. The v1.0.0 notes said "clients that never received an access token", which was wrong: `passport:purge` deletes revoked tokens and tokens expired for more than a week, so their clients are listed too. On Passport before 13.10 that includes an expired access token whose refresh token is still valid. The command's rule is unchanged; its messages and the docs now say this.

## [v1.1.0](https://github.com/MujahidAbbas/laravel-umpire/compare/v1.0.0...v1.1.0) - 2026-10-27

- `#[Authorize]` and `AuthorizesWithGate` now protect resources, resource templates and prompts. A denied user does not see them listed, and `resources/read`, `prompts/get` and `completion/complete` fail with JSON-RPC `-32602` "not found".
- The fail-closed check covers resources and prompts. One that carries `#[Authorize]` without enforcing it (the trait is missing, or it defines its own `shouldRegister()`) now throws `AuthorizationNotEnforced` on every request to its server. In v1.0.0 the attribute was ignored there, and the resource or prompt was served to every user.

## v1.0.0 - 2026-10-27

The first release.

- `#[Authorize]` and the `AuthorizesWithGate` trait: per-tool authorization through Laravel's Gate, for any guard. A tool that carries the attribute without enforcing it throws `AuthorizationNotEnforced` on every request to its server.
- Tool-call events `InvokingTool`, `ToolInvoked` and `ToolFailed`, for direct calls and calls through tool search's `execute_tools`, with redacted arguments, the user, the OAuth client, the access token and the duration. Streamed responses are passed through untouched.
- Argument redaction through `umpire.redact` and `#[Redact]`.
- An optional audit table (`mcp_tool_calls`) with the `ToolCall` model, retention through `model:prune`, and `Umpire::resolveTenantUsing()`.
- A marker for OAuth clients created through laravel/mcp's dynamic client registration (`mcp_registered_clients`), and `mcp:purge-clients --unused` to delete recorded clients that never received an access token.
- A Laravel Boost guideline and the `umpire-development` skill.
