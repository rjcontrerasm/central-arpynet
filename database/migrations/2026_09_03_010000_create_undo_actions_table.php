<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'undo_actions',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->string('action_type', 50);
                $table->string('label', 180);

                $table->string(
                    'entity_type',
                    60,
                )->nullable();

                $table->unsignedBigInteger(
                    'entity_id',
                )->nullable();

                $table->json('payload');

                $table->string(
                    'return_url',
                    500,
                )->nullable();

                $table->dateTime('expires_at');
                $table->dateTime('undone_at')->nullable();

                $table->dateTime(
                    'superseded_at',
                )->nullable();

                $table->timestamps();

                $table->index([
                    'user_id',
                    'expires_at',
                ]);

                $table->index([
                    'user_id',
                    'undone_at',
                    'superseded_at',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'undo_actions',
        );
    }
};
