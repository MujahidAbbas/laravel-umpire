<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;
use MujahidAbbas\Umpire\Tests\Fixtures\Models\Ticket;

#[Authorize('export', Ticket::class)]
class ExportTicketHistoryTool extends Tool
{
    use AuthorizesWithGate;

    protected string $name = 'export-ticket-history';

    protected string $description = 'Export the full history of every ticket.';

    public function handle(Request $request): Response
    {
        return Response::text('export-ticket-history done');
    }
}
