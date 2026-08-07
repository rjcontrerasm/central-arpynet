<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('client_id')
                ->nullable()
                ->constrained('clients')
                ->nullOnDelete();

            $table->foreignId('service_order_id')
                ->nullable()
                ->constrained('service_orders')
                ->nullOnDelete();

            $table->foreignId('project_id')
                ->nullable()
                ->constrained('projects')
                ->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('category', 40)->default('availability');
            $table->string('severity', 20)->default('medium');
            $table->string('status', 30)->default('new');

            $table->string('affected_service')->nullable();

            $table->string('source', 40)->default('manual');
            $table->string('external_id')->nullable();
            $table->string('external_url')->nullable();

            $table->dateTime('detected_at')->nullable();
            $table->dateTime('acknowledged_at')->nullable();
            $table->dateTime('mitigated_at')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();

            $table->dateTime('response_due_at')->nullable();
            $table->dateTime('resolution_due_at')->nullable();

            $table->string('next_action')->nullable();
            $table->dateTime('next_action_at')->nullable();

            $table->text('root_cause')->nullable();
            $table->text('resolution_summary')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->dateTime('last_activity_at')->nullable();
            $table->boolean('is_private')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'organization_id',
                'status',
                'severity',
            ]);

            $table->index([
                'client_id',
                'status',
            ]);

            $table->index([
                'response_due_at',
                'status',
            ]);

            $table->index([
                'resolution_due_at',
                'status',
            ]);

            $table->index([
                'next_action_at',
                'status',
            ]);

            $table->unique([
                'organization_id',
                'source',
                'external_id',
            ], 'incident_external_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
