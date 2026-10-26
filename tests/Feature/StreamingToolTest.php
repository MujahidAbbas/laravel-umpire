<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use MujahidAbbas\Umpire\Events\ToolCallFailed;
use MujahidAbbas\Umpire\Events\ToolCallStarting;
use MujahidAbbas\Umpire\Events\ToolCallSucceeded;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\HelpdeskServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\ExportTicketsTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\ListAgentsTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\SummarizeQueueTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\TicketStatsTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\TriageQueueTool;

// Risk 2: a Generator's body runs only when core iterates it, after the method binding has
// returned and after core has forgotten mcp.request. The interceptor must pass the stream
// through untouched, capture the arguments up front, and settle the call when it ends.

beforeEach(function (): void {
    Event::fake([ToolCallStarting::class, ToolCallSucceeded::class, ToolCallFailed::class]);
});

it('settles a streamed call once the stream is exhausted, with the full duration', function (): void {
    HelpdeskServer::tool(SummarizeQueueTool::class, ['tickets' => 2, 'delay_ms' => 40])
        ->assertNotificationCount(2)
        ->assertSee('Summarized 2 tickets.')
        ->assertHasNoErrors();

    Event::assertDispatchedTimes(ToolCallSucceeded::class, 1);
    Event::assertDispatched(ToolCallSucceeded::class, fn (ToolCallSucceeded $event): bool => $event->tool === 'summarize-queue'
        && $event->arguments === ['tickets' => 2, 'delay_ms' => 40]
        && $event->durationMs >= 80
        && $event->isError === false);
});

it('marks a streamed call as an error when the stream yields an error response', function (): void {
    HelpdeskServer::tool(TriageQueueTool::class)
        ->assertNotificationCount(1)
        ->assertHasErrors(['Two tickets could not be triaged.']);

    Event::assertDispatched(ToolCallSucceeded::class, fn (ToolCallSucceeded $event): bool => $event->tool === 'triage-queue'
        && $event->isError === true);
});

it('records an exception thrown in the middle of a stream', function (): void {
    config(['app.debug' => true]);

    HelpdeskServer::tool(ExportTicketsTool::class)
        ->assertNotificationCount(1)
        ->assertHasErrors(['The export storage is full.']);

    Event::assertDispatched(ToolCallFailed::class, fn (ToolCallFailed $event): bool => $event->tool === 'export-tickets'
        && $event->exception->getMessage() === 'The export storage is full.');
    Event::assertNotDispatched(ToolCallSucceeded::class);
});

it('records a stream that is abandoned before it finishes', function (): void {
    // Resolving CallTool binds the server's tools, as it does on every tools/call.
    app()->make(HelpdeskServer::class, ['transport' => new FakeTransporter]);
    app()->make(CallTool::class);
    app()->instance('mcp.request', new Request(['tickets' => 3]));

    $stream = app()->call([app()->make(SummarizeQueueTool::class), 'handle']);
    $stream->current();
    unset($stream);

    Event::assertDispatched(ToolCallFailed::class, fn (ToolCallFailed $event): bool => $event->tool === 'summarize-queue'
        && $event->exception->getMessage() === 'The tool stream was abandoned before it finished.');
    Event::assertNotDispatched(ToolCallSucceeded::class);
});

it('checks every response in an array return', function (): void {
    HelpdeskServer::tool(ListAgentsTool::class)->assertHasNoErrors();

    Event::assertDispatched(ToolCallSucceeded::class, fn (ToolCallSucceeded $event): bool => $event->tool === 'list-agents'
        && $event->isError === false);
});

it('checks every response in a ResponseFactory return', function (): void {
    HelpdeskServer::tool(TicketStatsTool::class)->assertHasErrors(['The statistics are stale.']);

    Event::assertDispatched(ToolCallSucceeded::class, fn (ToolCallSucceeded $event): bool => $event->tool === 'ticket-stats'
        && $event->isError === true);
});

it('settles a streamed call over HTTP with the arguments it was called with', function (): void {
    $messages = postMcp('mcp/helpdesk', toolCallMessage('summarize-queue', ['tickets' => 3]));

    expect($messages)->toHaveCount(4)
        ->and(rpcResult($messages)['content'][0]['text'])->toBe('Summarized 3 tickets.');
    Event::assertDispatchedTimes(ToolCallSucceeded::class, 1);
    Event::assertDispatched(ToolCallSucceeded::class, fn (ToolCallSucceeded $event): bool => $event->arguments === ['tickets' => 3]);
});

it('settles a streamed call made through execute_tools over HTTP', function (): void {
    $result = rpcResult(postMcp('mcp/catalog', executeToolsMessage([
        ['name' => 'summarize-queue', 'arguments' => ['tickets' => 2]],
    ])));

    expect($result['isError'])->toBeFalse();
    Event::assertDispatchedTimes(ToolCallSucceeded::class, 1);
    Event::assertDispatched(ToolCallSucceeded::class, fn (ToolCallSucceeded $event): bool => $event->tool === 'summarize-queue'
        && $event->arguments === ['tickets' => 2]);
});
