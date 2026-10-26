<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Events;

class ToolCallStarting
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function __construct(
        public readonly string $tool,
        public readonly array $arguments,
    ) {}
}
