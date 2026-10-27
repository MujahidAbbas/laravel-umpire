<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use MujahidAbbas\Umpire\Attributes\Authorize;

#[Authorize('issue-refunds')]
class UnguardedRefundPolicyResource extends Resource
{
    protected string $uri = 'helpdesk://policies/refunds-unguarded';

    protected string $description = 'The refund policy (forgot the AuthorizesWithGate trait).';

    public function handle(Request $request): Response
    {
        return Response::text('unguarded-refund-policy contents');
    }
}
