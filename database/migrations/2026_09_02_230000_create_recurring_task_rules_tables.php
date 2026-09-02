<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'recurring_task_rules',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id',
                )
                    ->constrained(
                        'organizations',
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'project_id',
                )
                    ->nullable()
                    ->constrained(
                        'projects',
                    )
                    ->nullOnDelete();

                $table->string('title');

                $table->text(
                    'description',
                )->nullable();

                $table->string(
                    'next_action',
                )->nullable();

                $table->string(
                    'frequency',
                    30,
                )->default('weekly');

                $table->date(
                    'anchor_date',
                );

                $table->date(
                    'end_date',
                )->nullable();

                $table->unsignedSmallInteger(
                    'create_days_before',
                )->default(0);

                $table->time(
                    'due_time',
                )->default('17:00:00');

                $table->string(
                    'urgency',
                    20,
                )->default('normal');

                $table->string(
                    'impact',
                    20,
                )->default('normal');

                $table->boolean(
                    'is_private',
                )->default(false);

                $table->boolean(
                    'is_active',
                )->default(true);

                $table->foreignId(
                    'assigned_to',
                )
                    ->nullable()
                    ->constrained(
                        'users',
                    )
                    ->nullOnDelete();

                $table->foreignId(
                    'created_by',
                )
                    ->nullable()
                    ->constrained(
                        'users',
                    )
                    ->nullOnDelete();

                $table->timestamps();
                $table->softDeletes();

                $table->index([
                    'is_active',
                    'anchor_date',
                ]);
            },
        );

        Schema::create(
            'recurring_task_runs',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'recurring_task_rule_id',
                )
                    ->constrained(
                        'recurring_task_rules',
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'organization_id',
                )
                    ->constrained(
                        'organizations',
                    )
                    ->cascadeOnDelete();

                $table->date(
                    'scheduled_for',
                );

                $table->foreignId(
                    'task_id',
                )
                    ->nullable()
                    ->constrained(
                        'tasks',
                    )
                    ->nullOnDelete();

                $table->dateTime(
                    'generated_at',
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'recurring_task_rule_id',
                        'scheduled_for',
                    ],
                    'rec_task_runs_rule_date_unique',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'recurring_task_runs',
        );

        Schema::dropIfExists(
            'recurring_task_rules',
        );
    }
};
