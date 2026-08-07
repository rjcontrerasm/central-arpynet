<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use App\Support\DailyDashboard;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingTasks extends TableWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = auth()->user();

        return $table
            ->heading('Próximos 7 días')
            ->query(
                $user
                    ? app(DailyDashboard::class)->upcomingTasks($user)
                    : Task::query()->whereRaw('1 = 0'),
            )
            ->defaultSort('due_at')
            ->columns([
                TextColumn::make('due_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Tarea')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('organization.name')
                    ->label('Empresa / ámbito')
                    ->badge(),
                TextColumn::make('priority_score')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        (int) $state >= 85 => 'danger',
                        (int) $state >= 65 => 'warning',
                        (int) $state >= 40 => 'info',
                        default => 'gray',
                    }),
            ])
            ->emptyStateHeading('No hay vencimientos próximos')
            ->emptyStateDescription('No existen tareas con fecha durante los siguientes 7 días.');
    }
}
