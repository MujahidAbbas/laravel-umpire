<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Exceptions;

use LogicException;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

class AuthorizationNotEnforced extends LogicException
{
    public static function becauseTraitIsMissing(string $class): self
    {
        return new self(
            "Umpire cannot enforce #[Authorize] on [{$class}]: the class does not use the ".AuthorizesWithGate::class.' trait, '
            .'so laravel/mcp would list it and let every user use it. Add `use AuthorizesWithGate;` to the class.',
        );
    }

    public static function becauseShouldRegisterIsOverridden(string $class): self
    {
        return new self(
            "Umpire cannot enforce #[Authorize] on [{$class}]: the class defines its own shouldRegister(), which replaces "
            .'the one from AuthorizesWithGate, so the attribute would be ignored. Remove that shouldRegister(), or remove '
            .'#[Authorize] and check the Gate inside your own shouldRegister().',
        );
    }

    public static function becauseAttributeIsNotUmpires(string $class, string $attribute, string $declaredOn): self
    {
        $where = $declaredOn === $class ? 'the class' : "its parent [{$declaredOn}]";
        $missing = class_exists($attribute) ? '' : ' That class does not exist, so the use line is probably missing.';

        return new self(
            "Umpire cannot enforce #[Authorize] on [{$class}]: {$where} carries #[\\{$attribute}], which is not Umpire's "
            ."attribute and protects nothing.{$missing} Import Umpire's: `use ".Authorize::class.';`.',
        );
    }

    public static function becauseAttributeIsOnMethod(string $class, string $attribute, string $method): self
    {
        return new self(
            "Umpire cannot enforce #[\\{$attribute}] on [{$class}]: it is declared on {$method}, and Umpire only reads "
            .'#[Authorize] on the class and its parents. Declare it on the class, with `use '.Authorize::class.';`.',
        );
    }

    public static function becauseAttributeIsOnTrait(string $class, string $attribute, string $trait): self
    {
        return new self(
            "Umpire cannot enforce #[\\{$attribute}] on [{$class}]: it is declared on the trait [{$trait}], and PHP does not "
            .'give a class the attributes of the traits it uses. Declare it on the class, with `use '.Authorize::class.';`.',
        );
    }
}
