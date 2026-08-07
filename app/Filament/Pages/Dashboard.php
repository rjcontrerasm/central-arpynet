<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Tasks\TaskResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Mi día';

    protected static ?string $navigationLabel = 'Mi día';

    public function getHeading(): string
    {
        return 'Mi día';
    }

    public function getSubheading(): ?string
    {
        return now()->translatedFormat('l, d \\d\\e F \\d\\e Y');
    }

    public function getColumns(): int | array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('tasks')
                ->label('Ver todas las tareas')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->url(TaskResource::getUrl()),
        ];
    }
}
