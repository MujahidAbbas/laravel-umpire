<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Tests\Fixtures\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\ToolSearch;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\LookupTicketTool;

/**
 * Adds the tool a test names in boot(), directly or inside a ToolSearch catalog. Tests reset the
 * static properties after each run.
 */
#[Name('Helpdesk misconfigured')]
class MisconfiguredServer extends Server
{
    /**
     * @var class-string<Tool>|null
     */
    public static ?string $tool = null;

    public static bool $inCatalog = false;

    protected array $tools = [
        LookupTicketTool::class,
    ];

    protected function boot(): void
    {
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
