<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

/**
 * No use line for the attribute, so PHP resolves it to a class in this namespace that does not exist.
 */
#[Authorize('issue-refunds')] // @phpstan-ignore attribute.notFound
class UnimportedAuthorizeRefundReplyPrompt extends Prompt
{
    use AuthorizesWithGate;

    protected string $name = 'unimported-authorize-refund-reply';

    protected string $description = 'Draft a reply that confirms a refund.';

    public function handle(Request $request): Response
    {
        return Response::text('unimported-authorize-refund-reply done');
    }
}
