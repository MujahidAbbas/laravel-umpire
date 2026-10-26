<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

#[Authorize('manage-customers')]
#[Authorize('delete-records', 'customers')]
class DeleteCustomerTool extends Tool
{
    use AuthorizesWithGate;

    protected string $name = 'delete-customer';

    protected string $description = 'Delete a customer and their tickets.';

    public function handle(Request $request): Response
    {
        return Response::text('delete-customer done');
    }
}
