<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire;

use Closure;
use MujahidAbbas\Umpire\Events\ToolFailed;
use MujahidAbbas\Umpire\Events\ToolInvoked;

class Umpire
{
    /**
     * @var (Closure(ToolInvoked|ToolFailed): (int|string|null))|null
     */
    protected static ?Closure $tenantResolver = null;

    /**
     * Resolve the tenant id stored with each audited call. The closure receives the ToolInvoked
     * or ToolFailed event and returns the tenant's id, or null. Pass null to stop resolving.
     *
     * @param  (Closure(ToolInvoked|ToolFailed): (int|string|null))|null  $resolver
     */
    public static function resolveTenantUsing(?Closure $resolver): void
    {
        static::$tenantResolver = $resolver;
    }

    /**
     * @internal
     */
    public static function tenantFor(ToolInvoked|ToolFailed $event): ?string
    {
        $tenant = static::$tenantResolver === null ? null : (static::$tenantResolver)($event);

        return $tenant === null ? null : (string) $tenant;
    }
}
