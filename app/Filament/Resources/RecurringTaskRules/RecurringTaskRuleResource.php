<?php

namespace App\Filament\Resources\RecurringTaskRules;

use App\Filament\Resources\RecurringTaskRules\Pages\ManageRecurringTaskRules;
use App\Models\RecurringTaskRule;
use App\Models\Task;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RecurringTaskRuleResource
    extends Resource
{
    protected static ?string $model =
        RecurringTaskRule::class;

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel =
        'Tareas recurrentes';

    protected static ?string $modelLabel =
        'tarea recurrente';

    protected static ?string $pluralModelLabel =
        'tareas recurrentes';

    protected static ?string $recordTitleAttribute =
        'title';

    protected static ?string $slug =
        'tareas-recurrentes';

    protected static ?int $navigationSort =
        25;

    public static function form(
        Schema $schema,
    ): Schema {
        return $schema
            ->components([
                Section::make(
                    'Tarea',
                )
                    ->schema([
                        Select::make(
                            'organization_id',
                        )
                            ->label(
                                'Empresa o ámbito',
                            )
                            ->options(
                                fn (): array =>
                                    auth()
                                        ->user()
                                        ?->organizations()
                                        ->wherePivot(
                                            'is_active',
                                            true,
                                        )
                                        ->orderBy(
                                            'organizations.name',
                                        )
                                        ->pluck(
                                            'organizations.name',
                                            'organizations.id',
                                        )
                                        ->all()
                                    ?? [],
                            )
                            ->default(
                                fn (): ?int =>
                                    auth()
                                        ->user()
                                        ?->current_organization_id,
                            )
                            ->searchable()
                            ->native(false)
                            ->required(),

                        Select::make(
                            'project_id',
                        )
                            ->label(
                                'Proyecto',
                            )
                            ->relationship(
                                name: 'project',
                                titleAttribute:
                                    'name',
                                modifyQueryUsing:
                                    fn (
                                        $query,
                                    ) =>
                                        auth()
                                            ->user()
                                        ? $query
                                            ->visibleTo(
                                                auth()
                                                    ->user(),
                                            )
                                        : $query
                                            ->whereRaw(
                                                '1 = 0',
                                            ),
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->nullable(),

                        TextInput::make(
                            'title',
                        )
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make(
                            'next_action',
                        )
                            ->label(
                                'Próxima acción',
                            )
                            ->helperText(
                                'Paso concreto que aparecerá en cada tarea generada.',
                            )
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make(
                            'description',
                        )
                            ->label(
                                'Descripción',
                            )
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(
                    'Recurrencia',
                )
                    ->schema([
                        Select::make(
                            'frequency',
                        )
                            ->label(
                                'Frecuencia',
                            )
                            ->options(
                                RecurringTaskRule::frequencyOptions(),
                            )
                            ->default(
                                'weekly',
                            )
                            ->native(false)
                            ->required(),

                        DatePicker::make(
                            'anchor_date',
                        )
                            ->label(
                                'Primera fecha',
                            )
                            ->helperText(
                                'Define el ciclo. No se generan tareas históricas anteriores a hoy.',
                            )
                            ->displayFormat(
                                'd/m/Y',
                            )
                            ->required(),

                        DatePicker::make(
                            'end_date',
                        )
                            ->label(
                                'Finaliza',
                            )
                            ->displayFormat(
                                'd/m/Y',
                            ),

                        TextInput::make(
                            'create_days_before',
                        )
                            ->label(
                                'Crear con anticipación',
                            )
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(90)
                            ->suffix('días')
                            ->default(0)
                            ->required(),

                        TextInput::make(
                            'due_time',
                        )
                            ->label(
                                'Hora de vencimiento',
                            )
                            ->default('17:00')
                            ->placeholder(
                                '17:00',
                            )
                            ->regex(
                                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                            )
                            ->helperText(
                                'Formato 24 horas, por ejemplo 17:00.',
                            )
                            ->required(),

                        Toggle::make(
                            'is_active',
                        )
                            ->label('Activa')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make(
                    'Prioridad',
                )
                    ->schema([
                        Select::make(
                            'urgency',
                        )
                            ->label(
                                'Urgencia',
                            )
                            ->options(
                                Task::urgencyOptions(),
                            )
                            ->default(
                                'normal',
                            )
                            ->native(false)
                            ->required(),

                        Select::make(
                            'impact',
                        )
                            ->label(
                                'Impacto',
                            )
                            ->options(
                                Task::impactOptions(),
                            )
                            ->default(
                                'normal',
                            )
                            ->native(false)
                            ->required(),

                        Toggle::make(
                            'is_private',
                        )
                            ->label(
                                'Privada',
                            )
                            ->helperText(
                                'Se aplicará a cada tarea generada.',
                            ),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(
        Table $table,
    ): Table {
        return $table
            ->defaultSort(
                'title',
            )
            ->columns([
                TextColumn::make(
                    'title',
                )
                    ->label(
                        'Tarea recurrente',
                    )
                    ->description(
                        fn (
                            RecurringTaskRule $record,
                        ): ?string =>
                            $record
                                ->next_action
                            ? 'Siguiente: '
                                .$record
                                    ->next_action
                            : null,
                    )
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make(
                    'organization.name',
                )
                    ->label(
                        'Empresa / ámbito',
                    )
                    ->badge(),

                TextColumn::make(
                    'frequency',
                )
                    ->label(
                        'Frecuencia',
                    )
                    ->formatStateUsing(
                        fn (
                            ?string $state,
                        ): string =>
                            RecurringTaskRule::frequencyOptions()[
                                $state
                            ]
                            ?? 'Sin definir',
                    ),

                TextColumn::make(
                    'anchor_date',
                )
                    ->label(
                        'Inicio',
                    )
                    ->date('d/m/Y'),

                TextColumn::make(
                    'create_days_before',
                )
                    ->label(
                        'Anticipación',
                    )
                    ->suffix(' d')
                    ->toggleable(
                        isToggledHiddenByDefault:
                            true,
                    ),

                IconColumn::make(
                    'is_active',
                )
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make(
                    'organization_id',
                )
                    ->label(
                        'Empresa / ámbito',
                    )
                    ->options(
                        fn (): array =>
                            auth()
                                ->user()
                                ?->organizations()
                                ->wherePivot(
                                    'is_active',
                                    true,
                                )
                                ->orderBy(
                                    'organizations.name',
                                )
                                ->pluck(
                                    'organizations.name',
                                    'organizations.id',
                                )
                                ->all()
                            ?? [],
                    ),

                SelectFilter::make(
                    'frequency',
                )
                    ->label(
                        'Frecuencia',
                    )
                    ->options(
                        RecurringTaskRule::frequencyOptions(),
                    ),
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
            return parent::
                getEloquentQuery()
                ->whereRaw(
                    '1 = 0',
                );
        }

        return parent::
            getEloquentQuery()
            ->visibleTo($user);
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                ManageRecurringTaskRules::route(
                    '/',
                ),
        ];
    }
}
