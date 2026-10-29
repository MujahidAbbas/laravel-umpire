<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

/**
 * Umpire's attribute on handle() instead of the class. PHP checks the target only on newInstance().
 */
class MethodAuthorizeRefundTool extends Tool
{
    use AuthorizesWithGate;

    protected string $name = 'method-authorize-refund';

    protected string $description = 'Refund an order (#[Authorize] on handle()).';

    #[Authorize('issue-refunds')] // @phpstan-ignore attribute.target
    public function handle(Request $request): Response
    {
        return Response::text('method-authorize-refund done');
    }
}
