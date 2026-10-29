<?php

declare(strict_types=1);

use Illuminate\Routing\Attributes\Controllers\Authorize as ControllerAuthorize;
use Illuminate\Support\Facades\Exceptions;
use MujahidAbbas\Umpire\Attributes\Authorize;
use MujahidAbbas\Umpire\Exceptions\AuthorizationNotEnforced;
use MujahidAbbas\Umpire\Tests\Fixtures\Concerns\RequiresRefundAbility;
use MujahidAbbas\Umpire\Tests\Fixtures\Prompts\FrameworkAuthorizeRefundReplyPrompt;
use MujahidAbbas\Umpire\Tests\Fixtures\Prompts\UnimportedAuthorizeRefundReplyPrompt;
use MujahidAbbas\Umpire\Tests\Fixtures\Resources\FrameworkAuthorizeCustomerRecordResource;
use MujahidAbbas\Umpire\Tests\Fixtures\Resources\FrameworkAuthorizeRefundPolicyResource;
use MujahidAbbas\Umpire\Tests\Fixtures\Resources\UnimportedAuthorizeCustomerRecordResource;
use MujahidAbbas\Umpire\Tests\Fixtures\Resources\UnimportedAuthorizeRefundPolicyResource;
use MujahidAbbas\Umpire\Tests\Fixtures\Servers\MisconfiguredServer;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\FrameworkAuthorizeRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\FrameworkAuthorizeSubclassRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\FrameworkMethodAuthorizeRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\GlobalAuthorizeRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\LookupTicketTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\MethodAuthorizeRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\MixedAuthorizeRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\NestedTraitAuthorizeRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\OddCaseAuthorizeRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\TraitAuthorizeRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\UnimportedAuthorizeRefundTool;
use MujahidAbbas\Umpire\Tests\Fixtures\Tools\UnimportedAuthorizeSubclassRefundTool;

// An #[Authorize] that is not Umpire's (Laravel's controller attribute, or a name PHP resolves to a
// class that does not exist because the use line is missing) is never read by AuthorizesWithGate.
// laravel/mcp instantiates a class's own attributes only to list tools and resources, so an
// unresolved one breaks tools/list but not tools/call, and a resolvable one breaks nothing.

afterEach(function (): void {
    MisconfiguredServer::$tool = null;
    MisconfiguredServer::$inCatalog = false;
    MisconfiguredServer::$resource = null;
    MisconfiguredServer::$prompt = null;
});

it('refuses every request on a server whose class carries an #[Authorize] that is not Umpire\'s', function (string $slot, string $class, string $found): void {
    MisconfiguredServer::${$slot} = $class;

    foreach ([
        fn () => MisconfiguredServer::tools(),
        fn () => MisconfiguredServer::resources(),
        fn () => MisconfiguredServer::prompts(),
        fn () => MisconfiguredServer::tool(LookupTicketTool::class),
    ] as $request) {
        expect($request)->toThrow(AuthorizationNotEnforced::class, $class)
            ->and($request)->toThrow(AuthorizationNotEnforced::class, "#[\\{$found}]");
    }
})->with([
    'Laravel\'s attribute on a tool' => ['tool', FrameworkAuthorizeRefundTool::class, ControllerAuthorize::class],
    'an unimported attribute on a tool' => ['tool', UnimportedAuthorizeRefundTool::class, 'MujahidAbbas\Umpire\Tests\Fixtures\Tools\Authorize'],
    '#[\Authorize] on a tool' => ['tool', GlobalAuthorizeRefundTool::class, 'Authorize'],
    'Laravel\'s attribute next to Umpire\'s' => ['tool', MixedAuthorizeRefundTool::class, ControllerAuthorize::class],
    'Laravel\'s attribute on a parent tool' => ['tool', FrameworkAuthorizeSubclassRefundTool::class, ControllerAuthorize::class],
    'an unimported attribute on a parent tool' => ['tool', UnimportedAuthorizeSubclassRefundTool::class, 'MujahidAbbas\Umpire\Tests\Fixtures\Tools\Authorize'],
    'Laravel\'s attribute on a resource' => ['resource', FrameworkAuthorizeRefundPolicyResource::class, ControllerAuthorize::class],
    'an unimported attribute on a resource' => ['resource', UnimportedAuthorizeRefundPolicyResource::class, 'MujahidAbbas\Umpire\Tests\Fixtures\Resources\Authorize'],
    'Laravel\'s attribute on a resource template' => ['resource', FrameworkAuthorizeCustomerRecordResource::class, ControllerAuthorize::class],
    'an unimported attribute on a resource template' => ['resource', UnimportedAuthorizeCustomerRecordResource::class, 'MujahidAbbas\Umpire\Tests\Fixtures\Resources\Authorize'],
    'Laravel\'s attribute on a prompt' => ['prompt', FrameworkAuthorizeRefundReplyPrompt::class, ControllerAuthorize::class],
    'an unimported attribute on a prompt' => ['prompt', UnimportedAuthorizeRefundReplyPrompt::class, 'MujahidAbbas\Umpire\Tests\Fixtures\Prompts\Authorize'],
]);

