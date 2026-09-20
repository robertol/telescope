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

        if (! $schema->hasTable('telescope_entries')) {
            return;
        }

        if ($schema->hasIndex('telescope_entries', 'telescope_entries_type_created_at_index')) {
            return;
        }

        $schema->table('telescope_entries', function (Blueprint $table) {
            $table->index(['type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());

        if (! $schema->hasTable('telescope_entries')) {
            return;
        }

        if (! $schema->hasIndex('telescope_entries', 'telescope_entries_type_created_at_index')) {
            return;
        }

        $schema->table('telescope_entries', function (Blueprint $table) {
            $table->dropIndex('telescope_entries_type_created_at_index');
        });
    }
};
