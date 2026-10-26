<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Attributes;

use Attribute;

/**
 * Argument keys to redact from this tool's audit events, on top of the global umpire.redact list.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Redact
{
    /**
     * @var list<string>
     */
    public readonly array $keys;

    public function __construct(string ...$keys)
    {
        $this->keys = array_values($keys);
    }
}
