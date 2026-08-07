<?php

namespace App\Filament\Resources\Incidents\Pages;

use App\Filament\Resources\Incidents\IncidentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageIncidents extends ManageRecords
{
    protected static string $resource = IncidentResource::class;

    public function getTitle(): string
    {
        return 'Incidentes';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo incidente'),
        ];
    }
}
