<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('trigger_key', 80);
            $table->string('action_key', 80);
            $table->json('trigger_config')->nullable();
            $table->json('action_config')->nullable();
            $table->string('mode', 24)->default('preview');
            $table->boolean('is_active')->default(false);
            $table->dateTime('last_evaluated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['organization_id', 'is_active']);
            $table->index(['trigger_key', 'action_key']);
        });

        Schema::create('automation_rule_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('automation_rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->string('fingerprint', 64);
            $table->string('outcome', 32);
            $table->json('payload')->nullable();
            $table->dateTime('evaluated_at');
            $table->dateTime('executed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['automation_rule_id', 'evaluated_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->unique(
                ['automation_rule_id', 'subject_type', 'subject_id', 'fingerprint'],
                'automation_runs_unique_fingerprint',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rule_runs');
        Schema::dropIfExists('automation_rules');
    }
};
