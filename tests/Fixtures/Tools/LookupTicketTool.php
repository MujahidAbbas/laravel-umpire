<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class LookupTicketTool extends Tool
{
    protected string $name = 'lookup-ticket';

    protected string $description = 'Look up a ticket by its reference.';

    public function handle(Request $request): Response
    {
        return Response::text('lookup-ticket done');
    }
}
