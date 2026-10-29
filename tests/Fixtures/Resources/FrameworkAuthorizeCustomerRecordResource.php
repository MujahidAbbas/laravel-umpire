<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Resources;

use Illuminate\Routing\Attributes\Controllers\Authorize;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;
use MujahidAbbas\Umpire\Concerns\AuthorizesWithGate;

/**
 * Imports Laravel's controller attribute instead of Umpire's.
 */
#[Authorize('view-customers')]
class FrameworkAuthorizeCustomerRecordResource extends Resource implements HasUriTemplate
{
    use AuthorizesWithGate;

    protected string $description = 'A customer\'s contact details and plan.';

    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('helpdesk://framework-customers/{id}');
    }

    public function handle(Request $request): Response
    {
        return Response::text('framework-customer-record '.$request->string('id')->value());
    }
}
