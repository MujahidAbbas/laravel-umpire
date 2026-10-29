<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Console;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Deletes OAuth clients that laravel/mcp created through dynamic client registration, Umpire
 * recorded, and that have no access tokens left. Lists them unless --force is given.
 */
#[AsCommand(name: 'mcp:purge-clients')]
class PurgeClientsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'mcp:purge-clients
        {--unused : Select recorded clients with no access tokens left (required)}
        {--older-than=24h : Only clients registered longer ago than this: digits followed by m, h or d, at least 1h}
        {--dry-run : List the clients without deleting them (the default)}
        {--force : Delete the listed clients}';

    /**
     * @var string
     */
    protected $description = 'Delete OAuth clients registered through MCP dynamic client registration that have no access tokens left';

    public function handle(): int
    {
        if (! $this->option('unused')) {
            $this->fail('Pass --unused. It is the only purge rule: clients recorded at registration with no access tokens left.');
        }

        if ($this->option('dry-run') && $this->option('force')) {
            $this->fail('--dry-run and --force cannot be combined.');
        }

        $minutes = $this->minutes();

        if (! class_exists(Passport::class)) {
            $this->fail('mcp:purge-clients needs Laravel Passport, which is not installed.');
        }

        $connection = Passport::client()->getConnection();
        $connections = $this->connectionNames();

        if (count(array_unique($connections)) > 1) {
            $this->fail(sprintf(
                'Passport\'s clients, tokens and auth codes and the %s table must share one database connection, so each '
                .'client is deleted in one transaction. Found: %s.',
                $this->markers(),
                implode(', ', array_map(fn (string $table, string $name): string => "{$table} on [{$name}]", array_keys($connections), $connections)),
            ));
        }

        $candidates = $this->candidates($connection, Carbon::now()->subMinutes($minutes));
        $count = $candidates->count();

        if ($count === 0) {
            $this->components->info('No clients to delete.');

            return self::SUCCESS;
        }

        $this->table(
            ['Client ID', 'Name', 'Registered at'],
            $candidates->map(fn (object $client): array => [$client->client_id, $client->name, $client->registered_at])->all(),
        );

        if (! $this->option('force')) {
            $this->line(sprintf('%d %s would be deleted. Run with --force to delete them.', $count, Str::plural('client', $count)));

            return self::SUCCESS;
        }

        if ($this->laravel->environment('production') && $this->attended() && ! $this->confirm(sprintf('Delete %d %s from production? This cannot be undone.', $count, Str::plural('client', $count)))) {
            $this->line('Nothing was deleted.');

            return self::FAILURE;
        }

        $deleted = $candidates->filter(fn (object $client): bool => $this->delete($connection, $client->client_id))->count();
        $kept = $count - $deleted;

        $this->line(sprintf('Deleted %d %s.', $deleted, Str::plural('client', $deleted)));

        if ($kept > 0) {
            $this->line(sprintf('%d %s received an access token after the listing and %s kept.', $kept, Str::plural('client', $kept), $kept === 1 ? 'was' : 'were'));
        }

        return self::SUCCESS;
    }

    /**
     * Recorded clients registered before the cutoff that still exist and have no row in the access
     * token table. A revoked token keeps its client off the list until passport:purge removes it.
     *
     * @return Collection<int, object{client_id: string, name: string, registered_at: string}>
     */
    protected function candidates(ConnectionInterface $connection, Carbon $cutoff): Collection
    {
        $markers = $this->markers();
        $clients = Passport::client();
        $tokens = Passport::token();

        /** @var Collection<int, object{client_id: string, name: string, registered_at: string}> */
        return $connection->table($markers)
            ->join($clients->getTable(), $clients->getQualifiedKeyName(), '=', "{$markers}.client_id")
            ->where("{$markers}.registered_at", '<', $cutoff)
            ->whereNotExists(fn (Builder $query): Builder => $query->selectRaw('1')
                ->from($tokens->getTable())
                ->whereColumn($tokens->qualifyColumn('client_id'), "{$markers}.client_id"))
            ->orderBy("{$markers}.registered_at")
            ->orderBy("{$markers}.client_id")
            ->get(["{$markers}.client_id", $clients->qualifyColumn('name'), "{$markers}.registered_at"]);
    }

    /**
     * Delete one client, its auth codes and its marker in a single transaction. The client row's
     * DELETE re-checks for tokens itself, so a client that got one after the listing survives.
     */
    protected function delete(ConnectionInterface $connection, string $clientId): bool
    {
        $clients = Passport::client();
        $tokens = Passport::token();
        $authCodes = Passport::authCode();

        return $connection->transaction(function () use ($connection, $clientId, $clients, $tokens, $authCodes): bool {
            $deleted = $connection->table($clients->getTable())
                ->where($clients->getKeyName(), $clientId)
                ->whereNotExists(fn (Builder $query): Builder => $query->selectRaw('1')
                    ->from($tokens->getTable())
                    ->where('client_id', $clientId))
                ->delete();

            if ($deleted === 0) {
                return false;
            }

            $connection->table($authCodes->getTable())->where('client_id', $clientId)->delete();
            $connection->table($this->markers())->where('client_id', $clientId)->delete();

            return true;
        });
    }

    /**
     * The connection each table the purge touches is on, keyed by table.
     *
     * @return array<string, string>
     */
    protected function connectionNames(): array
    {
        $passport = Config::get('passport.connection');
        $names = [];

        foreach ([Passport::client(), Passport::token(), Passport::authCode()] as $model) {
            $names[$model->getTable()] = $model->getConnection()->getName();
        }

        $names[$this->markers()] = DB::connection(is_string($passport) ? $passport : null)->getName();

        return array_map(fn (?string $name): string => (string) $name, $names);
    }

    /**
     * The --older-than window in minutes. Fails the command when it is malformed or under an hour,
     * echoing what was typed.
     */
    protected function minutes(): int
    {
        $value = $this->option('older-than');
        $value = is_string($value) ? $value : '';

        if (preg_match('/^(\d{1,6})([mhd])$/', $value, $matches) !== 1) {
            $this->fail("--older-than takes digits followed by m, h or d, such as 90m, 24h or 7d (got \"{$value}\").");
        }

        $minutes = (int) $matches[1] * ['m' => 1, 'h' => 60, 'd' => 1440][$matches[2]];

        if ($minutes < 60) {
            $this->fail("--older-than must be at least 1h (got \"{$value}\").");
        }

        return $minutes;
    }

    /**
     * Whether someone is there to answer. -n means no one. Otherwise STDIN must be a TTY, which a
     * scheduled run lacks, unless SHELL_INTERACTIVE is set (Symfony's long-standing override).
     */
    protected function attended(): bool
    {
        return $this->input->isInteractive()
            && ((defined('STDIN') && stream_isatty(STDIN)) || getenv('SHELL_INTERACTIVE') !== false);
    }

    protected function markers(): string
    {
        return Config::string('umpire.clients.table', 'mcp_registered_clients');
    }
}
