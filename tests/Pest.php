<?php

declare(strict_types=1);

use Laravel\Passport\ClientRepository;
use Laravel\Passport\PersonalAccessTokenResult;
use Laravel\Sanctum\NewAccessToken;
use MujahidAbbas\Umpire\Tests\Fixtures\Models\Agent;
use MujahidAbbas\Umpire\Tests\Fixtures\Models\SanctumAgent;
use MujahidAbbas\Umpire\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Feature');

dataset('guards', [
    'Passport' => 'passport',
    'Sanctum' => 'sanctum',
]);

/**
 * @param  class-string<Agent|SanctumAgent>  $model
 */
function agent(string $model = Agent::class): Agent|SanctumAgent
{
    return $model::query()->create([
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'password' => fake()->password(),
    ]);
}

/**
 * Issue a real Passport personal access token, so the guard decodes a signed JWT and attaches the
 * same AccessToken attributes an OAuth client's token carries.
 */
function passportToken(Agent $agent): PersonalAccessTokenResult
{
    static $keys = null;

    if ($keys === null) {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        $keys = ['private' => $private, 'public' => openssl_pkey_get_details($key)['key']];
    }

    config(['passport.private_key' => $keys['private'], 'passport.public_key' => $keys['public']]);

    app(ClientRepository::class)->createPersonalAccessGrantClient('Help desk', 'users');

    return $agent->createToken('Support console');
}

function sanctumToken(SanctumAgent $agent): NewAccessToken
{
    return $agent->createToken('Support console');
}

/**
 * Register a public client through laravel/mcp's dynamic client registration route, as an MCP
 * client would, and return its id.
 */
function registerClient(): string
{
    return test()->postJson(route('mcp.oauth.register'), [
        'client_name' => fake()->company().' Assistant',
        'redirect_uris' => ['https://'.fake()->domainName().'/oauth/callback'],
    ])->assertCreated()->json('client_id');
}

/**
 * An agent of the guard's user model, with a real bearer token for it.
 *
 * @return array{Agent|SanctumAgent, string}
 */
function agentWithToken(string $guard): array
{
    if ($guard === 'passport') {
        $agent = agent(Agent::class);

        return [$agent, passportToken($agent)->accessToken];
    }

    $agent = agent(SanctumAgent::class);

    return [$agent, sanctumToken($agent)->plainTextToken];
}

/**
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>
 */
function toolCallMessage(string $tool, array $arguments = []): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => [
            'name' => $tool,
            'arguments' => $arguments,
            '_meta' => [
                'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientCapabilities' => [],
            ],
        ],
    ];
}

/**
 * @param  list<array{name: string, arguments: array<string, mixed>}>  $calls
 * @return array<string, mixed>
 */
function executeToolsMessage(array $calls): array
{
    return toolCallMessage('execute_tools', ['calls' => $calls]);
}

/**
 * @return array<string, mixed>
 */
function listToolsMessage(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
        'params' => [
            '_meta' => [
                'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
                'io.modelcontextprotocol/clientCapabilities' => [],
            ],
        ],
    ];
}

/**
 * Post a JSON-RPC message to an MCP route and return every message sent back, whether the
 * transport answered with plain JSON or with a server-sent event stream.
 *
 * @param  array<string, mixed>  $message
 * @return list<array<string, mixed>>
 */
function postMcp(string $uri, array $message, ?string $token = null, int $status = 200): array
{
    $request = $token === null ? test() : test()->withToken($token);

    $response = $request->postJson($uri, $message, array_filter([
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method' => $message['method'],
        'Mcp-Name' => $message['params']['name'] ?? null,
    ]));

    $response->assertStatus($status);

    if (! str_contains((string) $response->headers->get('Content-Type'), 'text/event-stream')) {
        return [$response->json()];
    }

    return collect(explode("\n", $response->streamedContent()))
        ->filter(fn (string $line): bool => str_starts_with($line, 'data: '))
        ->map(fn (string $line): array => json_decode(substr($line, 6), true))
        ->values()
        ->all();
}

/**
 * The names a tools/list call returned.
 *
 * @return list<string>
 */
function listedTools(string $uri, ?string $token = null): array
{
    return array_column(rpcResult(postMcp($uri, listToolsMessage(), $token))['tools'], 'name');
}

/**
 * @param  list<array<string, mixed>>  $messages
 * @return array<string, mixed>
 */
function rpcResult(array $messages): array
{
    return collect($messages)->first(fn (array $message): bool => array_key_exists('id', $message))['result'];
}

/**
 * Decode what search_tools or execute_tools returned in its text content.
 *
 * @param  array<string, mixed>  $result
 * @return array<string, mixed>
 */
function toolSearchOutput(array $result): array
{
    return json_decode($result['content'][0]['text'], true);
}
