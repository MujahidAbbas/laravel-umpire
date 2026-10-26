<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Generator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class TriageQueueTool extends Tool
{
    protected string $name = 'triage-queue';

    protected string $description = 'Triage the queue, streaming progress.';

    public function handle(Request $request): Generator
    {
        yield Response::notification('triage/progress', ['done' => 1]);

        yield Response::error('Two tickets could not be triaged.');
    }
}
