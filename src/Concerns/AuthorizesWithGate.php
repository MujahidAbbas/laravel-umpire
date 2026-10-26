<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Concerns;

use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate;
use Laravel\Mcp\Request;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Support\Attributes;

/**
 * Enforces the tool's #[Authorize] attributes, including those declared on parent classes.
 * laravel/mcp asks shouldRegister() before it lists, searches or calls a tool; a tool the user
 * may not use is hidden, and calling it fails as if it did not exist.
 */
trait AuthorizesWithGate
{
    public function shouldRegister(Request $request): bool
    {
        $gate = Container::getInstance()->make(Gate::class)->forUser($request->user());

        foreach (Attributes::all($this, Authorize::class) as $authorize) {
            if ($gate->denies($authorize->ability, $authorize->arguments)) {
                return false;
            }
        }

        return true;
    }
}
