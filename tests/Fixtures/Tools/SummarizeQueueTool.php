<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Generator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class SummarizeQueueTool extends Tool
{
    protected string $name = 'summarize-queue';

    protected string $description = 'Summarize the open ticket queue, streaming progress.';

    public function handle(Request $request): Generator
    {
        $tickets = $request->integer('tickets', 2);

        for ($i = 1; $i <= $tickets; $i++) {
            usleep($request->integer('delay_ms') * 1000);

            yield Response::notification('queue/progress', ['done' => $i, 'of' => $tickets]);
        }

        yield Response::text("Summarized {$tickets} tickets.");
    }
}
