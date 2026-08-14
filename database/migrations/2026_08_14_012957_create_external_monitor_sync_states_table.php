<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'external_monitor_sync_states',
            function (Blueprint $table): void {
                $table->id();
                $table->string('provider')->unique();
                $table->dateTime('last_sync_at')->nullable();
                $table->dateTime('last_success_at')->nullable();
                $table->dateTime('last_error_at')->nullable();
                $table->dateTime('last_generated_at')->nullable();
                $table->unsignedInteger('last_item_count')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamps();
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('external_monitor_sync_states');
    }
};
