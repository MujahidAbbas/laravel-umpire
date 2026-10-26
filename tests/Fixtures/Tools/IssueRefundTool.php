<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

#[Authorize('issue-refunds')]
class IssueRefundTool extends Tool
{
    use AuthorizesWithGate;

    protected string $name = 'issue-refund';

    protected string $description = 'Refund a customer\'s order.';

    public function handle(Request $request): Response
    {
        return Response::text('issue-refund done');
    }
}
