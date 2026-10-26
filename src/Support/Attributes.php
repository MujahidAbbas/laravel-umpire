<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Support;

use ReflectionClass;

/**
 * @internal
 */
class Attributes
{
    /**
     * Instances of an attribute declared on a class or any of its parents. PHP does not inherit
     * attributes, so a tool that extends a protected tool would otherwise lose them.
     *
     * @template TAttribute of object
     *
     * @param  class-string|object  $class
     * @param  class-string<TAttribute>  $attribute
     * @return list<TAttribute>
     */
    public static function all(string|object $class, string $attribute): array
    {
        $instances = [];

        for ($reflection = new ReflectionClass($class); $reflection !== false; $reflection = $reflection->getParentClass()) {
            foreach ($reflection->getAttributes($attribute) as $declared) {
                $instances[] = $declared->newInstance();
            }
        }

        return $instances;
    }
}
