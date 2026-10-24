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
        Schema::create('segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            $table->string('name');
            $table->text('description')->nullable();

            // JSON rules for dynamic segmentation
            // Example: {"all": [{"field": "country", "operator": "=", "value": "NG"}, {"field": "last_reply_at", "operator": ">", "value": "-7d"}]}
            $table->json('rules');

            // Cached statistics
            $table->integer('contact_count')->default(0);
            $table->timestamp('last_calculated_at')->nullable();

            // Metadata
            $table->boolean('is_active')->default(true);
            $table->boolean('is_dynamic')->default(true); // Dynamic segments recalculate vs static lists

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'is_dynamic']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('segments');
    }
};
