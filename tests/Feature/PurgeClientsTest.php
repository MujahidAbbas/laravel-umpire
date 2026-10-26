<?php

declare(strict_types=1);

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\Token;
use MujahidAbbas\Umpire\Tests\Fixtures\Models\SecondaryToken;

// mcp:purge-clients decides what gets deleted. Only a client Umpire recorded at registration,
// registered before the cutoff and never issued an access token may go; every other client,
// and every other client's auth codes and marker, must survive.

beforeEach(function (): void {
    $this->travelTo(now()->subDays(3));
    $this->unused = registerClient();
    $this->tokened = registerClient();
    $this->revoked = registerClient();
    $this->manual = (string) app(ClientRepository::class)
        ->createAuthorizationCodeGrantClient(fake()->company(), ['https://'.fake()->domainName().'/callback'], confidential: false)
        ->getKey();
    $this->travelBack();
    $this->recent = registerClient();

    issueAccessToken($this->tokened);
    issueAccessToken($this->revoked, revoked: true);
    issueAuthCode($this->unused);
    issueAuthCode($this->recent);
});

afterEach(function (): void {
    Passport::useTokenModel(Token::class);
    putenv('SHELL_INTERACTIVE');
});

function issueAccessToken(string $clientId, bool $revoked = false): void
{
    Passport::token()->forceFill([
        'id' => bin2hex(random_bytes(40)),
        'user_id' => agent()->getKey(),
        'client_id' => $clientId,
        'scopes' => [],
        'revoked' => $revoked,
        'expires_at' => now()->addHour(),
    ])->save();
}

function issueAuthCode(string $clientId): void
{
    Passport::authCode()->forceFill([
        'id' => bin2hex(random_bytes(40)),
        'user_id' => agent()->getKey(),
        'client_id' => $clientId,
        'scopes' => '[]',
        'revoked' => false,
        'expires_at' => now()->addMinutes(10),
    ])->save();
}

/**
 * @return list<string>
 */
function clientIds(): array
{
    return Passport::client()->newQuery()->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();
}

/**
 * @return array{string, string, string}
 */
function listedRow(string $clientId): array
{
    return [
        $clientId,
        Passport::client()->newQuery()->findOrFail($clientId)->name,
        DB::table('mcp_registered_clients')->where('client_id', $clientId)->value('registered_at'),
    ];
}

it('lists the clients it would delete and deletes nothing', function (array $options): void {
    $this->artisan('mcp:purge-clients', ['--unused' => true, ...$options])
        ->expectsTable(['Client ID', 'Name', 'Registered at'], [listedRow($this->unused)])
        ->expectsOutput('1 client would be deleted. Run with --force to delete them.')
        ->assertSuccessful();

    expect(clientIds())->toEqualCanonicalizing([$this->unused, $this->tokened, $this->revoked, $this->manual, $this->recent])
        ->and(DB::table('mcp_registered_clients')->count())->toBe(4);
})->with([
    'by default' => [[]],
    'with --dry-run' => [['--dry-run' => true]],
]);

it('deletes only recorded clients older than the cutoff that never got a token', function (): void {
    $this->artisan('mcp:purge-clients', ['--unused' => true, '--force' => true])
        ->expectsOutput('Deleted 1 client.')
        ->assertSuccessful();

    expect(clientIds())->toEqualCanonicalizing([$this->tokened, $this->revoked, $this->manual, $this->recent]);
});

it('deletes the client\'s auth codes and marker with it, and no one else\'s', function (): void {
    $this->artisan('mcp:purge-clients', ['--unused' => true, '--force' => true])->assertSuccessful();

    expect(Passport::authCode()->newQuery()->pluck('client_id')->all())->toBe([$this->recent])
        ->and(DB::table('mcp_registered_clients')->pluck('client_id')->all())
        ->toEqualCanonicalizing([$this->tokened, $this->revoked, $this->recent]);
});

it('compares the registration time with --older-than', function (string $olderThan, int $hoursLater, array $deleted): void {
    $this->travel($hoursLater)->hours();

    $this->artisan('mcp:purge-clients', ['--unused' => true, '--force' => true, '--older-than' => $olderThan])->assertSuccessful();

    expect(array_values(array_diff([$this->unused, $this->recent], clientIds())))->toBe(array_map(fn (string $client): string => $this->{$client}, $deleted));
})->with([
    'both younger than 4d' => ['4d', 0, []],
    'one older than 71h' => ['71h', 0, ['unused']],
    'both older than 1h, two hours on' => ['1h', 2, ['unused', 'recent']],
    'both older than 90m, two hours on' => ['90m', 2, ['unused', 'recent']],
    'only one older than 3h, two hours on' => ['3h', 2, ['unused']],
]);

