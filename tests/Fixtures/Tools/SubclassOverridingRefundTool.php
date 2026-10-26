<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;

/**
 * Inherits #[Authorize] and the trait from IssueRefundTool, then overrides shouldRegister().
 */
class SubclassOverridingRefundTool extends IssueRefundTool
{
    protected string $name = 'subclass-overriding-refund';

    public function shouldRegister(Request $request): bool
    {
        return true;
    }
}
