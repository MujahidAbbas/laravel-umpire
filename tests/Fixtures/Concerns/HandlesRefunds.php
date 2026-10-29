<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Concerns;

/**
 * Brings in RequiresRefundAbility and its #[Authorize] one trait down.
 */
trait HandlesRefunds
{
    use RequiresRefundAbility;
}
