<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Attributes\Redact;

#[Redact('security_answer')]
class VerifyCustomerTool extends Tool
{
    protected string $name = 'verify-customer';

    protected string $description = 'Verify a customer before discussing their account.';

    public function handle(Request $request): Response
    {
        // Reports the raw length, which proves redaction never reaches the tool's own arguments.
        return Response::text('Checked an answer of '.mb_strlen($request->string('security_answer')->value()).' characters.');
    }
}
