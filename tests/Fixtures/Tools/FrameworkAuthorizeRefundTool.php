<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Illuminate\Routing\Attributes\Controllers\Authorize;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

/**
 * Imports Laravel's controller attribute instead of Umpire's.
 */
#[Authorize('issue-refunds')]
class FrameworkAuthorizeRefundTool extends Tool
{
    use AuthorizesWithGate;

    protected string $name = 'framework-authorize-refund';

    protected string $description = 'Refund an order (Laravel\'s #[Authorize] imported by mistake).';

    public function handle(Request $request): Response
    {
        return Response::text('framework-authorize-refund done');
    }
}