it('refuses a server whose ToolSearch catalog holds a tool with an #[Authorize] that is not Umpire\'s', function (string $tool): void {
    MisconfiguredServer::$tool = $tool;
    MisconfiguredServer::$inCatalog = true;

    MisconfiguredServer::tool(LookupTicketTool::class);
})->with([
    FrameworkAuthorizeRefundTool::class,
    UnimportedAuthorizeRefundTool::class,
])->throws(AuthorizationNotEnforced::class, 'use MujahidAbbas\Umpire\Attributes\Authorize;');

it('reports Umpire\'s reason, not PHP\'s missing attribute class, for an unimported #[Authorize]', function (string $method): void {
    config(['app.debug' => false]);
    Exceptions::fake();
    MisconfiguredServer::$tool = UnimportedAuthorizeRefundTool::class;

    $message = $method === 'tools/list' ? listToolsMessage() : toolCallMessage('unimported-authorize-refund');
    $error = postMcp('mcp/misconfigured', $message, status: 500)[0]['error'];

    expect($error['code'])->toBe(-32603);
    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(fn (AuthorizationNotEnforced $exception): bool => str_contains($exception->getMessage(), UnimportedAuthorizeRefundTool::class));
})->with(['tools/list', 'tools/call']);

it('enforces Umpire\'s attribute written in another case', function (): void {
    // Composer's autoloader matches case, so the class must already be loaded, as it is once any
    // correctly written #[Authorize] has been read.
    class_exists(Authorize::class);
    MisconfiguredServer::$tool = OddCaseAuthorizeRefundTool::class;

    MisconfiguredServer::tools()->assertNotRegistered(OddCaseAuthorizeRefundTool::class);
});

it('refuses #[Authorize] declared where Umpire does not read it', function (string $class, string $reason): void {
    MisconfiguredServer::$tool = $class;

    expect(fn () => MisconfiguredServer::tools())->toThrow(AuthorizationNotEnforced::class, $reason)
        ->and(fn () => MisconfiguredServer::tool(LookupTicketTool::class))->toThrow(AuthorizationNotEnforced::class, $reason);
})->with([
    'Umpire\'s on handle()' => [MethodAuthorizeRefundTool::class, 'declared on '.MethodAuthorizeRefundTool::class.'::handle()'],
    'Laravel\'s on handle()' => [FrameworkMethodAuthorizeRefundTool::class, 'declared on '.FrameworkMethodAuthorizeRefundTool::class.'::handle()'],
    'on a trait the class uses' => [TraitAuthorizeRefundTool::class, 'declared on the trait ['.RequiresRefundAbility::class.']'],
    'on a trait a used trait uses' => [NestedTraitAuthorizeRefundTool::class, 'declared on the trait ['.RequiresRefundAbility::class.']'],
]);

it('says where a foreign #[Authorize] was declared and when its class does not exist', function (string $tool, string $reason): void {
    MisconfiguredServer::$tool = $tool;

    expect(fn () => MisconfiguredServer::tools())->toThrow(AuthorizationNotEnforced::class, $reason);
})->with([
    'on the class, resolvable' => [FrameworkAuthorizeRefundTool::class, 'the class carries #[\\'.ControllerAuthorize::class.'], which is not Umpire\'s attribute and protects nothing. Import'],
    'on a parent, unresolved' => [UnimportedAuthorizeSubclassRefundTool::class, 'its parent ['.UnimportedAuthorizeRefundTool::class.'] carries #[\\MujahidAbbas\\Umpire\\Tests\\Fixtures\\Tools\\Authorize], which is not Umpire\'s attribute and protects nothing. That class does not exist'],
]);
