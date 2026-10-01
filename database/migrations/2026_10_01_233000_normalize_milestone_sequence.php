<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('service_order_milestones')
            ->whereNotNull('conformity_date')
            ->whereNull('delivered_date')
            ->orderBy('id')
            ->each(function ($milestone): void {
                DB::table('service_order_milestones')
                    ->where('id', $milestone->id)
                    ->update([
                        'delivered_date' => $milestone->conformity_date,
                        'updated_at' => now(),
                    ]);
            });

        DB::table('service_order_milestones')
            ->whereNotNull('delivered_date')
            ->whereNotNull('task_id')
            ->orderBy('id')
            ->each(function ($milestone): void {
                $completedAt = $milestone->delivered_date
                    ? $milestone->delivered_date.' 17:00:00'
                    : now();

                DB::table('tasks')
                    ->where('id', $milestone->task_id)
                    ->where('status', '!=', 'completed')
                    ->update([
                        'status' => 'completed',
                        'completed_at' => $completedAt,
                        'last_activity_at' => now(),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Data normalization is intentionally irreversible.
    }
};
