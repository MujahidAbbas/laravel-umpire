<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

#[Authorize('issue-refunds')]
class OverridingRefundReplyPrompt extends Prompt
{
    use AuthorizesWithGate;

    protected string $name = 'overriding-refund-reply';

    protected string $description = 'Draft a refund reply (its own shouldRegister() replaces the trait\'s).';

    public function shouldRegister(Request $request): bool
    {
        return true;
    }

    public function handle(Request $request): Response
    {
        return Response::text('overriding-refund-reply done');
    }
}
