<?php

declare(strict_types=1);

use MujahidAbbas\Umpire\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Feature');

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
 * Post a JSON-RPC message to an MCP route and return every message sent back, whether the
 * transport answered with plain JSON or with a server-sent event stream.
 *
 * @param  array<string, mixed>  $message
 * @return list<array<string, mixed>>
 */
function postMcp(string $uri, array $message): array
{
    $response = test()->postJson($uri, $message, [
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method' => $message['method'],
        'Mcp-Name' => $message['params']['name'],
    ]);

    $response->assertOk();

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
 * @param  list<array<string, mixed>>  $messages
 * @return array<string, mixed>
 */
function rpcResult(array $messages): array
{
    return collect($messages)->first(fn (array $message): bool => array_key_exists('id', $message))['result'];
}
