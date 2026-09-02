<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('summary_email_deliveries')) {
            return;
        }

        Schema::create(
            'summary_email_deliveries',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('user_id')
                    ->constrained()
                    ->cascadeOnDelete();

                $table->string('period', 20);
                $table->date('summary_date');
                $table->string('recipient');
                $table->string('status', 20)
                    ->default('pending');
                $table->timestamp('sent_at')
                    ->nullable();
                $table->text('error_message')
                    ->nullable();
                $table->timestamps();

                $table->unique(
                    [
                        'user_id',
                        'period',
                        'summary_date',
                    ],
                    'summary_email_delivery_unique',
                );

                $table->index(
                    [
                        'status',
                        'summary_date',
                    ],
                    'summary_email_delivery_status_idx',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'summary_email_deliveries',
        );
    }
};
