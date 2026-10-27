<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use MujahidAbbas\Umpire\Attributes\Authorize;

#[Authorize('issue-refunds')]
class UnguardedRefundReplyPrompt extends Prompt
{
    protected string $name = 'unguarded-refund-reply';

    protected string $description = 'Draft a refund reply (forgot the AuthorizesWithGate trait).';

    public function handle(Request $request): Response
    {
        return Response::text('unguarded-refund-reply done');
    }
}
