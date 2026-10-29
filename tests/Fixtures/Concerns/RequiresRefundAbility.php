<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Concerns;

use MujahidAbbas\Umpire\Attributes\Authorize;

/**
 * PHP does not copy a trait's attributes onto the class that uses it.
 */
#[Authorize('issue-refunds')]
trait RequiresRefundAbility {}
