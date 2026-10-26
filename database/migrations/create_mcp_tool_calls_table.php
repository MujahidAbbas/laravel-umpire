<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('tool')->index();
            $table->json('arguments');
            // Strings, so integer and UUID keys both fit.
            $table->string('user_type')->nullable();
            $table->string('user_id')->nullable();
            $table->string('client_id')->nullable()->index();
            $table->string('token_id')->nullable();
            $table->string('tenant_id')->nullable()->index();
            $table->boolean('is_error');
            // The class name only: an exception message cannot be redacted.
            $table->string('exception')->nullable();
            $table->float('duration_ms');
            $table->timestamp('created_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->table());
    }

    protected function table(): string
    {
        $table = config('umpire.audit.table');

        if (! is_string($table) || $table === '') {
            throw new RuntimeException(
                'The umpire.audit.table config value is missing, so Umpire cannot tell which table to migrate. '
                .'Make sure MujahidAbbas\\Umpire\\UmpireServiceProvider is registered (package discovery skips it when '
                .'the app lists the package under dont-discover), then run "php artisan config:clear" and migrate again.',
            );
        }

        return $table;
    }
};
