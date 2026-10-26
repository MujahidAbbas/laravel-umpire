<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\ToolSearch;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\CreateTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\EscalateTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\ExportTicketsTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\LookupTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\SummarizeQueueTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\TicketStatsTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\VerifyCustomerTool;

#[Name('Helpdesk catalog')]
class CatalogServer extends Server
{
    protected array $tools = [
        ToolSearch::class => [
            CreateTicketTool::class,
            EscalateTicketTool::class,
            SummarizeQueueTool::class,
            ExportTicketsTool::class,
            VerifyCustomerTool::class,
            LookupTicketTool::class,
            TicketStatsTool::class,
        ],
    ];
}
