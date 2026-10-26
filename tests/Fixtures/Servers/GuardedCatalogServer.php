<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\ToolSearch;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\IssueRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\LookupTicketTool;

#[Name('Helpdesk guarded catalog')]
class GuardedCatalogServer extends Server
{
    protected array $tools = [
        ToolSearch::class => [
            LookupTicketTool::class,
            IssueRefundTool::class,
        ],
    ];
}
