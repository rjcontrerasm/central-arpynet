<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_event_links', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('calendar_id')->default('primary');
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id');

            $table->string('google_event_id');
            $table->char('content_hash', 64)->nullable();

            $table->dateTime('last_synced_at')->nullable();
            $table->dateTime('last_error_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->unique([
                'user_id',
                'source_type',
                'source_id',
            ], 'calendar_event_source_unique');

            $table->index([
                'user_id',
                'google_event_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_event_links');
    }
};
