<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('parent_task_id')
                ->nullable()
                ->constrained('tasks')
                ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('next_action')->nullable();

            $table->string('status', 30)->default('inbox');
            $table->string('urgency', 20)->default('normal');
            $table->string('impact', 20)->default('normal');

            $table->unsignedTinyInteger('priority_score')->default(0);
            $table->string('priority_band', 30)->default('planned');

            $table->dateTime('start_at')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->dateTime('waiting_until')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('last_activity_at')->nullable();

            $table->string('waiting_for')->nullable();
            $table->string('source', 30)->default('manual');
            $table->string('external_system', 40)->nullable();
            $table->string('external_id')->nullable();

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->boolean('is_private')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'organization_id',
                'status',
                'priority_band',
            ]);

            $table->index(['due_at', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['external_system', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
