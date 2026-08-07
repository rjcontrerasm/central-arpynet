<?php

namespace App\Filament\Resources\Incidents;

use App\Filament\Resources\Incidents\Pages\ManageIncidents;
use App\Models\Client;
use App\Models\Incident;
use App\Models\Project;
use App\Models\ServiceOrder;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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

class IncidentResource extends Resource
{
    protected static ?string $model = Incident::class;

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedExclamationTriangle;

    protected static ?string $navigationLabel = 'Incidentes';

    protected static ?string $modelLabel = 'incidente';

    protected static ?string $pluralModelLabel = 'incidentes';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $slug = 'incidentes';

    protected static ?int $navigationSort = 70;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Incidente')
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

                        TextInput::make('title')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('category')
                            ->label('Categoría')
                            ->options(Incident::categoryOptions())
                            ->default('availability')
                            ->native(false)
                            ->required(),

                        Select::make('severity')
                            ->label('Severidad')
                            ->options(Incident::severityOptions())
                            ->default('medium')
                            ->native(false)
                            ->required(),

                        Select::make('status')
                            ->label('Estado')
                            ->options(Incident::statusOptions())
                            ->default('new')
                            ->native(false)
                            ->required(),

                        TextInput::make('affected_service')
                            ->label('Servicio afectado')
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Relaciones')
                    ->schema([
                        Select::make('client_id')
                            ->label('Cliente')
                            ->options(
                                fn (): array => auth()->user()
                                    ? Client::query()
                                        ->visibleTo(auth()->user())
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all()
                                    : [],
                            )
                            ->searchable()
                            ->native(false),

                        Select::make('service_order_id')
                            ->label('Orden / servicio')
                            ->options(
                                fn (): array => auth()->user()
                                    ? ServiceOrder::query()
                                        ->visibleTo(auth()->user())
                                        ->orderByDesc('updated_at')
                                        ->pluck('title', 'id')
                                        ->all()
                                    : [],
                            )
                            ->searchable()
                            ->native(false),

                        Select::make('project_id')
                            ->label('Proyecto')
                            ->options(
                                fn (): array => auth()->user()
                                    ? Project::query()
                                        ->visibleTo(auth()->user())
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all()
                                    : [],
                            )
                            ->searchable()
                            ->native(false),

                        Toggle::make('is_private')
                            ->label('Privado')
                            ->helperText(
                                'No se compartirá con futuros colaboradores.',
                            ),
                    ])
                    ->columns(2),

                Section::make('Seguimiento y SLA')
                    ->schema([
                        DateTimePicker::make('detected_at')
                            ->label('Detectado')
                            ->displayFormat('d/m/Y H:i')
                            ->default(now()),

                        DateTimePicker::make('acknowledged_at')
                            ->label('Atendido / reconocido')
                            ->displayFormat('d/m/Y H:i'),

                        DateTimePicker::make('response_due_at')
                            ->label('Límite de respuesta')
                            ->displayFormat('d/m/Y H:i'),

                        DateTimePicker::make('resolution_due_at')
                            ->label('Límite de solución')
                            ->displayFormat('d/m/Y H:i'),

                        TextInput::make('next_action')
                            ->label('Próxima acción')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        DateTimePicker::make('next_action_at')
                            ->label('Próximo seguimiento')
                            ->displayFormat('d/m/Y H:i'),
                    ])
                    ->columns(2),

                Section::make('Resolución')
                    ->schema([
                        DateTimePicker::make('mitigated_at')
                            ->label('Mitigado')
                            ->displayFormat('d/m/Y H:i'),

                        DateTimePicker::make('resolved_at')
                            ->label('Resuelto')
                            ->displayFormat('d/m/Y H:i'),

                        DateTimePicker::make('closed_at')
                            ->label('Cerrado')
                            ->displayFormat('d/m/Y H:i'),

                        Textarea::make('root_cause')
                            ->label('Causa raíz')
                            ->rows(4)
                            ->columnSpanFull(),

                        Textarea::make('resolution_summary')
                            ->label('Resumen de solución')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Origen y evidencia')
                    ->schema([
                        Select::make('source')
                            ->label('Origen')
                            ->options(Incident::sourceOptions())
                            ->default('manual')
                            ->native(false)
                            ->required(),

                        TextInput::make('external_id')
                            ->label('ID externo')
                            ->maxLength(255)
                            ->helperText(
                                'Permite que futuras integraciones actualicen el mismo incidente sin duplicarlo.',
                            ),

                        TextInput::make('external_url')
                            ->label('Enlace de evidencia / monitor')
                            ->url()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('detected_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Incidente')
                    ->description(
                        fn (Incident $record): ?string =>
                            $record->affected_service,
                    )
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('organization.name')
                    ->label('Empresa / ámbito')
                    ->badge()
                    ->sortable(),

                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('severity')
                    ->label('Severidad')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            Incident::severityOptions()[$state]
                                ?? 'Media',
                    )
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'critical' => 'danger',
                            'high' => 'warning',
                            'medium' => 'info',
                            'low' => 'gray',
                            default => 'gray',
                        },
                    ),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            Incident::statusOptions()[$state]
                                ?? 'Nuevo',
                    )
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'resolved',
                            'closed' => 'success',
                            'mitigated',
                            'monitoring' => 'info',
                            'investigating' => 'warning',
                            'cancelled' => 'gray',
                            default => 'danger',
                        },
                    ),

                TextColumn::make('attention_label')
                    ->label('Atención')
                    ->state(
                        fn (Incident $record): string =>
                            $record->attention_label,
                    )
                    ->badge()
                    ->color(
                        fn (Incident $record): string =>
                            $record->attention_color,
                    ),

                TextColumn::make('open_duration_label')
                    ->label('Tiempo abierto')
                    ->state(
                        fn (Incident $record): string =>
                            $record->open_duration_label,
                    ),

                TextColumn::make('detected_at')
                    ->label('Detectado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->label('Empresa / ámbito')
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

                SelectFilter::make('severity')
                    ->label('Severidad')
                    ->options(Incident::severityOptions()),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Incident::statusOptions()),

                SelectFilter::make('source')
                    ->label('Origen')
                    ->options(Incident::sourceOptions()),
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
            ->visibleTo($user)
            ->with([
                'organization',
                'client',
                'serviceOrder',
                'project',
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageIncidents::route('/'),
        ];
    }
}
