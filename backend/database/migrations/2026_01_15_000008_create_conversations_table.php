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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            // Associated contact
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');

            // Conversation details
            $table->string('subject')->nullable();
            $table->enum('channel', ['sms', 'email', 'web', 'api'])->default('sms');

            // Status and priority
            $table->enum('status', ['open', 'pending', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');

            // Assignment
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->onDelete('set null');

            // Timestamps
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // Statistics
            $table->integer('message_count')->default(0);
            $table->integer('agent_message_count')->default(0);
            $table->integer('contact_message_count')->default(0);

            // Metadata
            $table->json('tags')->nullable();
            $table->json('metadata')->nullable(); // Additional conversation data

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'contact_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'channel']);
            $table->index(['tenant_id', 'priority']);
            $table->index(['tenant_id', 'assigned_user_id']);
            $table->index(['tenant_id', 'last_message_at']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
