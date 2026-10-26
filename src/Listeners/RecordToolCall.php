<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Listeners;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Model;
use MujahidAbbas\Umpire\Events\ToolFailed;
use MujahidAbbas\Umpire\Events\ToolInvoked;
use MujahidAbbas\Umpire\Models\ToolCall;
use MujahidAbbas\Umpire\Umpire;
use Throwable;

/**
 * Writes one audit row when a tool call settles. Registered always; umpire.audit.enabled is read
 * on every call, so the table can be switched on and off at runtime.
 *
 * @internal
 */
class RecordToolCall
{
    public function __construct(
        protected Repository $config,
        protected ExceptionHandler $exceptions,
    ) {}

    public function handle(ToolInvoked|ToolFailed $event): void
    {
        if (! $this->config->get('umpire.audit.enabled')) {
            return;
        }

        // A failed audit write is reported, never thrown: it must not change the tool's result.
        try {
            $user = $event->user;

            $call = (new ToolCall)->forceFill([
                'id' => $event->id,
                'tool' => $event->tool,
                'arguments' => $event->arguments,
                'user_type' => match (true) {
                    $user instanceof Model => $user->getMorphClass(),
                    $user !== null => $user::class,
                    default => null,
                },
                'user_id' => $this->identifier($user?->getAuthIdentifier()),
                'client_id' => $event->clientId,
                'token_id' => $event->tokenId,
                'tenant_id' => Umpire::tenantFor($event),
                'is_error' => $event instanceof ToolFailed || $event->isError,
                'exception' => $event instanceof ToolFailed ? $event->exception::class : null,
                'duration_ms' => $event->durationMs,
            ]);

            // Inside a transaction, write through a savepoint: on PostgreSQL a failed insert
            // would otherwise abort the caller's transaction.
            $connection = $call->getConnection();
            $connection->transactionLevel() > 0 ? $connection->transaction(fn (): bool => $call->save()) : $call->save();
        } catch (Throwable $exception) {
            $this->exceptions->report($exception);
        }
    }

    protected function identifier(mixed $value): ?string
    {
        return is_string($value) || is_int($value) ? (string) $value : null;
    }
}
