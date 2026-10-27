<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;

class QueueSummaryResource extends Resource
{
    protected string $uri = 'helpdesk://queue/summary';

    protected string $description = 'Open tickets per queue.';

    public function handle(Request $request): Response
    {
        return Response::text('queue-summary contents');
    }
}
