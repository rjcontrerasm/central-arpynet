<?php

namespace App\Filament\Resources\RecurringTaskRules\Pages;

use App\Filament\Resources\RecurringTaskRules\RecurringTaskRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRecurringTaskRules
    extends ManageRecords
{
    protected static string $resource =
        RecurringTaskRuleResource::class;

    public function getTitle(): string
    {
        return 'Tareas recurrentes';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(
                    'Nueva tarea recurrente',
                ),
        ];
    }
}
