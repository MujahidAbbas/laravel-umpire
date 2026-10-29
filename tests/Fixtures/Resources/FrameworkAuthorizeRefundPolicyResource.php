<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Resources;

use Illuminate\Routing\Attributes\Controllers\Authorize;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

/**
 * Imports Laravel's controller attribute instead of Umpire's.
 */
#[Authorize('issue-refunds')]
class FrameworkAuthorizeRefundPolicyResource extends Resource
{
    use AuthorizesWithGate;

    protected string $uri = 'helpdesk://policies/framework-refunds';

    protected string $description = 'The refund policy agents follow.';

    public function handle(Request $request): Response
    {
        return Response::text('framework-refund-policy contents');
    }
}
