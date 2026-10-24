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
        Schema::create('event_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            // Event information
            $table->string('event_type')->index(); // e.g., 'message.sent', 'contact.created', 'campaign.started'
            $table->string('event_category')->index(); // e.g., 'message', 'contact', 'campaign', 'workflow'

            // Related entities
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('contact_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('message_id')->nullable()->constrained('unified_messages')->onDelete('set null');
            $table->foreignId('campaign_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('workflow_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('conversation_id')->nullable()->constrained()->onDelete('set null');

            // Event data
            $table->string('action'); // e.g., 'sent', 'delivered', 'opened', 'clicked', 'created', 'updated'
            $table->enum('status', ['success', 'failure', 'warning', 'info'])->default('info');

            // Event details
            $table->text('description')->nullable();
            $table->json('event_data')->nullable(); // Additional event-specific data
            $table->json('metadata')->nullable(); // System metadata

            // Context information
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('session_id')->nullable();

            // Error information (for failed events)
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();

            $table->timestamps();

            // Indexes for performance and analytics
            $table->index(['tenant_id', 'event_type']);
            $table->index(['tenant_id', 'event_category']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'contact_id']);
            $table->index(['tenant_id', 'campaign_id']);
            $table->index(['tenant_id', 'workflow_id']);
            $table->index(['created_at']); // For time-based queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_logs');
    }
};
