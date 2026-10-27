<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;
use MujahidAbbas\Umpire\Attributes\Authorize;

#[Authorize('view-customers')]
class UnguardedCustomerRecordResource extends Resource implements HasUriTemplate
{
    protected string $description = 'A customer record (forgot the AuthorizesWithGate trait).';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('helpdesk://unguarded-customers/{id}');
    }

    public function handle(Request $request): Response
    {
        return Response::text('unguarded-customer-record contents');
    }
}
