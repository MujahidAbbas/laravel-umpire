<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Attributes\Authorize;

#[Authorize('issue-refunds')]
class UnguardedRefundTool extends Tool
{
    protected string $name = 'unguarded-refund';

    protected string $description = 'Refund an order (forgot the AuthorizesWithGate trait).';

    public function handle(Request $request): Response
    {
        return Response::text('unguarded-refund done');
    }
}
