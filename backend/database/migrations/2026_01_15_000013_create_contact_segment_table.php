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
        Schema::create('contact_segment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->onDelete('cascade');
            $table->foreignId('segment_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade'); // For performance

            // When the contact was added to this segment
            $table->timestamp('added_at')->useCurrent();

            // Who added the contact to this segment (optional)
            $table->foreignId('added_by_user_id')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();

            // Ensure unique contact-segment pairs
            $table->unique(['contact_id', 'segment_id']);

            // Indexes for performance
            $table->index(['tenant_id', 'contact_id']);
            $table->index(['tenant_id', 'segment_id']);
            $table->index(['tenant_id', 'added_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_segment');
    }
};
