<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Events;

use Illuminate\Contracts\Auth\Authenticatable;

class ToolInvoked
{
    /**
     * @param  string  $id  The ULID of the InvokingTool event that started this call.
     * @param  array<string, mixed>  $arguments  The tool arguments, with the configured keys redacted.
     * @param  string|null  $clientId  The OAuth client id (Passport) or personal access token id (Sanctum).
     * @param  string|null  $tokenId  The access token id (Passport) or personal access token id (Sanctum).
     * @param  bool  $isError  Whether any response the tool returned is an error result.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $tool,
        public readonly array $arguments,
        public readonly ?Authenticatable $user,
        public readonly ?string $clientId,
        public readonly ?string $tokenId,
        public readonly float $durationMs,
        public readonly bool $isError,
    ) {}
}
