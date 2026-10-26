<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Exceptions;

use RuntimeException;

/**
 * Carried by ToolFailed when a streamed tool call is dropped before it finishes, usually because
 * the client disconnected. Never thrown or reported.
 */
class ToolStreamAbandoned extends RuntimeException
{
    public static function make(): self
    {
        return new self('The tool stream was abandoned before it finished.');
    }
}
