<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class TagTicketTool extends Tool
{
    protected string $name = 'tag-ticket';

    protected string $description = 'Tag a ticket.';

    public function handle(Request $request): Response
    {
        return Response::text('tag-ticket done');
    }
}
