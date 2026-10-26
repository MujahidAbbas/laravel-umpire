<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Models;

use Laravel\Passport\Token;

/**
 * An access token model an app moved to a connection of its own.
 */
class SecondaryToken extends Token
{
    protected $connection = 'secondary';
}
