<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProjects extends ManageRecords
{
    protected static string $resource = ProjectResource::class;

    public function getTitle(): string
    {
        return 'Proyectos';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Nuevo proyecto')];
    }
}
