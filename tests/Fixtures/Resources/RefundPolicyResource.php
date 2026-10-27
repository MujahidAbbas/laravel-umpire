<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

#[Authorize('issue-refunds')]
class RefundPolicyResource extends Resource
{
    use AuthorizesWithGate;

    protected string $uri = 'helpdesk://policies/refunds';

    protected string $description = 'The refund policy agents follow.';

    public function handle(Request $request): Response
    {
        return Response::text('refund-policy contents');
    }
}
