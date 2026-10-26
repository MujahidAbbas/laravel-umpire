<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use MujahidAbbas\Umpire\Events\InvokingTool;
use MujahidAbbas\Umpire\Events\ToolFailed;
use MujahidAbbas\Umpire\Events\ToolInvoked;
use MujahidAbbas\Umpire\Tests\Fixtures\Models\SanctumAgent;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\HelpdeskServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\EscalateTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\VerifyCustomerTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\VerifyVipCustomerTool;

beforeEach(function (): void {
    Event::fake([InvokingTool::class, ToolInvoked::class, ToolFailed::class]);
});

/**
 * Assert that every event of the one recorded call carries the same id and credentials.
 */
function assertCallCarries(string $tool, ?int $userId, ?string $clientId, ?string $tokenId): void
{
    $starting = Event::dispatched(InvokingTool::class)->map(fn (array $arguments): InvokingTool => $arguments[0])->sole();
    $settled = Event::dispatched(ToolInvoked::class)->map(fn (array $arguments): ToolInvoked => $arguments[0])->sole();

    expect(Str::isUlid($starting->id))->toBeTrue()
        ->and($settled->id)->toBe($starting->id);

    foreach ([$starting, $settled] as $event) {
        expect($event->tool)->toBe($tool)
            ->and($event->user?->getAuthIdentifier())->toBe($userId)
            ->and($event->clientId)->toBe($clientId)
            ->and($event->tokenId)->toBe($tokenId);
    }
}

it('carries the Passport user, OAuth client and access token', function (string $uri, array $message): void {
    $agent = agent();
    $token = passportToken($agent);

    postMcp($uri, $message, $token->accessToken);

    assertCallCarries('lookup-ticket', $agent->getKey(), $token->token->client_id, $token->token->getKey());
})->with([
    'direct call' => ['mcp/passport/helpdesk', toolCallMessage('lookup-ticket')],
    'execute_tools' => ['mcp/passport/catalog', executeToolsMessage([['name' => 'lookup-ticket', 'arguments' => []]])],
]);

it('carries the Sanctum user and its personal access token as both client and token', function (string $uri, array $message): void {
    $agent = agent(SanctumAgent::class);
    $token = sanctumToken($agent);

    postMcp($uri, $message, $token->plainTextToken);

    $id = (string) $token->accessToken->getKey();

    assertCallCarries('lookup-ticket', $agent->getKey(), $id, $id);
})->with([
    'direct call' => ['mcp/sanctum/helpdesk', toolCallMessage('lookup-ticket')],
    'execute_tools' => ['mcp/sanctum/catalog', executeToolsMessage([['name' => 'lookup-ticket', 'arguments' => []]])],
]);

it('leaves the credentials empty for a guest', function (): void {
    postMcp('mcp/helpdesk', toolCallMessage('lookup-ticket'));

    assertCallCarries('lookup-ticket', null, null, null);
});

it('carries the same id and payload on a failed call', function (): void {
    $ticket = fake()->uuid();

    HelpdeskServer::tool(EscalateTicketTool::class, ['ticket' => $ticket]);

    $starting = Event::dispatched(InvokingTool::class)->sole()[0];

    Event::assertDispatched(ToolFailed::class, fn (ToolFailed $event): bool => $event->id === $starting->id
        && $event->tool === 'escalate-ticket'
        && $event->arguments === ['ticket' => $ticket]
        && $event->durationMs >= 0);
});

it('redacts global and #[Redact] keys at any depth, ignoring case', function (): void {
    $answer = fake()->word();
    $email = fake()->safeEmail();

    HelpdeskServer::tool(VerifyCustomerTool::class, [
        'email' => $email,
        'Password' => fake()->password(),
        'security_answer' => $answer,
        'profile' => ['API_KEY' => fake()->sha1(), 'tier' => 'gold'],
    ])->assertSee('Checked an answer of '.mb_strlen($answer).' characters.');

    $expected = [
        'email' => $email,
        'Password' => '[redacted]',
        'security_answer' => '[redacted]',
        'profile' => ['API_KEY' => '[redacted]', 'tier' => 'gold'],
    ];

    Event::assertDispatched(InvokingTool::class, fn (InvokingTool $event): bool => $event->arguments === $expected);
    Event::assertDispatched(ToolInvoked::class, fn (ToolInvoked $event): bool => $event->arguments === $expected);
});

it('merges a parent tool\'s #[Redact] keys with the tool\'s own', function (): void {
    HelpdeskServer::tool(VerifyVipCustomerTool::class, [
        'security_answer' => fake()->word(),
        'account_pin' => (string) fake()->randomNumber(4, true),
        'segment' => 'enterprise',
    ]);

    Event::assertDispatched(ToolInvoked::class, fn (ToolInvoked $event): bool => $event->arguments === [
        'security_answer' => '[redacted]',
        'account_pin' => '[redacted]',
        'segment' => 'enterprise',
    ]);
});

it('redacts the arguments of a catalog tool run through execute_tools', function (): void {
    $answer = fake()->word();

    postMcp('mcp/catalog', executeToolsMessage([
        ['name' => 'verify-customer', 'arguments' => ['security_answer' => $answer]],
    ]));

    Event::assertDispatched(ToolInvoked::class, fn (ToolInvoked $event): bool => $event->tool === 'verify-customer'
        && $event->arguments === ['security_answer' => '[redacted]']);
});

it('reads the global redaction list from config', function (): void {
    config(['umpire.redact' => ['email']]);
    $password = fake()->password();

    HelpdeskServer::tool(VerifyCustomerTool::class, ['email' => fake()->safeEmail(), 'password' => $password]);

    Event::assertDispatched(ToolInvoked::class, fn (ToolInvoked $event): bool => $event->arguments === [
        'email' => '[redacted]',
        'password' => $password,
    ]);
});

it('publishes its config under the umpire-config tag', function (): void {
    $this->publishPath = sys_get_temp_dir().'/umpire-'.Str::random(12);
    $this->refreshApplication();

    $this->artisan('vendor:publish', ['--tag' => 'umpire-config'])->assertSuccessful();

    expect(File::get($this->publishPath.'/config/umpire.php'))->toBe(File::get(dirname(__DIR__, 2).'/config/umpire.php'));
});
