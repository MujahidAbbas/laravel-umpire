<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

#[Authorize('issue-refunds')]
class OverridingRefundTool extends Tool
{
    use AuthorizesWithGate;

    protected string $name = 'overriding-refund';

    protected string $description = 'Refund an order (its own shouldRegister() replaces the trait\'s).';

    public function shouldRegister(Request $request): bool
    {
        return true;
    }

    public function handle(Request $request): Response
    {
        return Response::text('overriding-refund done');
    }
}
