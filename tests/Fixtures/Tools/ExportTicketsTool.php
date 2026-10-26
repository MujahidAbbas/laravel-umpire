<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Generator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use RuntimeException;

class ExportTicketsTool extends Tool
{
    protected string $name = 'export-tickets';

    protected string $description = 'Export tickets, streaming progress.';

    public function handle(Request $request): Generator
    {
        yield Response::notification('export/progress', ['done' => 1]);

        throw new RuntimeException('The export storage is full.');
    }
}
