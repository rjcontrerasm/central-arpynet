<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->string('contract_url', 500)
                ->nullable()
                ->after('contract_amount');
        });

        Schema::table('service_order_execution_orders', function (Blueprint $table): void {
            $table->string('document_url', 500)
                ->nullable()
                ->after('document_number');
        });

        Schema::table('service_order_invoices', function (Blueprint $table): void {
            $table->string('document_url', 500)
                ->nullable()
                ->after('number');
        });
    }

    public function down(): void
    {
        Schema::table('service_order_invoices', function (Blueprint $table): void {
            $table->dropColumn('document_url');
        });

        Schema::table('service_order_execution_orders', function (Blueprint $table): void {
            $table->dropColumn('document_url');
        });

        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropColumn('contract_url');
        });
    }
};
