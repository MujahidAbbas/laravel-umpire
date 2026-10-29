<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

/**
 * No use line for the attribute, so PHP resolves it to a class in this namespace that does not exist.
 */
#[Authorize('view-customers')] // @phpstan-ignore attribute.notFound
class UnimportedAuthorizeCustomerRecordResource extends Resource implements HasUriTemplate
{
    use AuthorizesWithGate;

    protected string $description = 'A customer\'s contact details and plan.';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('helpdesk://unimported-customers/{id}');
    }

    public function handle(Request $request): Response
    {
        return Response::text('unimported-customer-record '.$request->string('id')->value());
    }
}
