<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'agent_action_proposals',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId(
                    'organization_id',
                )->constrained()->cascadeOnDelete();

                $table->foreignId(
                    'created_by',
                )->constrained('users')
                    ->cascadeOnDelete();

                $table->foreignId(
                    'reviewed_by',
                )->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string(
                    'subject_type',
                    40,
                )->index();

                $table->unsignedBigInteger(
                    'subject_id',
                )->index();

                $table->string(
                    'subject_title',
                    255,
                );

                $table->string(
                    'action_key',
                    120,
                )->index();

                $table->string(
                    'action_label',
                    255,
                );

                $table->string(
                    'risk',
                    40,
                );

                $table->string(
                    'effect',
                    40,
                );

                $table->json(
                    'proposed_changes',
                );

                $table->text(
                    'rationale',
                )->nullable();

                $table->string(
                    'fingerprint',
                    64,
                )->index();

                $table->string(
                    'status',
                    20,
                )->default('pending')
                    ->index();

                $table->timestamp(
                    'reviewed_at',
                )->nullable();

                $table->timestamps();

                $table->index([
                    'organization_id',
                    'status',
                    'created_at',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'agent_action_proposals',
        );
    }
};
