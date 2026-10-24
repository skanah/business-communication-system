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
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('sender_number');
            $table->string('receiver_number');
            $table->text('message_content');
            $table->string('message_type')->comment('incoming, outgoing');
            $table->string('message_status')->comment('sent, delivered, failed, read');
            $table->string('provider')->comment('smppe, twilio, etc.');
            $table->string('provider_message_id')->nullable();
            $table->integer('cost')->default(0);
            $table->string('currency')->default('USD');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
