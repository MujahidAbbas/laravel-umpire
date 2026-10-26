<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

class ToolFailed
{
    /**
     * @param  string  $id  The ULID of the InvokingTool event that started this call.
     * @param  array<string, mixed>  $arguments  The tool arguments, with the configured keys redacted.
     * @param  string|null  $clientId  The OAuth client id (Passport) or personal access token id (Sanctum).
     * @param  string|null  $tokenId  The access token id (Passport) or personal access token id (Sanctum).
     * @param  Throwable  $exception  What handle() threw, or ToolStreamAbandoned when the stream was dropped.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $tool,
        public readonly array $arguments,
        public readonly ?Authenticatable $user,
        public readonly ?string $clientId,
        public readonly ?string $tokenId,
        public readonly float $durationMs,
        public readonly Throwable $exception,
    ) {}
}
