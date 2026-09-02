<?php

namespace App\Filament\Resources\Tasks;

use App\Filament\Resources\Tasks\Pages\ManageTasks;
use App\Models\Task;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedCheckCircle;

    protected static ?string $navigationLabel = 'Tareas';

    protected static ?string $modelLabel = 'tarea';

    protected static ?string $pluralModelLabel = 'tareas';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $slug = 'tareas';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tarea')
                    ->schema([
                        Select::make('organization_id')
                            ->label('Empresa o ámbito')
                            ->options(
                                fn (): array => auth()->user()
                                    ?->organizations()
                                    ->wherePivot('is_active', true)
                                    ->orderBy('organizations.name')
                                    ->pluck(
                                        'organizations.name',
                                        'organizations.id',
                                    )
                                    ->all() ?? [],
                            )
                            ->default(
                                fn (): ?int => auth()->user()
                                    ?->current_organization_id,
                            )
                            ->searchable()
                            ->native(false)
                            ->required(),

                        Select::make('project_id')
                            ->label('Proyecto')
                            ->relationship(
                                name: 'project',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => auth()->user()
                                    ? $query->visibleTo(auth()->user())
                                    : $query->whereRaw('1 = 0'),
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->nullable(),

                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('next_action')
                            ->label('Próxima acción')
                            ->helperText(
                                'Describe el siguiente paso concreto.'
                            )
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Planificación y prioridad')
                    ->schema([
                        Select::make('status')
                            ->label('Estado')
                            ->options(Task::statusOptions())
                            ->default('inbox')
                            ->native(false)
                            ->required(),

                        Select::make('urgency')
                            ->label('Urgencia')
                            ->options(Task::urgencyOptions())
                            ->default('normal')
                            ->native(false)
                            ->required(),

                        Select::make('impact')
                            ->label('Impacto')
                            ->options(Task::impactOptions())
                            ->default('normal')
                            ->native(false)
                            ->required(),

                        DateTimePicker::make('start_at')
                            ->label('Inicio')
                            ->displayFormat('d/m/Y H:i'),

                        DateTimePicker::make('due_at')
                            ->label('Vencimiento')
                            ->displayFormat('d/m/Y H:i'),

                        DateTimePicker::make('waiting_until')
                            ->label('Esperar hasta')
                            ->displayFormat('d/m/Y H:i'),

                        TextInput::make('waiting_for')
                            ->label('Esperando a')
                            ->maxLength(255),

                        Toggle::make('is_private')
                            ->label('Privada')
                            ->helperText(
                                'No se compartirá con futuros colaboradores.'
                            ),
                    ])
                    ->columns(2),

                Hidden::make('source')
                    ->default('manual'),

                Hidden::make('assigned_to')
                    ->default(fn (): ?int => auth()->id()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('priority_score', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Tarea')
                    ->description(
                        fn (Task $record): ?string =>
                            $record->next_action
                                ? 'Siguiente: '.$record->next_action
                                : null,
                    )
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('organization.name')
                    ->label('Empresa / ámbito')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('project.name')
                    ->label('Proyecto')
                    ->placeholder('Sin proyecto')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            Task::statusOptions()[$state]
                                ?? 'Sin definir',
                    )
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'completed' => 'success',
                            'cancelled' => 'gray',
                            'waiting' => 'warning',
                            'delegated' => 'info',
                            'in_progress' => 'primary',
                            default => 'gray',
                        },
                    ),

                TextColumn::make('priority_score')
                    ->label('Puntaje')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(
                        fn ($state): string => match (true) {
                            (int) $state >= 85 => 'danger',
                            (int) $state >= 65 => 'warning',
                            (int) $state >= 40 => 'info',
                            default => 'gray',
                        },
                    ),

                TextColumn::make('priority_band')
                    ->label('Atención')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            Task::priorityBandOptions()[$state]
                                ?? 'Planificado',
                    )
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'critical' => 'danger',
                            'today' => 'warning',
                            'week' => 'info',
                            'completed' => 'success',
                            default => 'gray',
                        },
                    ),

                TextColumn::make('due_at')
                    ->label('Vencimiento')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Sin fecha')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Actualizada')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->label('Empresa o ámbito')
                    ->options(
                        fn (): array => auth()->user()
                            ?->organizations()
                            ->wherePivot('is_active', true)
                            ->orderBy('organizations.name')
                            ->pluck(
                                'organizations.name',
                                'organizations.id',
                            )
                            ->all() ?? [],
                    ),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Task::statusOptions()),

                SelectFilter::make('priority_band')
                    ->label('Atención')
                    ->options(Task::priorityBandOptions()),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return parent::getEloquentQuery()
                ->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->visibleTo($user);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTasks::route('/'),
        ];
    }
}
