<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 30)->default('project');
            $table->string('horizon', 20)->default('short');
            $table->string('status', 30)->default('planned');
            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->decimal('budget', 14, 2)->nullable();
            $table->string('currency', 3)->default('PEN');
            $table->string('next_action')->nullable();
            $table->text('blockers')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('last_activity_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_private')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['organization_id', 'status', 'horizon']);
            $table->index('target_date');
            $table->index('last_activity_at');
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('project_id')
                ->nullable()
                ->after('organization_id')
                ->constrained('projects')
                ->nullOnDelete();
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::dropIfExists('projects');
    }
};
