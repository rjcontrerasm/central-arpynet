<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        DB::table('service_orders')
            ->orderBy('id')
            ->get()
            ->each(function ($serviceOrder): void {
                $executionOrderId = null;

                if (
                    filled($serviceOrder->order_number)
                    || filled($serviceOrder->order_received_date)
                ) {
                    $yearSource = $serviceOrder->order_received_date
                        ?: $serviceOrder->start_date;

                    $executionOrderId = DB::table(
                        'service_order_execution_orders',
                    )->insertGetId([
                        'service_order_id' => $serviceOrder->id,
                        'fiscal_year' => $yearSource
                            ? (int) substr((string) $yearSource, 0, 4)
                            : null,
                        'document_type' => 'service_order',
                        'document_number' => $serviceOrder->order_number,
                        'issued_date' => $serviceOrder->order_received_date,
                        'start_date' => $serviceOrder->start_date,
                        'end_date' => $serviceOrder->end_date,
                        'amount' => $serviceOrder->invoice_amount
                            ?: $serviceOrder->amount,
                        'status' => 'issued',
                        'created_by' => $serviceOrder->created_by,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if (
                    filled($serviceOrder->invoice_number)
                    || filled($serviceOrder->invoice_date)
                    || filled($serviceOrder->invoice_amount)
                ) {
                    DB::table('service_order_invoices')->insert([
                        'service_order_id' => $serviceOrder->id,
                        'execution_order_id' => $executionOrderId,
                        'number' => $serviceOrder->invoice_number,
                        'issue_date' => $serviceOrder->invoice_date,
                        'due_date' => $serviceOrder->invoice_due_date,
                        'paid_date' => $serviceOrder->paid_date,
                        'amount' => $serviceOrder->invoice_amount,
                        'status' => $serviceOrder->paid_date
                            ? 'paid'
                            : 'issued',
                        'created_by' => $serviceOrder->created_by,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
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
