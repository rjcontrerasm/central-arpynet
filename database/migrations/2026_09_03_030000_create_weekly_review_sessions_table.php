<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'weekly_review_sessions',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->date('week_start');

                $table->dateTime(
                    'carryover_reviewed_at',
                )->nullable();

                $table->dateTime(
                    'stagnation_reviewed_at',
                )->nullable();

                $table->dateTime(
                    'finance_reviewed_at',
                )->nullable();

                $table->dateTime(
                    'obligations_reviewed_at',
                )->nullable();

                $table->dateTime(
                    'horizon_reviewed_at',
                )->nullable();

                $table->dateTime(
                    'completed_at',
                )->nullable();

                $table->timestamps();

                $table->unique([
                    'user_id',
                    'week_start',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'weekly_review_sessions',
        );
    }
};
