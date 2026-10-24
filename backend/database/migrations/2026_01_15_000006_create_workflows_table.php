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
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            $table->string('name');
            $table->text('description')->nullable();

            // JSON graph structure for workflow nodes and edges
            // Example: {"nodes": [...], "edges": [...]}
            $table->json('graph');

            // Supported node types: trigger, condition, delay, sendSMS, sendEmail, waitForReply, updateContact, webhook, end
            $table->json('node_types'); // Array of supported node types

            // Workflow settings
            $table->boolean('is_active')->default(true);
            $table->boolean('is_draft')->default(true);
            $table->boolean('is_dynamic')->default(false);

            // Execution statistics
            $table->integer('total_executions')->default(0);
            $table->integer('active_executions')->default(0);
            $table->timestamp('last_executed_at')->nullable();

            // Metadata
            $table->json('tags')->nullable();
            $table->json('settings')->nullable(); // Additional workflow settings

            $table->softDeletes();

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'is_draft']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
