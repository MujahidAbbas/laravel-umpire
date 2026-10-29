<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Illuminate\Routing\Attributes\Controllers\Authorize;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

/**
 * Laravel's controller attribute on handle(), as on a controller action.
 */
class FrameworkMethodAuthorizeRefundTool extends Tool
{
    use AuthorizesWithGate;

    protected string $name = 'framework-method-authorize-refund';

    protected string $description = 'Refund an order (Laravel\'s #[Authorize] on handle()).';

    #[Authorize('issue-refunds')]
    public function handle(Request $request): Response
    {
        return Response::text('framework-method-authorize-refund done');
    }
}
