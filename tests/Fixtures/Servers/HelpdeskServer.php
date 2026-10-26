<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\CloseTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\CreateTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\EscalateTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\ExportTicketsTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\ListAgentsTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\LookupTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\SummarizeQueueTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\TicketStatsTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\TriageQueueTool;

#[Name('Helpdesk')]
class HelpdeskServer extends Server
{
    protected array $tools = [
        LookupTicketTool::class,
        CreateTicketTool::class,
        EscalateTicketTool::class,
        CloseTicketTool::class,
        SummarizeQueueTool::class,
        ExportTicketsTool::class,
        TriageQueueTool::class,
        ListAgentsTool::class,
        TicketStatsTool::class,
    ];
}
