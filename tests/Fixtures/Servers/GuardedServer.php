<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\DeleteCustomerTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\ExportTicketHistoryTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\IssueGoodwillCreditTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\IssueRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\LookupTicketTool;

#[Name('Helpdesk guarded')]
class GuardedServer extends Server
{
    protected array $tools = [
        LookupTicketTool::class,
        IssueRefundTool::class,
        IssueGoodwillCreditTool::class,
        DeleteCustomerTool::class,
        ExportTicketHistoryTool::class,
    ];
}
