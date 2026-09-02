<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'whatsapp_inbound_messages',
            function (Blueprint $table): void {
                $table->id();

                $table->string('message_id')->unique();

                $table->string(
                    'sender_wa_id',
                    40,
                )->nullable()->index();

                $table->string(
                    'phone_number_id',
                    40,
                )->nullable();

                $table->string(
                    'message_type',
                    30,
                )->nullable();

                $table->string(
                    'status',
                    30,
                )->index();

                $table->foreignId('task_id')
                    ->nullable()
                    ->constrained('tasks')
                    ->nullOnDelete();

                $table->string(
                    'text_sha256',
                    64,
                )->nullable();

                $table->unsignedInteger(
                    'text_length',
                )->default(0);

                $table->dateTime(
                    'received_at',
                )->nullable();

                $table->dateTime(
                    'processed_at',
                )->nullable();

                $table->string(
                    'error_code',
                    80,
                )->nullable();

                $table->timestamps();

                $table->index([
                    'status',
                    'received_at',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'whatsapp_inbound_messages',
        );
    }
};
