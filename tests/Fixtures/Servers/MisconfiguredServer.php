<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Resource as McpResource;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolSearch;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\LookupTicketTool;

/**
 * Adds the tool, resource or prompt a test names in boot(); the tool directly or inside a
 * ToolSearch catalog. Tests reset the static properties after each run.
 */
#[Name('Helpdesk misconfigured')]
class MisconfiguredServer extends Server
{
    /**
     * @var class-string<Tool>|null
     */
    public static ?string $tool = null;

    public static bool $inCatalog = false;

    /**
     * Aliased: Pint reads a bare Resource in a docblock as PHP's resource type.
     *
     * @var McpResource|class-string<McpResource>|null
     */
    public static McpResource|string|null $resource = null;

    /**
     * @var class-string<Prompt>|null
     */
    public static ?string $prompt = null;

    protected array $tools = [
        LookupTicketTool::class,
    ];

    protected function boot(): void
    {
        if (static::$resource !== null) {
            $this->resources[] = static::$resource;
        }

        if (static::$prompt !== null) {
            $this->prompts[] = static::$prompt;
        }

        if (static::$tool === null) {
            return;
        }

        if (static::$inCatalog) {
            $this->tools[ToolSearch::class] = [static::$tool];
        } else {
            $this->tools[] = static::$tool;
        }
    }
}
