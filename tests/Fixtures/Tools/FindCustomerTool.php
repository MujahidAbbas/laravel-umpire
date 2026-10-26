<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class FindCustomerTool extends Tool
{
    protected string $name = 'find-customer';

    protected string $description = 'Find a customer by email.';

    public function handle(Request $request): Response
    {
        return Response::text('find-customer done');
    }
}
