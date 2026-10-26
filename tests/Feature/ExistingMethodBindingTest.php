<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Mcp\Response;
use MujahidAbbas\Umpire\Events\ToolCallFailed;
use MujahidAbbas\Umpire\Events\ToolCallStarting;
use MujahidAbbas\Umpire\Events\ToolCallSucceeded;
use MujahidAbbas\Umpire\Exceptions\ToolCannotBeAudited;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\HelpdeskServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\CreateTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\LookupTicketTool;

// Risk 4, decided 2026-10-26: fail closed. Container::bindMethod() keeps one binding per
// Class@method and overwrites silently, so Umpire refuses a tool call it cannot audit rather
// than letting it run unrecorded.

beforeEach(function (): void {
    Event::fake([ToolCallStarting::class, ToolCallSucceeded::class, ToolCallFailed::class]);
});

function bindLookupTicketInTheApp(): void
{
    app()->bindMethod([LookupTicketTool::class, 'handle'], fn (LookupTicketTool $tool): Response => Response::text('Handled by the app binding.'));
}

it('refuses a tool call when the app already bound the tool\'s handle()', function (): void {
    bindLookupTicketInTheApp();

    HelpdeskServer::tool(LookupTicketTool::class);
})->throws(ToolCannotBeAudited::class, 'Remove the bindMethod() call for ['.LookupTicketTool::class.'@handle]');

it('refuses every tool call on that server, not only the bound tool', function (): void {
    bindLookupTicketInTheApp();

    HelpdeskServer::tool(CreateTicketTool::class, ['subject' => fake()->sentence()]);
})->throws(ToolCannotBeAudited::class);

it('answers a refused call over HTTP with a JSON-RPC error and reports the reason', function (): void {
    config(['app.debug' => false]);
    Exceptions::fake();
    bindLookupTicketInTheApp();

    $message = toolCallMessage('lookup-ticket');

    // laravel/mcp maps JSON-RPC -32603 (internal error) to HTTP 500.
    $this->postJson('mcp/helpdesk', $message, [
        'MCP-Protocol-Version' => '2026-07-28',
        'Mcp-Method' => 'tools/call',
        'Mcp-Name' => 'lookup-ticket',
    ])->assertStatus(500)->assertJsonPath('error.code', -32603);

    Exceptions::assertReported(ToolCannotBeAudited::class);
    Event::assertNotDispatched(ToolCallStarting::class);
});

it('does not mistake its own binding for the app\'s on later calls', function (): void {
    HelpdeskServer::tool(LookupTicketTool::class)->assertSee('lookup-ticket done');
    HelpdeskServer::tool(LookupTicketTool::class)->assertSee('lookup-ticket done');

    Event::assertDispatchedTimes(ToolCallSucceeded::class, 2);
});

it('cannot see a binding the app makes after Umpire bound the tool (documented limitation)', function (): void {
    HelpdeskServer::tool(LookupTicketTool::class)->assertSee('lookup-ticket done');

    bindLookupTicketInTheApp();

    HelpdeskServer::tool(LookupTicketTool::class)->assertSee('Handled by the app binding.');

    Event::assertDispatchedTimes(ToolCallSucceeded::class, 1);
});
