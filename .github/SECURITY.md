# Security Policy

## Supported versions

Security fixes go to the latest release of Umpire for Laravel.

## Reporting a vulnerability

Please do not report a vulnerability in a public discussion, issue or pull request.

Report it privately through GitHub instead: open the repository's **Security** tab and choose
**Report a vulnerability**. Only the maintainer sees the report, and the fix is discussed and
prepared in that private advisory before anything is made public.

Please include:

- the Umpire, laravel/mcp, Laravel and PHP versions, and the guard (Passport, Sanctum or other);
- what an attacker can do, and the steps or a minimal tool or server that shows it.

Reports that matter most for this package:

- a tool protected by `#[Authorize]` that a user the Gate denies can list or call;
- a tool call that runs without dispatching Umpire's events or writing its audit row;
- a redacted argument that reaches an event or the audit table;
- `mcp:purge-clients` deleting a client it should keep.
