<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Policies;

use Illuminate\Contracts\Auth\Authenticatable;

class TicketPolicy
{
    /**
     * @var list<int|string>
     */
    public static array $exporters = [];

    public function export(Authenticatable $user): bool
    {
        return in_array($user->getAuthIdentifier(), static::$exporters, true);
    }
}
