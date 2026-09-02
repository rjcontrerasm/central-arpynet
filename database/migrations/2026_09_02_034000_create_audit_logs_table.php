<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create(
            'audit_logs',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('organization_id')
                    ->nullable()
                    ->constrained('organizations')
                    ->nullOnDelete();

                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('event', 30);
                $table->string('subject_type', 80);
                $table->unsignedBigInteger('subject_id')
                    ->nullable();
                $table->string('subject_label')
                    ->nullable();
                $table->string('source', 30)
                    ->default('web');
                $table->json('changes')
                    ->nullable();
                $table->timestamp('occurred_at');
                $table->timestamps();

                $table->index(
                    [
                        'organization_id',
                        'occurred_at',
                    ],
                    'audit_logs_org_date_idx',
                );

                $table->index(
                    [
                        'subject_type',
                        'subject_id',
                    ],
                    'audit_logs_subject_idx',
                );

                $table->index(
                    [
                        'event',
                        'occurred_at',
                    ],
                    'audit_logs_event_date_idx',
                );

                $table->index(
                    [
                        'user_id',
                        'occurred_at',
                    ],
                    'audit_logs_user_date_idx',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
