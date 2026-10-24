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
        Schema::create('unified_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            // Message relationships
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->foreignId('conversation_id')->constrained()->onDelete('cascade');
            $table->foreignId('campaign_id')->nullable()->constrained()->onDelete('set null');

            // Message direction and type
            $table->enum('direction', ['inbound', 'outbound'])->default('outbound');
            $table->enum('channel', ['sms', 'email', 'web', 'api'])->default('sms');

            // Message content
            $table->text('content');
            $table->string('subject')->nullable(); // For emails
            $table->json('attachments')->nullable(); // File attachments

            // Delivery information
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed', 'bounced'])->default('pending');
            $table->string('message_id')->nullable(); // External message ID
            $table->string('external_id')->nullable(); // Provider's message ID

            // Delivery tracking
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Engagement tracking (for emails)
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->integer('click_count')->default(0);

            // Error information
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();

            // Metadata
            $table->json('metadata')->nullable(); // Additional message data
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            // Sender information (for outbound messages)
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();

            // Indexes for performance
            $table->index(['tenant_id', 'contact_id']);
            $table->index(['tenant_id', 'conversation_id']);
            $table->index(['tenant_id', 'campaign_id']);
            $table->index(['tenant_id', 'direction']);
            $table->index(['tenant_id', 'channel']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'sent_at']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unified_messages');
    }
};
