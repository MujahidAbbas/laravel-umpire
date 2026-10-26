<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

class TicketStatsTool extends Tool
{
    protected string $name = 'ticket-stats';

    protected string $description = 'Report queue statistics.';

    public function handle(Request $request): ResponseFactory
    {
        return Response::make([
            Response::text('Open tickets: '.fake()->numberBetween(1, 50)),
            Response::error('The statistics are stale.'),
        ]);
    }
}
