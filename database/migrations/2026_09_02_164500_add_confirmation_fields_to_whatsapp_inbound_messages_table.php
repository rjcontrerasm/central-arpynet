<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'whatsapp_inbound_messages',
            function (Blueprint $table): void {
                $table->string(
                    'confirmation_status',
                    30,
                )
                    ->nullable()
                    ->after('processed_at')
                    ->index();

                $table->string(
                    'confirmation_message_id',
                    255,
                )
                    ->nullable()
                    ->after('confirmation_status');

                $table->dateTime(
                    'confirmation_sent_at',
                )
                    ->nullable()
                    ->after('confirmation_message_id');

                $table->string(
                    'confirmation_error_code',
                    80,
                )
                    ->nullable()
                    ->after('confirmation_sent_at');
            },
        );
    }

    public function down(): void
    {
        Schema::table(
            'whatsapp_inbound_messages',
            function (Blueprint $table): void {
                $table->dropIndex([
                    'confirmation_status',
                ]);

                $table->dropColumn([
                    'confirmation_status',
                    'confirmation_message_id',
                    'confirmation_sent_at',
                    'confirmation_error_code',
                ]);
            },
        );
    }
};
