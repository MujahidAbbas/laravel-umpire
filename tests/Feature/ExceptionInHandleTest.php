<?php

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Validation\ValidationException;
use MujahidAbbas\Umpire\Events\ToolCallFailed;
use MujahidAbbas\Umpire\Events\ToolCallStarting;
use MujahidAbbas\Umpire\Events\ToolCallSucceeded;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\HelpdeskServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\CloseTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\CreateTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\EscalateTicketTool;

// Risk 1: core's ToolInvoker catches every exception from handle() and turns it into an
// isError result. The interceptor runs inside that try, so it must record the failure and
// rethrow the same exception, leaving core's response and its report() call unchanged.

beforeEach(function (): void {
    Event::fake([ToolCallStarting::class, ToolCallSucceeded::class, ToolCallFailed::class]);
    Exceptions::fake();
});

it('records an exception and leaves the generic error response and its report unchanged', function (): void {
    config(['app.debug' => false]);
    $ticket = fake()->uuid();

    HelpdeskServer::tool(EscalateTicketTool::class, ['ticket' => $ticket])
        ->assertHasErrors(['An internal server error occurred.']);

    Event::assertDispatched(ToolCallStarting::class, fn (ToolCallStarting $event): bool => $event->tool === 'escalate-ticket'
        && $event->arguments === ['ticket' => $ticket]);
    Event::assertDispatched(ToolCallFailed::class, fn (ToolCallFailed $event): bool => $event->tool === 'escalate-ticket'
        && $event->arguments === ['ticket' => $ticket]
        && $event->exception instanceof RuntimeException
        && $event->exception->getMessage() === 'The on-call pager is unreachable.');
    Event::assertNotDispatched(ToolCallSucceeded::class);
    Exceptions::assertReportedCount(1);
});

it('leaves the exception message in the response when debug is on', function (): void {
    config(['app.debug' => true]);

    HelpdeskServer::tool(EscalateTicketTool::class, ['ticket' => fake()->uuid()])
        ->assertHasErrors(['The on-call pager is unreachable.']);

    Event::assertDispatchedTimes(ToolCallFailed::class, 1);
});

it('records a validation failure and keeps core\'s validation message', function (): void {
    HelpdeskServer::tool(CreateTicketTool::class, [])
        ->assertHasErrors(['The subject field is required.']);

    Event::assertDispatched(ToolCallFailed::class, fn (ToolCallFailed $event): bool => $event->tool === 'create-ticket'
        && $event->exception instanceof ValidationException);
    Exceptions::assertNothingReported();
});

it('records an authorization failure without reporting it', function (): void {
    HelpdeskServer::tool(CloseTicketTool::class, ['ticket' => fake()->uuid()])
        ->assertHasErrors(['Only the ticket owner may close it.']);

    Event::assertDispatched(ToolCallFailed::class, fn (ToolCallFailed $event): bool => $event->tool === 'close-ticket'
        && $event->exception instanceof AuthorizationException);
    Exceptions::assertNothingReported();
});

it('records an exception on a direct call over HTTP', function (): void {
    config(['app.debug' => false]);
    $ticket = fake()->uuid();

    $result = rpcResult(postMcp('mcp/helpdesk', toolCallMessage('escalate-ticket', ['ticket' => $ticket])));

    expect($result['isError'])->toBeTrue()
        ->and($result['content'][0]['text'])->toBe('An internal server error occurred.');
    Event::assertDispatched(ToolCallFailed::class, fn (ToolCallFailed $event): bool => $event->tool === 'escalate-ticket'
        && $event->arguments === ['ticket' => $ticket]);
    Exceptions::assertReportedCount(1);
});

it('records an exception inside execute_tools over HTTP', function (): void {
    config(['app.debug' => false]);
    $ticket = fake()->uuid();

    $result = rpcResult(postMcp('mcp/catalog', executeToolsMessage([
        ['name' => 'escalate-ticket', 'arguments' => ['ticket' => $ticket]],
    ])));

    expect($result['isError'])->toBeTrue();
    Event::assertDispatched(ToolCallFailed::class, fn (ToolCallFailed $event): bool => $event->tool === 'escalate-ticket'
        && $event->arguments === ['ticket' => $ticket]);
    Exceptions::assertReportedCount(1);
});
