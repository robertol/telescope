<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return config('telescope.storage.database.connection');
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        if (! $schema->hasTable('telescope_users')) {
            return;
        }

        if ($schema->hasColumn('telescope_users', 'must_change_password')) {
            return;
        }

        $schema->table('telescope_users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());

        if (! $schema->hasTable('telescope_users') || ! $schema->hasColumn('telescope_users', 'must_change_password')) {
            return;
        }

        $schema->table('telescope_users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
