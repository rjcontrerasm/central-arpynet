<?php

namespace App\Filament\Resources\WorkTeams\Pages;

use App\Filament\Resources\WorkTeams\WorkTeamResource;
use App\Models\WorkTeam;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWorkTeams extends ManageRecords
{
    protected static string $resource = WorkTeamResource::class;

    public function getTitle(): string
    {
        return 'Equipos de trabajo';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo equipo')
                ->mutateDataUsing(
                    function (array $data): array {
                        $data['created_by'] = auth()->id();

                        return $data;
                    },
                )
                ->after(
                    function (WorkTeam $record): void {
                        if (auth()->id()) {
                            $record->users()->syncWithoutDetaching([
                                auth()->id() => [
                                    'role' => 'lead',
                                    'is_active' => true,
                                ],
                            ]);
                        }
                    },
                ),
        ];
    }
}
