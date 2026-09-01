<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasColumn(
                'tasks',
                'waiting_since',
            )
        ) {
            Schema::table(
                'tasks',
                function (Blueprint $table): void {
                    $table->timestamp('waiting_since')
                        ->nullable()
                        ->after('waiting_until');
                },
            );
        }

        if (
            ! Schema::hasColumn(
                'tasks',
                'waiting_reason',
            )
        ) {
            Schema::table(
                'tasks',
                function (Blueprint $table): void {
                    $table->string(
                        'waiting_reason',
                        255,
                    )
                        ->nullable()
                        ->after('waiting_since');
                },
            );
        }
    }

    public function down(): void
    {
        if (
            Schema::hasColumn(
                'tasks',
                'waiting_reason',
            )
        ) {
            Schema::table(
                'tasks',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'waiting_reason',
                    );
                },
            );
        }

        if (
            Schema::hasColumn(
                'tasks',
                'waiting_since',
            )
        ) {
            Schema::table(
                'tasks',
                function (Blueprint $table): void {
                    $table->dropColumn(
                        'waiting_since',
                    );
                },
            );
        }
    }
};
