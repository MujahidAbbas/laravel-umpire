<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Exceptions;

use LogicException;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

class AuthorizationNotEnforced extends LogicException
{
    public static function becauseTraitIsMissing(string $class): self
    {
        return new self(
            "Umpire cannot enforce #[Authorize] on [{$class}]: the tool does not use the ".AuthorizesWithGate::class.' trait, '
            .'so laravel/mcp would list and run it for every user. Add `use AuthorizesWithGate;` to the tool.',
        );
    }

    public static function becauseShouldRegisterIsOverridden(string $class): self
    {
        return new self(
            "Umpire cannot enforce #[Authorize] on [{$class}]: the tool defines its own shouldRegister(), which replaces "
            .'the one from AuthorizesWithGate, so the attribute would be ignored. Remove that shouldRegister(), or remove '
            .'#[Authorize] and check the Gate inside your own shouldRegister().',
        );
    }
}
