<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompt;

class SummarizeTicketPrompt extends Prompt
{
    protected string $name = 'summarize-ticket';

    protected string $description = 'Summarize a ticket for a hand-over.';

    public function handle(Request $request): Response
    {
        return Response::text('summarize-ticket done');
    }
}
