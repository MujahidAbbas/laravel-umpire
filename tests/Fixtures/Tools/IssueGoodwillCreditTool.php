<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Declares no attribute of its own: it must still require the parent's issue-refunds ability.
 */
class IssueGoodwillCreditTool extends IssueRefundTool
{
    protected string $name = 'issue-goodwill-credit';

    protected string $description = 'Credit a customer\'s account as a goodwill gesture.';

    public function handle(Request $request): Response
    {
        return Response::text('issue-goodwill-credit done');
    }
}
