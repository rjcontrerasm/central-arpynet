<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('tax_id', 20)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('drive_url')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active']);
            $table->index('tax_id');
            $table->index('name');
        });

        Schema::create('service_orders', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('client_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('stage', 40)->default('opportunity');
            $table->dateTime('stage_changed_at')->nullable();

            $table->string('quotation_number', 80)->nullable();
            $table->date('quotation_date')->nullable();

            $table->string('order_number', 100)->nullable();
            $table->date('order_received_date')->nullable();

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->date('report_submitted_date')->nullable();
            $table->date('conformity_date')->nullable();

            $table->string('invoice_number', 100)->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('invoice_due_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->date('closed_date')->nullable();

            $table->decimal('amount', 14, 2)->nullable();
            $table->decimal('invoice_amount', 14, 2)->nullable();
            $table->string('currency', 3)->default('PEN');
            $table->boolean('includes_tax')->default(true);

            $table->string('next_action')->nullable();
            $table->dateTime('next_action_at')->nullable();

            $table->string('drive_url')->nullable();
            $table->text('notes')->nullable();

            $table->dateTime('last_activity_at')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index([
                'organization_id',
                'stage',
                'stage_changed_at',
            ]);

            $table->index(['client_id', 'stage']);
            $table->index(['invoice_due_date', 'paid_date']);
            $table->index('next_action_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_orders');
        Schema::dropIfExists('clients');
    }
};
