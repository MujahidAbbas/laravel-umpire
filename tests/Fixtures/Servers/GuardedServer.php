<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use MujahidAbbas\Umpire\Tests\Fixtures\Prompts\DraftRefundReplyPrompt;
use MujahidAbbas\Umpire\Tests\Fixtures\Prompts\SummarizeTicketPrompt;
use MujahidAbbas\Umpire\Tests\Fixtures\Resources\CustomerRecordResource;
use MujahidAbbas\Umpire\Tests\Fixtures\Resources\QueueSummaryResource;
use MujahidAbbas\Umpire\Tests\Fixtures\Resources\RefundPolicyResource;
use MujahidAbbas\Umpire\Tests\Fixtures\Resources\VipCustomerListResource;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\DeleteCustomerTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\ExportTicketHistoryTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\IssueGoodwillCreditTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\IssueRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\LookupTicketTool;

#[Name('Helpdesk guarded')]
class GuardedServer extends Server
{
    protected array $capabilities = [
        self::CAPABILITY_TOOLS => ['listChanged' => false],
        self::CAPABILITY_RESOURCES => ['listChanged' => false],
        self::CAPABILITY_PROMPTS => ['listChanged' => false],
        self::CAPABILITY_COMPLETIONS => [],
    ];

    protected array $tools = [
        LookupTicketTool::class,
        IssueRefundTool::class,
        IssueGoodwillCreditTool::class,
        DeleteCustomerTool::class,
        ExportTicketHistoryTool::class,
    ];

    protected array $resources = [
        QueueSummaryResource::class,
        RefundPolicyResource::class,
        VipCustomerListResource::class,
        CustomerRecordResource::class,
    ];

    protected array $prompts = [
        SummarizeTicketPrompt::class,
        DraftRefundReplyPrompt::class,
    ];
}
