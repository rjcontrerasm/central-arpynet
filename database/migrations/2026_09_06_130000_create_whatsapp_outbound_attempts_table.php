<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_outbound_attempts', function (Blueprint $table): void {
            $table->id();
            $table->string('purpose', 40)->index();
            $table->string('request_kind', 20);
            $table->string('recipient_sha256', 64)->index();
            $table->string('status', 30)->index();
            $table->string('message_id', 255)->nullable();
            $table->string('error_code', 80)->nullable();
            $table->string('error_subcode', 80)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_type', 120)->nullable();
            $table->string('error_message', 500)->nullable();
            $table->string('fbtrace_id', 120)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['purpose', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_outbound_attempts');
    }
};
