<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_obligations', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('category', 40)->default('service');
            $table->text('description')->nullable();

            $table->string('frequency', 30)->default('monthly');
            $table->date('anchor_date');
            $table->date('end_date')->nullable();

            $table->decimal('expected_amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('PEN');

            $table->unsignedSmallInteger('reminder_days_before')->default(7);
            $table->boolean('is_critical')->default(false);
            $table->boolean('is_active')->default(true);

            $table->string('provider')->nullable();
            $table->string('reference')->nullable();
            $table->string('drive_url')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'organization_id',
                'category',
                'is_active',
            ]);

            $table->index(['frequency', 'anchor_date']);
        });

        Schema::create('obligation_occurrences', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('recurring_obligation_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->date('due_date');
            $table->string('status', 20)->default('pending');

            $table->decimal('expected_amount', 14, 2)->nullable();
            $table->decimal('actual_amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('PEN');

            $table->date('paid_date')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('receipt_url')->nullable();
            $table->text('notes')->nullable();

            $table->dateTime('completed_at')->nullable();

            $table->timestamps();

            $table->unique([
                'recurring_obligation_id',
                'due_date',
            ], 'obligation_occurrence_unique_due');

            $table->index([
                'organization_id',
                'status',
                'due_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligation_occurrences');
        Schema::dropIfExists('recurring_obligations');
    }
};
