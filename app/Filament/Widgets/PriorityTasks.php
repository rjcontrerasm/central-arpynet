<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use App\Support\DailyDashboard;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PriorityTasks extends TableWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $user = auth()->user();

        return $table
            ->heading('Atender ahora')
            ->query(
                $user
                    ? app(DailyDashboard::class)->priorityTasks($user)
                    : Task::query()->whereRaw('1 = 0'),
            )
            ->defaultSort('priority_score', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Tarea')
                    ->description(fn (Task $record): ?string =>
                        $record->next_action
                            ? 'Siguiente: '.$record->next_action
                            : null)
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
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string =>
                        Task::statusOptions()[$state] ?? 'Sin definir'),
                TextColumn::make('due_at')
                    ->label('Vencimiento')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Sin fecha')
                    ->sortable(),
            ])
            ->emptyStateHeading('No hay tareas pendientes')
            ->emptyStateDescription('Crea una tarea para comenzar el seguimiento.');
    }
}
