<?php

namespace App\Filament\Resources\ObligationOccurrences\Pages;

use App\Filament\Resources\ObligationOccurrences\ObligationOccurrenceResource;
use Filament\Resources\Pages\ManageRecords;

class ManageObligationOccurrences extends ManageRecords
{
    protected static string $resource =
        ObligationOccurrenceResource::class;

    public function getTitle(): string
    {
        return 'Vencimientos';
    }
}
