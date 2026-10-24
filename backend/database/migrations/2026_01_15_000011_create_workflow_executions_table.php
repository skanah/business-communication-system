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
        Schema::create('workflow_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('workflow_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');

            // Execution status
            $table->enum('status', ['running', 'waiting', 'completed', 'failed', 'paused'])->default('running');
            $table->string('current_node_id')->nullable(); // Current node in workflow

            // Execution data
            $table->json('context')->nullable(); // Execution context/data
            $table->json('node_states')->nullable(); // State of each executed node

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('next_execution_at')->nullable(); // For delayed executions

            // Error information
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'workflow_id']);
            $table->index(['tenant_id', 'contact_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'next_execution_at']);
            $table->index(['status', 'next_execution_at']); // For scheduled executions
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_executions');
    }
};
