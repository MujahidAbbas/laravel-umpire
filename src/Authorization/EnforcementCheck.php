<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Authorization;

use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;
use MujahidAbbas\Umpire\Exceptions\AuthorizationNotEnforced;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

/**
 * Fails closed when a tool, resource or prompt declares #[Authorize] but its shouldRegister() is
 * not the one from AuthorizesWithGate: laravel/mcp only calls shouldRegister(), so the attribute
 * alone protects nothing, and a class's own shouldRegister() silently replaces the trait's. Also
 * fails closed on an #[Authorize] the trait never reads: another class of that name, or Umpire's
 * declared on a method or a trait.
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

            if (class_exists($class) && $this->declaresAuthorize(new ReflectionClass($class))) {
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
     * Whether the class or a parent declares Umpire's #[Authorize], read by name so that nothing is
     * instantiated: an attribute whose class does not exist must reach this check, not PHP's error.
     *
     * @param  ReflectionClass<object>  $class
     *
     * @throws AuthorizationNotEnforced
     */
    protected function declaresAuthorize(ReflectionClass $class): bool
    {
        $declared = false;

        for ($reflection = $class; $reflection !== false; $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getAttributes() as $attribute) {
                if ($this->isUmpires($attribute)) {
                    $declared = true;
                } elseif ($this->isNamedAuthorize($attribute)) {
                    throw AuthorizationNotEnforced::becauseAttributeIsNotUmpires($class->getName(), $attribute->getName(), $reflection->getName());
                }
            }

            foreach ($this->traitsOf($reflection) as $trait) {
                foreach ($trait->getAttributes() as $attribute) {
                    if ($this->isNamedAuthorize($attribute)) {
                        throw AuthorizationNotEnforced::becauseAttributeIsOnTrait($class->getName(), $attribute->getName(), $trait->getName());
                    }
                }
            }
        }

        foreach ($class->getMethods() as $method) {
            foreach ($method->getAttributes() as $attribute) {
                if ($this->isNamedAuthorize($attribute)) {
                    throw AuthorizationNotEnforced::becauseAttributeIsOnMethod($class->getName(), $attribute->getName(), "{$method->class}::{$method->name}()");
                }
            }
        }

        return $declared;
    }

    /**
     * PHP class names ignore case, and so does getAttributes(Authorize::class).
     *
     * @param  ReflectionAttribute<object>  $attribute
     */
    protected function isUmpires(ReflectionAttribute $attribute): bool
    {
        return strcasecmp($attribute->getName(), Authorize::class) === 0;
    }

    /**
     * @param  ReflectionAttribute<object>  $attribute
     */
    protected function isNamedAuthorize(ReflectionAttribute $attribute): bool
    {
        $name = $attribute->getName();
        $separator = strrpos($name, '\\');

        return strcasecmp($separator === false ? $name : substr($name, $separator + 1), 'Authorize') === 0;
    }

    /**
     * The traits a class uses directly, and the traits those traits use.
     *
     * @param  ReflectionClass<object>  $class
     * @return list<ReflectionClass<object>>
     */
    protected function traitsOf(ReflectionClass $class): array
    {
        $traits = [];

        foreach ($class->getTraits() as $trait) {
            $traits = [...$traits, $trait, ...$this->traitsOf($trait)];
        }

        return $traits;
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
