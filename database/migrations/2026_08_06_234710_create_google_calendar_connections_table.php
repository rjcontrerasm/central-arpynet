<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_connections', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('calendar_id')->default('primary');
            $table->string('calendar_summary')->nullable();
            $table->longText('token_data')->nullable();
            $table->json('scopes')->nullable();

            $table->dateTime('connected_at')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->dateTime('last_sync_at')->nullable();
            $table->dateTime('last_error_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_connections');
    }
};
