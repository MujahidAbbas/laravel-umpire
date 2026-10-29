<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Redacted Arguments
    |--------------------------------------------------------------------------
    |
    | Tool argument keys whose values are replaced with "[redacted]" before
    | any Umpire event is dispatched, so listeners and the audit table never
    | see them. Keys match at any depth and ignore case. A tool adds its own
    | keys with the #[Redact('card_number')] attribute.
    |
    */

    'redact' => [
        'password',
        'password_confirmation',
        'current_password',
        'secret',
        'client_secret',
        'token',
        'access_token',
        'refresh_token',
        'api_key',
        'authorization',
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Table
    |--------------------------------------------------------------------------
    |
    | When enabled, every tool call is written to this table once it settles,
    | with the redacted arguments and the exception class, never its message.
    | Publish the migration with the "umpire-migrations" tag. Old rows are
    | removed by "model:prune --model=MujahidAbbas\Umpire\Models\ToolCall";
    | a retention of null keeps every row.
    |
    */

    'audit' => [
        'enabled' => false,
        'table' => 'mcp_tool_calls',
        'retention_days' => 90,
    ],

    /*
    |--------------------------------------------------------------------------
    | Registered Clients
    |--------------------------------------------------------------------------
    |
    | Umpire records the id of every OAuth client created through laravel/mcp
    | dynamic client registration, in a table on Passport's connection. The
    | "mcp:purge-clients --unused" command only ever deletes recorded clients
    | that have no access tokens left. The table comes with the
    | "umpire-migrations" tag.
    |
    */

    'clients' => [
        'record' => true,
        'table' => 'mcp_registered_clients',
    ],

];
