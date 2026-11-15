<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/**
 * One audited tool call. model:prune only discovers app/Models, so prune it with
 * `php artisan model:prune --model="MujahidAbbas\Umpire\Models\ToolCall"`.
 *
 * @property string $id
 * @property string $tool
 * @property array<string, mixed> $arguments
 * @property string|null $user_type
 * @property string|null $user_id
 * @property string|null $client_id
 * @property string|null $token_id
 * @property string|null $tenant_id
 * @property bool $is_error
 * @property string|null $exception
 * @property float $duration_ms
 * @property Carbon $created_at
 */
class ToolCall extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    public function getTable(): string
    {
        return Config::string('umpire.audit.table', 'mcp_tool_calls');
    }

    /**
     * Rows older than umpire.audit.retention_days, or none when it is null. A string of digits
     * counts as the integer it spells, since env() returns strings; any other value throws, and
     * so does a negative integer, whose cutoff would lie in the future and match every row.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        $days = Config::get('umpire.audit.retention_days');

        if ($days === null) {
            return static::query()->whereRaw('1 = 0');
        }

        $days = is_string($days) && ctype_digit($days) ? (int) $days : Config::integer('umpire.audit.retention_days');

        if ($days < 0) {
            throw new InvalidArgumentException("Configuration value for key [umpire.audit.retention_days] must not be negative, {$days} given.");
        }

        return static::query()->where('created_at', '<', Carbon::now()->subDays($days));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'is_error' => 'boolean',
            'duration_ms' => 'float',
        ];
    }
}
