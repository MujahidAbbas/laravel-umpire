<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Attributes;

use Attribute;

/**
 * A Gate ability the authenticated user must have to see or use this tool, resource or prompt.
 * Repeat it to require several abilities; every one must pass. The class must use the
 * AuthorizesWithGate trait.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class Authorize
{
    /**
     * Passed to the Gate after the user, as with the `can` middleware: a class name selects a policy.
     *
     * @var list<mixed>
     */
    public readonly array $arguments;

    public function __construct(public readonly string $ability, mixed ...$arguments)
    {
        $this->arguments = array_values($arguments);
    }
}
