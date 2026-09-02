<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'summary_whatsapp_deliveries',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->string('period', 20);
                $table->date('summary_date');

                $table->string(
                    'recipient_sha256',
                    64,
                );

                $table->string(
                    'status',
                    30,
                )->index();

                $table->string(
                    'message_id',
                    255,
                )->nullable();

                $table->dateTime(
                    'sent_at',
                )->nullable();

                $table->string(
                    'error_code',
                    80,
                )->nullable();

                $table->timestamps();

                $table->unique([
                    'user_id',
                    'period',
                    'summary_date',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'summary_whatsapp_deliveries',
        );
    }
};
