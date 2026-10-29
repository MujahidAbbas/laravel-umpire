<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Authorize;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

/**
 * Imports a global Authorize class that does not exist, the name #[\Authorize] resolves to.
 */
#[Authorize('issue-refunds')] // @phpstan-ignore attribute.notFound
class GlobalAuthorizeRefundTool extends Tool
{
    use AuthorizesWithGate;

    protected string $name = 'global-authorize-refund';

    protected string $description = 'Refund an order (#[\Authorize], a global class).';

    public function handle(Request $request): Response
    {
        return Response::text('global-authorize-refund done');
    }
}
