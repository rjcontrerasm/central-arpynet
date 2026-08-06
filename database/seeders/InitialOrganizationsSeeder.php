<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class InitialOrganizationsSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->where('email', 'rcontreras@arpynet.com')->first();

        if (! $user) {
            throw new RuntimeException('No existe el usuario rcontreras@arpynet.com.');
        }

        $items = [
            ['name' => 'Personal', 'slug' => 'personal', 'category' => 'personal'],
            ['name' => 'Casa Andina', 'slug' => 'casa-andina', 'category' => 'employment'],
            ['name' => 'ARPYNET', 'slug' => 'arpynet', 'category' => 'company', 'legal_name' => 'ARPYNET S.A.C.', 'tax_id' => '20600708067'],
            ['name' => 'PC SOTEC', 'slug' => 'pc-sotec', 'category' => 'company'],
            ['name' => 'ANX CORPORATION', 'slug' => 'anx-corporation', 'category' => 'company'],
            ['name' => '100 PUNTOS', 'slug' => '100-puntos', 'category' => 'company'],
            ['name' => 'LOROGRAFIC', 'slug' => 'lorografic', 'category' => 'company'],
            ['name' => 'PRODUCTX', 'slug' => 'productx', 'category' => 'company'],
        ];

        $default = null;

        foreach ($items as $item) {
            $organization = Organization::query()->updateOrCreate(
                ['slug' => $item['slug']],
                [
                    ...$item,
                    'timezone' => 'America/Lima',
                    'is_active' => true,
                    'created_by' => $user->id,
                ],
            );

            $isDefault = $organization->slug === 'arpynet';

            $organization->users()->syncWithoutDetaching([
                $user->id => [
                    'role' => 'owner',
                    'is_default' => $isDefault,
                    'is_active' => true,
                ],
            ]);

            if ($isDefault) {
                $default = $organization;
            }
        }

        if ($default) {
            $user->forceFill([
                'current_organization_id' => $default->id,
            ])->save();
        }
    }
}
