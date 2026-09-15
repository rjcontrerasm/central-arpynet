<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_organization', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('client_id')
                ->constrained('clients')
                ->cascadeOnDelete();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['client_id', 'organization_id'],
                'client_org_unique',
            );

            $table->index(
                ['organization_id', 'is_active'],
                'client_org_active_index',
            );
        });

        DB::table('clients')
            ->select([
                'id',
                'organization_id',
                'created_by',
                'created_at',
                'updated_at',
            ])
            ->whereNotNull('organization_id')
            ->orderBy('id')
            ->chunkById(500, function ($clients): void {
                $rows = [];

                foreach ($clients as $client) {
                    $rows[] = [
                        'client_id' => $client->id,
                        'organization_id' => $client->organization_id,
                        'is_active' => true,
                        'created_by' => $client->created_by,
                        'created_at' => $client->created_at ?? now(),
                        'updated_at' => $client->updated_at ?? now(),
                    ];
                }

                if ($rows !== []) {
                    DB::table('client_organization')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_organization');
    }
};
