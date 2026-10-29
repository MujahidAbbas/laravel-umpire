<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;
use MujahidAbbas\Umpire\Tests\Fixtures\Concerns\RequiresRefundAbility;

/**
 * #[Authorize] lives on a trait this tool uses.
 */
class TraitAuthorizeRefundTool extends Tool
{
    use AuthorizesWithGate;
    use RequiresRefundAbility;

    protected string $name = 'trait-authorize-refund';

    protected string $description = 'Refund an order (#[Authorize] on a used trait).';

    public function handle(Request $request): Response
    {
        return Response::text('trait-authorize-refund done');
    }
}
