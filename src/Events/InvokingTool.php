<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Events;

use Illuminate\Contracts\Auth\Authenticatable;

class InvokingTool
{
    /**
     * @param  string  $id  A ULID shared with the ToolInvoked or ToolFailed event that settles this call.
     * @param  array<string, mixed>  $arguments  The tool arguments, with the configured keys redacted.
     * @param  string|null  $clientId  The OAuth client id (Passport) or personal access token id (Sanctum).
     * @param  string|null  $tokenId  The access token id (Passport) or personal access token id (Sanctum).
     */
    public function __construct(
        public readonly string $id,
        public readonly string $tool,
        public readonly array $arguments,
        public readonly ?Authenticatable $user,
        public readonly ?string $clientId,
        public readonly ?string $tokenId,
    ) {}
}
