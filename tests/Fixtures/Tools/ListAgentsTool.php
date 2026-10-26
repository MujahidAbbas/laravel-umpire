<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListAgentsTool extends Tool
{
    protected string $name = 'list-agents';

    protected string $description = 'List the agents on shift.';

    /**
     * @return array<int, Response>
     */
    public function handle(Request $request): array
    {
        return [
            Response::text('Agent: '.fake()->name()),
            Response::text('Agent: '.fake()->name()),
        ];
    }
}
