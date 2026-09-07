<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'agent_action_proposals',
            function (Blueprint $table): void {
                $table->string('subject_version', 80)->nullable();
                $table->unsignedBigInteger('executed_by')->nullable()->index();
                $table->timestamp('executed_at')->nullable();
                $table->json('execution_before')->nullable();
                $table->json('execution_after')->nullable();
                $table->unsignedBigInteger('undo_action_id')->nullable()->index();
            },
        );
    }

    public function down(): void
    {
        Schema::table(
            'agent_action_proposals',
            function (Blueprint $table): void {
                $table->dropIndex(['executed_by']);
                $table->dropIndex(['undo_action_id']);
                $table->dropColumn([
                    'subject_version',
                    'executed_by',
                    'executed_at',
                    'execution_before',
                    'execution_after',
                    'undo_action_id',
                ]);
            },
        );
    }
};
