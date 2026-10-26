<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Exceptions;

use LogicException;

class ToolCannotBeAudited extends LogicException
{
    public static function becauseHandleIsBound(string $class): self
    {
        return new self(
            "Umpire cannot audit [{$class}]: its handle() method already has a container method binding. "
            ."Remove the bindMethod() call for [{$class}@handle] so every call to this tool can be audited.",
        );
    }
}
