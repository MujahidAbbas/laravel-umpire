<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use mujahidabbas\umpire\attributes\AUTHORIZE; // @phpstan-ignore class.nameCase
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

/**
 * Umpire's attribute, its name written in another case. PHP class names are case-insensitive.
 */
#[AUTHORIZE('issue-refunds')] // @phpstan-ignore class.nameCase
class OddCaseAuthorizeRefundTool extends Tool
{
    use AuthorizesWithGate;

    protected string $name = 'odd-case-authorize-refund';

    protected string $description = 'Refund an order (Umpire\'s #[Authorize] in another case).';

    public function handle(Request $request): Response
    {
        return Response::text('odd-case-authorize-refund done');
    }
}
