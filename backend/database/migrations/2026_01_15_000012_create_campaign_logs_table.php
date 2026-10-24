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
        Schema::create('campaign_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('campaign_id')->constrained()->onDelete('cascade');
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->foreignId('message_id')->nullable()->constrained('unified_messages')->onDelete('set null');

            // Log details
            $table->string('action'); // 'sent', 'delivered', 'opened', 'clicked', 'failed', etc.
            $table->enum('status', ['success', 'failure', 'warning'])->default('success');

            // Additional data
            $table->json('event_data')->nullable();
            $table->text('error_message')->nullable();

            // Context
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'campaign_id']);
            $table->index(['tenant_id', 'contact_id']);
            $table->index(['tenant_id', 'action']);
            $table->index(['tenant_id', 'status']);
            $table->index(['campaign_id', 'action']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_logs');
    }
};
