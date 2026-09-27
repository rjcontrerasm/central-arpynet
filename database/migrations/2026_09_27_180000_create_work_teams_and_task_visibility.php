<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_teams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('home_organization_id')
                ->nullable()
                ->constrained('organizations')
                ->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['home_organization_id', 'is_active']);
            $table->unique(
                ['home_organization_id', 'name'],
                'work_teams_home_org_name_unique',
            );
        });

        Schema::create('work_team_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('work_team_id')
                ->constrained('work_teams')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['work_team_id', 'user_id']);
            $table->index(['user_id', 'is_active']);
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->string('visibility_scope')
                ->default('organization')
                ->after('is_private')
                ->index();
        });

        Schema::create('task_work_team', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')
                ->constrained('tasks')
                ->cascadeOnDelete();
            $table->foreignId('work_team_id')
                ->constrained('work_teams')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'work_team_id']);
            $table->index(['work_team_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_work_team');

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('visibility_scope');
        });

        Schema::dropIfExists('work_team_user');
        Schema::dropIfExists('work_teams');
    }
};
