<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

#[Authorize('issue-refunds')]
class DraftRefundReplyPrompt extends Prompt
{
    use AuthorizesWithGate;

    protected string $name = 'draft-refund-reply';

    protected string $description = 'Draft a reply that confirms a refund.';

    public function handle(Request $request): Response
    {
        return Response::text('draft-refund-reply done');
    }
}
