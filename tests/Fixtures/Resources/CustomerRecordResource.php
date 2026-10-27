<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

#[Authorize('view-customers')]
class CustomerRecordResource extends Resource implements HasUriTemplate
{
    use AuthorizesWithGate;

    protected string $description = 'A customer\'s contact details and plan.';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('helpdesk://customers/{id}');
    }

    public function handle(Request $request): Response
    {
        return Response::text('customer-record '.$request->string('id')->value());
    }
}
