<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Prompts;

use Illuminate\Routing\Attributes\Controllers\Authorize;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

/**
 * Imports Laravel's controller attribute instead of Umpire's.
 */
#[Authorize('issue-refunds')]
class FrameworkAuthorizeRefundReplyPrompt extends Prompt
{
    use AuthorizesWithGate;

    protected string $name = 'framework-authorize-refund-reply';

    protected string $description = 'Draft a reply that confirms a refund.';

    public function handle(Request $request): Response
    {
        return Response::text('framework-authorize-refund-reply done');
    }
}
