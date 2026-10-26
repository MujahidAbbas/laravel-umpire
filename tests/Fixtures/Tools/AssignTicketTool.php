<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class AssignTicketTool extends Tool
{
    protected string $name = 'assign-ticket';

    protected string $description = 'Assign a ticket to an agent.';

    public function handle(Request $request): Response
    {
        return Response::text('assign-ticket done');
    }
}
