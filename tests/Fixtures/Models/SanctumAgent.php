<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Models;

use Illuminate\Foundation\Auth\User;
use Laravel\Sanctum\HasApiTokens;

/**
 * A help-desk agent who authenticates through Sanctum. Shares the users table with Agent.
 */
class SanctumAgent extends User
{
    use HasApiTokens;

    protected $table = 'users';

    protected $guarded = [];
}
