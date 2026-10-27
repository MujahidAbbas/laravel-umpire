<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

#[Authorize('issue-refunds')]
class OverridingRefundPolicyResource extends Resource
{
    use AuthorizesWithGate;

    protected string $uri = 'helpdesk://policies/refunds-overriding';

    protected string $description = 'The refund policy (its own shouldRegister() replaces the trait\'s).';

    public function shouldRegister(Request $request): bool
    {
        return true;
    }

    public function handle(Request $request): Response
    {
        return Response::text('overriding-refund-policy contents');
    }
}
