<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'daily_review_sessions',
            function (Blueprint $table): void {
                $table->dateTime(
                    'reminder_sent_at',
                )
                    ->nullable()
                    ->after('completed_at');
            },
        );
    }

    public function down(): void
    {
        Schema::table(
            'daily_review_sessions',
            function (Blueprint $table): void {
                $table->dropColumn(
                    'reminder_sent_at',
                );
            },
        );
    }
};
