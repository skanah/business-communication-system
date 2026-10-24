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
        Schema::create('communication_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('sip_username')->nullable();
            $table->string('sip_password')->nullable();
            $table->string('sip_domain')->nullable();
            $table->string('webrtc_id')->nullable();
            $table->string('email_address')->nullable();
            $table->string('sms_number')->nullable();
            $table->boolean('call_forwarding_enabled')->default(false);
            $table->string('call_forwarding_number')->nullable();
            $table->boolean('voicemail_enabled')->default(false);
            $table->string('voicemail_pin')->nullable();
            $table->boolean('sms_notifications')->default(true);
            $table->boolean('email_notifications')->default(true);
            $table->boolean('webrtc_enabled')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_profiles');
    }
};
