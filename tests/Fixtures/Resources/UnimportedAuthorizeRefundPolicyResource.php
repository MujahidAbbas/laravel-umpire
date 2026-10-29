<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

/**
 * No use line for the attribute, so PHP resolves it to a class in this namespace that does not exist.
 */
#[Authorize('issue-refunds')] // @phpstan-ignore attribute.notFound
class UnimportedAuthorizeRefundPolicyResource extends Resource
{
    use AuthorizesWithGate;

    protected string $uri = 'helpdesk://policies/unimported-refunds';

    protected string $description = 'The refund policy agents follow.';

    public function handle(Request $request): Response
    {
        return Response::text('unimported-refund-policy contents');
    }
}
