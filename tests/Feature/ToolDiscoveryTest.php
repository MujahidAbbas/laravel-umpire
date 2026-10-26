<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use MujahidAbbas\Umpire\Events\InvokingTool;
use MujahidAbbas\Umpire\Events\ToolFailed;
use MujahidAbbas\Umpire\Events\ToolInvoked;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\DynamicServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\AssignTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\LookupTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\MergeTicketsTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\TagTicketTool;

// Risk 3: a server can add tools after it is resolved, in boot(), which runs in start().
// Decided 2026-10-26: Server::$tools is re-read each time CallTool resolves, after boot(), so
// every registration style laravel/mcp allows is audited.

beforeEach(function (): void {
    Event::fake([InvokingTool::class, ToolInvoked::class, ToolFailed::class]);
});

it('audits a tool however the server registers it', function (string $class, string $name): void {
    DynamicServer::tool($class)->assertHasNoErrors()->assertSee("{$name} done");

    Event::assertDispatched(InvokingTool::class, fn (InvokingTool $event): bool => $event->tool === $name);
    Event::assertDispatched(ToolInvoked::class, fn (ToolInvoked $event): bool => $event->tool === $name);
})->with([
    'declared in $tools' => [LookupTicketTool::class, 'lookup-ticket'],
    'added in the constructor' => [AssignTicketTool::class, 'assign-ticket'],
    'added as a class name in boot()' => [MergeTicketsTool::class, 'merge-tickets'],
    'added as an instance in boot()' => [TagTicketTool::class, 'tag-ticket'],
]);

it('audits a tool added as an instance in boot() over HTTP', function (): void {
    $result = rpcResult(postMcp('mcp/dynamic', toolCallMessage('tag-ticket', ['tag' => fake()->word()])));

    expect($result['content'][0]['text'])->toBe('tag-ticket done');
    Event::assertDispatched(ToolInvoked::class, fn (ToolInvoked $event): bool => $event->tool === 'tag-ticket');
});

it('audits a tool declared in a ToolSearch catalog', function (): void {
    $result = rpcResult(postMcp('mcp/dynamic', executeToolsMessage([
        ['name' => 'find-customer', 'arguments' => ['email' => fake()->safeEmail()]],
    ])));

    expect($result['isError'])->toBeFalse();
    Event::assertDispatched(ToolInvoked::class, fn (ToolInvoked $event): bool => $event->tool === 'find-customer');
});
