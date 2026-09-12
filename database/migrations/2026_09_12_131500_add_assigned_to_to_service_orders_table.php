<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->foreignId('assigned_to')
                ->nullable()
                ->after('last_activity_at')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['organization_id', 'assigned_to']);
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'assigned_to']);
            $table->dropConstrainedForeignId('assigned_to');
        });
    }
};
