<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

/**
 * Inherits Laravel's #[Authorize] from its parent.
 */
class FrameworkAuthorizeSubclassRefundTool extends FrameworkAuthorizeRefundTool
{
    protected string $name = 'framework-authorize-subclass-refund';
}
