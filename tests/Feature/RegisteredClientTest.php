<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use Laravel\Passport\Passport;

// Stock Passport stores nothing that tells a dynamically registered client from one made with
// passport:client --public, so Umpire records each 201 from mcp.oauth.register. The purge
// only ever considers recorded clients.

it('records a client registered through dynamic client registration', function (): void {
    $this->freezeSecond();

    $id = registerClient();

    $marker = DB::table('mcp_registered_clients')->sole();

    expect($marker->client_id)->toBe($id)
        ->and($marker->registered_at)->toBe(now()->toDateTimeString())
        ->and(Passport::client()->newQuery()->whereKey($id)->exists())->toBeTrue();
});

it('does not record a registration that failed', function (): void {
    Exceptions::fake();

    $this->postJson(route('mcp.oauth.register'), ['client_name' => fake()->company()])->assertStatus(400);

    expect(DB::table('mcp_registered_clients')->count())->toBe(0);
    Exceptions::assertNothingReported();
});

it('does not record a 201 from any other route', function (): void {
    Route::post('helpdesk/integrations', fn () => response()->json(['client_id' => fake()->uuid()], 201));

    $this->postJson('helpdesk/integrations')->assertCreated();

    expect(DB::table('mcp_registered_clients')->count())->toBe(0);
});

it('records nothing while umpire.clients.record is off', function (): void {
    config(['umpire.clients.record' => false]);

    registerClient();

    expect(DB::table('mcp_registered_clients')->count())->toBe(0);
});

it('keeps the registration working and reports when the marker cannot be written', function (): void {
    Exceptions::fake();
    // The first query migrates and opens the test's transaction, before the table name changes.
    expect(DB::table('mcp_registered_clients')->count())->toBe(0);
    config(['umpire.clients.table' => 'mcp_registered_clients_missing']);

    $id = registerClient();

    expect(Passport::client()->newQuery()->whereKey($id)->exists())->toBeTrue();
    Exceptions::assertReportedCount(1);
    Exceptions::assertReported(QueryException::class);
});
