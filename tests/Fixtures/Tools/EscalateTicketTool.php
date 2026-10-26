<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

class EscalateTicketTool extends Tool
{
    protected string $name = 'escalate-ticket';

    protected string $description = 'Page the on-call agent about a ticket.';

    public function handle(Request $request): Response
    {
        throw new RuntimeException('The on-call pager is unreachable.');
    }
}
