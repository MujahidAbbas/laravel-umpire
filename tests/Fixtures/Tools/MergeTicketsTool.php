<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class MergeTicketsTool extends Tool
{
    protected string $name = 'merge-tickets';

    protected string $description = 'Merge duplicate tickets.';

    public function handle(Request $request): Response
    {
        return Response::text('merge-tickets done');
    }
}
