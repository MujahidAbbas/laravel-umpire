<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

/**
 * Inherits an unresolved #[Authorize] from its parent.
 */
class UnimportedAuthorizeSubclassRefundTool extends UnimportedAuthorizeRefundTool
{
    protected string $name = 'unimported-authorize-subclass-refund';
}
