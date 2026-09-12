<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return 'Equipo';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo usuario')
                ->after(
                    function (User $record): void {
                        $actor = auth()->user();
                        $organizationId = (int) ($actor?->current_organization_id ?? 0);

                        if (
                            ! $actor
                            || $organizationId < 1
                            || ! in_array(
                                $organizationId,
                                $actor->manageableOrganizationIds(),
                                true,
                            )
                        ) {
                            return;
                        }

                        $record->organizations()->syncWithoutDetaching([
                            $organizationId => [
                                'role' => 'member',
                                'is_default' => true,
                                'is_active' => true,
                            ],
                        ]);

                        $record->forceFill([
                            'current_organization_id' => $organizationId,
                        ])->save();
                    },
                ),
        ];
    }
}
