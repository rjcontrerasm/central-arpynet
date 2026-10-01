<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->foreignId('work_team_id')
                ->nullable()
                ->after('assigned_to')
                ->constrained('work_teams')
                ->nullOnDelete();

            $table->index(['work_team_id', 'stage']);
        });

        Schema::create('service_order_milestones', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('service_order_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('task_id')
                ->nullable()
                ->unique()
                ->constrained('tasks')
                ->nullOnDelete();

            $table->unsignedInteger('sequence')->default(1);
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('contractual_due_date')->nullable();
            $table->date('delivered_date')->nullable();
            $table->date('conformity_date')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['service_order_id', 'sequence'],
                'service_order_milestone_sequence_unique',
            );

            // Keep the explicit name below MariaDB's
            // 64-character identifier limit.
            $table->index(
                [
                    'service_order_id',
                    'contractual_due_date',
                ],
                'so_milestones_order_due_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_milestones');

        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropForeign(['work_team_id']);
            $table->dropIndex(['work_team_id', 'stage']);
            $table->dropColumn('work_team_id');
        });
    }
};
