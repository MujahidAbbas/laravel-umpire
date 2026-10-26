<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class CreateTicketTool extends Tool
{
    protected string $name = 'create-ticket';

    protected string $description = 'Open a support ticket.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'subject' => $schema->string()->required(),
        ];
    }

    public function handle(Request $request): Response
    {
        $data = $request->validate(['subject' => 'required|string']);

        return Response::text("Ticket opened: {$data['subject']}");
    }
}
