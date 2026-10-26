<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

#[Authorize('issue-refunds')]
class AliasingRefundTool extends Tool
{
    use AuthorizesWithGate {
        shouldRegister as protected authorizedByGate;
    }

    protected string $name = 'aliasing-refund';

    protected string $description = 'Refund an order (wraps the trait\'s method in its own).';

    public function shouldRegister(Request $request): bool
    {
        return $this->authorizedByGate($request) && $request->user() !== null;
    }

    public function handle(Request $request): Response
    {
        return Response::text('aliasing-refund done');
    }
}
