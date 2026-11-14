# Release Notes

## [Unreleased](https://github.com/MujahidAbbas/laravel-umpire/compare/v1.1.2...main)

## [v1.1.2](https://github.com/MujahidAbbas/laravel-umpire/compare/v1.1.1...v1.1.2) - 2026-11-15

- `mcp:purge-clients --unused` now skips a client with a login in progress: one that holds an authorization code that is neither revoked nor expired. Until now it deleted the client and the code, and that login failed when the client exchanged the code for a token. The listing and the delete both check, so the purge deletes fewer clients than before, never more. A skipped client is listed again once its code has expired (ten minutes in Passport) if the login was abandoned. The line printed for clients kept after the listing now reads "received an access token or started a login after the listing".
- `mcp:purge-clients` prints a client's name as plain text: control characters are removed and console tags are escaped. The name comes from laravel/mcp's unauthenticated registration route, and one holding escape sequences or tags such as `<fg=red>` or `<href=...>` could recolor the listing, move the cursor or plant a link in the terminal or in a log written with `appendOutputTo()`. It never changed which clients were deleted.
- `umpire.audit.retention_days` accepts a string of digits, so it can come from `env()`. Until now `model:prune` failed with "must be an integer, string given". Any other value that is not an integer or `null` still fails.
- Docs: do not queue a listener on `ToolFailed`; and three limitations: integer Passport client ids on PostgreSQL, servers built with `new`, and `Mcp::local()` servers, which have no user.

## [v1.1.1](https://github.com/MujahidAbbas/laravel-umpire/compare/v1.1.0...v1.1.1) - 2026-10-29

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
