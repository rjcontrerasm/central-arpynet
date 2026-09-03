<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'daily_review_sessions',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->date('review_date');

                $table->dateTime(
                    'decisions_reviewed_at',
                )->nullable();

                $table->dateTime(
                    'waiting_reviewed_at',
                )->nullable();

                $table->dateTime(
                    'tasks_reviewed_at',
                )->nullable();

                $table->dateTime(
                    'operations_reviewed_at',
                )->nullable();

                $table->dateTime(
                    'completed_at',
                )->nullable();

                $table->timestamps();

                $table->unique([
                    'user_id',
                    'review_date',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'daily_review_sessions',
        );
    }
};
