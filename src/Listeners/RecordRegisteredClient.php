<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Listeners;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Carbon;
use Throwable;
use UnexpectedValueException;

/**
 * Records each client laravel/mcp creates through dynamic client registration, so the purge can
 * tell them from clients made any other way: stock Passport stores nothing that marks them.
 *
 * @internal
 */
class RecordRegisteredClient
{
    public function __construct(
        protected Repository $config,
        protected ConnectionResolverInterface $db,
        protected ExceptionHandler $exceptions,
    ) {}

    public function handle(RequestHandled $event): void
    {
        if (! $event->request->routeIs('mcp.oauth.register') || $event->response->getStatusCode() !== 201) {
            return;
        }

        if (! $this->config->get('umpire.clients.record')) {
            return;
        }

        // A failed write is reported, never thrown: it must not break the registration.
        try {
            $body = json_decode((string) $event->response->getContent(), true, flags: JSON_THROW_ON_ERROR);
            $clientId = is_array($body) ? ($body['client_id'] ?? null) : null;

            if (! is_string($clientId) || $clientId === '') {
                throw new UnexpectedValueException('Umpire could not read the client_id of a client registered through mcp.oauth.register.');
            }

            $connection = $this->config->get('passport.connection');
            $table = $this->config->get('umpire.clients.table');

            $db = $this->db->connection(is_string($connection) ? $connection : null);
            $insert = fn (): int => $db->table(is_string($table) ? $table : 'mcp_registered_clients')
                ->insertOrIgnore(['client_id' => $clientId, 'registered_at' => Carbon::now()]);

            // Inside a transaction, write through a savepoint: on PostgreSQL a failed insert
            // would otherwise abort the caller's transaction.
            $db->transactionLevel() > 0 ? $db->transaction($insert) : $insert();
        } catch (Throwable $exception) {
            $this->exceptions->report($exception);
        }
    }
}
