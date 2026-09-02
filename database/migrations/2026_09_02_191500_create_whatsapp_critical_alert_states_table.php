<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_critical_alert_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('fingerprint', 120);
            $table->string('subject_type', 40);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('organization_id')->nullable()->index();
            $table->string('title_sha256', 64);
            $table->string('last_level', 30)->index();
            $table->string('last_state_hash', 64);
            $table->dateTime('last_attempt_at')->nullable();
            $table->dateTime('last_sent_at')->nullable();
            $table->string('last_message_id', 255)->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'fingerprint']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_critical_alert_states');
    }
};
