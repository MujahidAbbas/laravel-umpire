<?php

declare(strict_types=1);

use MujahidAbbas\Umpire\Models\ToolCall;
use MujahidAbbas\Umpire\Tests\Fixtures\Models\SanctumAgent;

// Passport is a dev dependency only. In an app without it, Umpire still boots and audits Sanctum
// calls, and the purge refuses to run instead of failing on a missing class.

it('audits a Sanctum tool call', function (): void {
    config(['umpire.audit.enabled' => true]);

    $agent = agent(SanctumAgent::class);
    $token = sanctumToken($agent);

    postMcp('mcp/sanctum/helpdesk', toolCallMessage('lookup-ticket'), $token->plainTextToken);

    $id = (string) $token->accessToken->getKey();
    $row = ToolCall::query()->sole();

    expect($row->tool)->toBe('lookup-ticket')
        ->and($row->user_type)->toBe(SanctumAgent::class)
        ->and($row->user_id)->toBe((string) $agent->getKey())
        ->and($row->client_id)->toBe($id)
        ->and($row->token_id)->toBe($id)
        ->and($row->is_error)->toBeFalse();
});

it('refuses to purge clients', function (): void {
    $this->artisan('mcp:purge-clients', ['--unused' => true, '--force' => true])
        ->expectsOutputToContain('mcp:purge-clients needs Laravel Passport, which is not installed.')
        ->assertFailed();
});
