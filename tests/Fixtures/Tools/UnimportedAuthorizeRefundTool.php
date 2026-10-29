<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

/**
 * No use line for the attribute, so PHP resolves it to a class in this namespace that does not exist.
 */
#[Authorize('issue-refunds')] // @phpstan-ignore attribute.notFound
class UnimportedAuthorizeRefundTool extends Tool
{
    use AuthorizesWithGate;

    protected string $name = 'unimported-authorize-refund';

    protected string $description = 'Refund an order (#[Authorize] without its use line).';

    public function handle(Request $request): Response
    {
        return Response::text('unimported-authorize-refund done');
    }
}
