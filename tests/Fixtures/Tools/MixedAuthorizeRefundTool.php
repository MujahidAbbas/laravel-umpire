<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Illuminate\Routing\Attributes\Controllers\Authorize as ControllerAuthorize;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

/**
 * Umpire's attribute next to Laravel's: only the first is enforced.
 */
#[Authorize('view-customers')]
#[ControllerAuthorize('issue-refunds')]
class MixedAuthorizeRefundTool extends Tool
{
    use AuthorizesWithGate;

    protected string $name = 'mixed-authorize-refund';

    protected string $description = 'Refund an order (Umpire\'s and Laravel\'s #[Authorize] together).';

    public function handle(Request $request): Response
    {
        return Response::text('mixed-authorize-refund done');
    }
}
