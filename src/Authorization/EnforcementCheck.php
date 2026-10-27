<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Authorization;

use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;
use MujahidAbbas\Umpire\Exceptions\AuthorizationNotEnforced;
use MujahidAbbas\Umpire\Support\Attributes;
use ReflectionMethod;

/**
 * Fails closed when a tool, resource or prompt declares #[Authorize] but its shouldRegister() is
 * not the one from AuthorizesWithGate: laravel/mcp only calls shouldRegister(), so the attribute
 * alone protects nothing, and a class's own shouldRegister() silently replaces the trait's.
 *
 * @internal
 */
class EnforcementCheck
{
    /**
     * Classes already checked. Their code cannot change while the process runs.
     *
     * @var array<string, true>
     */
    protected array $verified = [];

    /**
     * @param  list<string>  $classes
     *
     * @throws AuthorizationNotEnforced
     */
    public function verify(array $classes): void
    {
        foreach ($classes as $class) {
            if (isset($this->verified[$class])) {
                continue;
            }

            if (class_exists($class) && Attributes::all($class, Authorize::class) !== []) {
                if (! method_exists($class, 'shouldRegister')) {
                    throw AuthorizationNotEnforced::becauseTraitIsMissing($class);
                }

                if (! $this->isTraitMethod(new ReflectionMethod($class, 'shouldRegister'))) {
                    throw AuthorizationNotEnforced::becauseShouldRegisterIsOverridden($class);
                }
            }

            $this->verified[$class] = true;
        }
    }

    /**
     * A method a class imports from a trait keeps the trait's file and line; an override, an alias
     * wrapped in the class's own method, or a subclass override does not.
     */
    protected function isTraitMethod(ReflectionMethod $method): bool
    {
        $trait = new ReflectionMethod(AuthorizesWithGate::class, 'shouldRegister');

        return $method->getFileName() === $trait->getFileName()
            && $method->getStartLine() === $trait->getStartLine();
    }
}
