<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;
use MujahidAbbas\Umpire\Tests\Fixtures\Concerns\HandlesRefunds;

/**
 * #[Authorize] lives on a trait that a trait this tool uses brings in.
 */
class NestedTraitAuthorizeRefundTool extends Tool
{
    use AuthorizesWithGate;
    use HandlesRefunds;

    protected string $name = 'nested-trait-authorize-refund';

    protected string $description = 'Refund an order (#[Authorize] on a trait used by a used trait).';

    public function handle(Request $request): Response
    {
        return Response::text('nested-trait-authorize-refund done');
    }
}
