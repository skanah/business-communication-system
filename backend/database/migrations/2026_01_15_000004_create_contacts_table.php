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
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');

            // Basic contact information
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company')->nullable();

            // Consent flags
            $table->boolean('sms_consent')->default(false);
            $table->boolean('email_consent')->default(false);
            $table->timestamp('sms_consent_given_at')->nullable();
            $table->timestamp('email_consent_given_at')->nullable();

            // JSON attributes for flexible contact data
            $table->json('attributes')->nullable();

            // Tags for segmentation
            $table->json('tags')->nullable();

            // Metadata
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('last_reply_at')->nullable();
            $table->string('timezone')->nullable();
            $table->string('country_code')->nullable();

            // Status
            $table->enum('status', ['active', 'inactive', 'bounced', 'unsubscribed'])->default('active');

            $table->softDeletes();

            $table->timestamps();

            // Indexes for performance
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'sms_consent']);
            $table->index(['tenant_id', 'email_consent']);
            $table->index(['tenant_id', 'last_contacted_at']);
            $table->index(['tenant_id', 'tags']); // For JSON queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
