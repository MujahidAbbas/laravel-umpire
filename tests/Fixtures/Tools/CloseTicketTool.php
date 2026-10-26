<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Illuminate\Auth\Access\AuthorizationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CloseTicketTool extends Tool
{
    protected string $name = 'close-ticket';

    protected string $description = 'Close a ticket.';

    public function handle(Request $request): Response
    {
        throw new AuthorizationException('Only the ticket owner may close it.');
    }
}
