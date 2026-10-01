<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->string('contract_document_type', 40)
                ->nullable()
                ->after('description');
            $table->string('contract_number', 120)
                ->nullable()
                ->after('contract_document_type');
            $table->date('contract_date')
                ->nullable()
                ->after('contract_number');
            $table->decimal('contract_amount', 14, 2)
                ->nullable()
                ->after('contract_date');
        });

        Schema::create('service_order_execution_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_order_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('fiscal_year')->nullable();
            $table->string('document_type', 40)->default('service_order');
            $table->string('document_number', 120)->nullable();
            $table->date('issued_date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['service_order_id', 'fiscal_year'],
                'so_exec_order_year_idx',
            );
        });

        Schema::create('service_order_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_order_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('execution_order_id')
                ->nullable()
                ->constrained('service_order_execution_orders')
                ->nullOnDelete();
            $table->string('number', 120)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->decimal('amount', 14, 2)->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['service_order_id', 'status'],
                'so_invoice_status_idx',
            );
        });

        Schema::table('service_order_milestones', function (Blueprint $table): void {
            $table->foreignId('execution_order_id')
                ->nullable()
                ->after('task_id')
                ->constrained('service_order_execution_orders')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('service_order_milestones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('execution_order_id');
        });

        Schema::dropIfExists('service_order_invoices');
        Schema::dropIfExists('service_order_execution_orders');

        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropColumn([
                'contract_document_type',
                'contract_number',
                'contract_date',
                'contract_amount',
            ]);
        });
    }
};
