<?php

declare(strict_types=1);

namespace MujahidAbbas\Umpire\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

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
     * Rows older than umpire.audit.retention_days, or none when it is null.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        if (Config::get('umpire.audit.retention_days') === null) {
            return static::query()->whereRaw('1 = 0');
        }

        $cutoff = Carbon::now()->subDays(Config::integer('umpire.audit.retention_days'));

        return static::query()->where('created_at', '<', $cutoff);
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
