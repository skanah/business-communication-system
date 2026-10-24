<?php

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
        // Guarded so this is a no-op on databases created from the updated
        // create-table migrations (which already include the column).
        Schema::table('segments', function (Blueprint $table) {
            if (!Schema::hasColumn('segments', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('campaigns', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('segments', function (Blueprint $table) {
            if (Schema::hasColumn('segments', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
