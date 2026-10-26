<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Tools\ToolSearch;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\AssignTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\FindCustomerTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\LookupTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\MergeTicketsTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\TagTicketTool;

/**
 * Registers one tool in each way laravel/mcp allows. laravel/mcp's own test suite adds tools
 * in boot() (tests/Fixtures/ServerWithDynamicTools.php), so that path is supported upstream.
 */
#[Name('Helpdesk dynamic')]
class DynamicServer extends Server
{
    protected array $tools = [
        LookupTicketTool::class,
        ToolSearch::class => [FindCustomerTool::class],
    ];

    public function __construct(Transport $transport)
    {
        parent::__construct($transport);

        $this->tools[] = AssignTicketTool::class;
    }

    protected function boot(): void
    {
        $this->tools[] = MergeTicketsTool::class;
        $this->tools[] = new TagTicketTool;
    }
}
