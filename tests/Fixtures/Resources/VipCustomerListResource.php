<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Resource;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

#[Authorize('view-vip-customers')]
class VipCustomerListResource extends Resource
{
    use AuthorizesWithGate;

    protected string $uri = 'helpdesk://customers/vip';

    protected string $description = 'Customers on the VIP plan (its URI also matches the customer record template).';

    public function handle(Request $request): Response
    {
        return Response::text('vip-customer-list contents');
    }
}
