<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * A help-desk agent who authenticates through Passport.
 */
class Agent extends User implements OAuthenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    protected $guarded = [];
}
