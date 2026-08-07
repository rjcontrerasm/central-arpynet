<?php

namespace App\Filament\Resources\RecurringObligations\Pages;

use App\Filament\Resources\RecurringObligations\RecurringObligationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRecurringObligations extends ManageRecords
{
    protected static string $resource =
        RecurringObligationResource::class;

    public function getTitle(): string
    {
        return 'Obligaciones recurrentes';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva obligación'),
        ];
    }
}