it('says so when there is nothing to delete', function (): void {
    $this->artisan('mcp:purge-clients', ['--unused' => true, '--older-than' => '4d'])
        ->expectsOutputToContain('No clients to delete.')
        ->doesntExpectOutputToContain('would be deleted')
        ->assertSuccessful();
});

it('keeps a client that receives a token after it was listed', function (): void {
    $issued = false;

    DB::listen(function (QueryExecuted $query) use (&$issued): void {
        if (! $issued && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'mcp_registered_clients')) {
            $issued = true;
            issueAccessToken($this->unused);
        }
    });

    $this->artisan('mcp:purge-clients', ['--unused' => true, '--force' => true])
        ->expectsOutput('Deleted 0 clients.')
        ->expectsOutput('1 client received an access token after the listing and was kept.')
        ->assertSuccessful();

    expect($issued)->toBeTrue()
        ->and(clientIds())->toContain($this->unused)
        ->and(Passport::authCode()->newQuery()->where('client_id', $this->unused)->exists())->toBeTrue()
        ->and(DB::table('mcp_registered_clients')->where('client_id', $this->unused)->exists())->toBeTrue();
});

it('asks before deleting in production when someone can answer', function (string $answer, array $remaining): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    // A person at a terminal: Pest itself may or may not have a TTY.
    putenv('SHELL_INTERACTIVE=1');

    $command = $this->artisan('mcp:purge-clients', ['--unused' => true, '--force' => true])
        ->expectsConfirmation('Delete 1 client from production? This cannot be undone.', $answer);

    $answer === 'yes'
        ? $command->expectsOutput('Deleted 1 client.')->assertSuccessful()
        : $command->expectsOutput('Nothing was deleted.')->assertFailed();
    $command->run();

    expect(clientIds())->toEqualCanonicalizing(array_map(fn (string $client): string => $this->{$client}, $remaining));
})->with([
    'no' => ['no', ['unused', 'tokened', 'revoked', 'manual', 'recent']],
    'yes' => ['yes', ['tokened', 'revoked', 'manual', 'recent']],
]);

it('deletes without asking in production when the run is unattended', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    putenv('SHELL_INTERACTIVE=1');

    $this->artisan('mcp:purge-clients', ['--unused' => true, '--force' => true, '--no-interaction' => true])
        ->expectsOutput('Deleted 1 client.')
        ->assertSuccessful();

    expect(clientIds())->not->toContain($this->unused);
});

it('refuses to run with a missing or conflicting flag', function (array $options, string $error): void {
    $this->artisan('mcp:purge-clients', $options)
        ->expectsOutputToContain($error)
        ->assertFailed();

    expect(clientIds())->toHaveCount(5);
})->with([
    'without --unused' => [['--force' => true], 'Pass --unused.'],
    '--dry-run with --force' => [['--unused' => true, '--dry-run' => true, '--force' => true], '--dry-run and --force cannot be combined.'],
]);

it('refuses an --older-than it cannot read, echoing it', function (string $olderThan): void {
    // Artisan::output(), not expectsOutputToContain(): the console wraps a long error line.
    expect(Artisan::call('mcp:purge-clients', ['--unused' => true, '--force' => true, '--older-than' => $olderThan]))->toBe(1)
        ->and(Artisan::output())->toContain("--older-than takes digits followed by m, h or d, such as 90m, 24h or 7d (got \"{$olderThan}\").")
        ->and(clientIds())->toHaveCount(5);
})->with(['24', 'h', '1w', '24H', '1.5h', '-2h', ' 24h', '24h ', '1234567d', '']);

it('refuses an --older-than under an hour, echoing it', function (string $olderThan): void {
    expect(Artisan::call('mcp:purge-clients', ['--unused' => true, '--force' => true, '--older-than' => $olderThan]))->toBe(1)
        ->and(Artisan::output())->toContain("--older-than must be at least 1h (got \"{$olderThan}\").")
        ->and(clientIds())->toHaveCount(5);
})->with(['59m', '0h', '0d']);

it('refuses to run when its tables are on different connections', function (): void {
    config(['database.connections.secondary' => ['driver' => 'sqlite', 'database' => ':memory:']]);
    Passport::useTokenModel(SecondaryToken::class);

    // Artisan::output(), not expectsOutputToContain(): the console wraps a long error line.
    expect(Artisan::call('mcp:purge-clients', ['--unused' => true, '--force' => true]))->toBe(1)
        ->and(Artisan::output())->toContain('must share one database connection', 'oauth_access_tokens on [secondary]')
        ->and(clientIds())->toHaveCount(5);
});
