<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Passport's connection, so one transaction covers a client, its auth codes and its marker.
     */
    public function getConnection(): ?string
    {
        $connection = config('passport.connection');

        return is_string($connection) ? $connection : null;
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create($this->table(), function (Blueprint $table): void {
            // A uuid, like Passport's client ids, so the purge's joins compare like types everywhere.
            $table->uuid('client_id')->primary();
            $table->timestamp('registered_at')->index();
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
        $table = config('umpire.clients.table');

        if (! is_string($table) || $table === '') {
            throw new RuntimeException(
                'The umpire.clients.table config value is missing, so Umpire cannot tell which table to migrate. '
                .'Make sure MujahidAbbas\\Umpire\\UmpireServiceProvider is registered (package discovery skips it when '
                .'the app lists the package under dont-discover), then run "php artisan config:clear" and migrate again.',
            );
        }

        return $table;
    }
};
