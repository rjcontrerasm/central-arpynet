<?php

namespace App\Filament\Resources\RecurringObligations;

use App\Filament\Resources\RecurringObligations\Pages\ManageRecurringObligations;
use App\Models\RecurringObligation;
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

class RecurringObligationResource extends Resource
{
    protected static ?string $model = RecurringObligation::class;

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel =
        'Obligaciones recurrentes';

    protected static ?string $modelLabel =
        'obligación recurrente';

    protected static ?string $pluralModelLabel =
        'obligaciones recurrentes';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug =
        'obligaciones-recurrentes';

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Obligación')
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

                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('category')
                            ->label('Categoría')
                            ->options(
                                RecurringObligation::categoryOptions(),
                            )
                            ->default('service')
                            ->native(false)
                            ->required(),

                        TextInput::make('provider')
                            ->label('Proveedor / entidad')
                            ->maxLength(255),

                        TextInput::make('reference')
                            ->label('Referencia / suministro / contrato')
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Recurrencia y alertas')
                    ->schema([
                        Select::make('frequency')
                            ->label('Frecuencia')
                            ->options(
                                RecurringObligation::frequencyOptions(),
                            )
                            ->default('monthly')
                            ->native(false)
                            ->required(),

                        DatePicker::make('anchor_date')
                            ->label('Primer vencimiento')
                            ->helperText(
                                'Esta fecha determina el día y ciclo de los siguientes vencimientos.'
                            )
                            ->displayFormat('d/m/Y')
                            ->required(),

                        DatePicker::make('end_date')
                            ->label('Finaliza')
                            ->displayFormat('d/m/Y'),

                        TextInput::make('reminder_days_before')
                            ->label('Avisar con anticipación')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(365)
                            ->suffix('días')
                            ->default(7)
                            ->required(),

                        Toggle::make('is_critical')
                            ->label('Obligación crítica')
                            ->helperText(
                                'Los vencimientos próximos se resaltarán como críticos.'
                            ),

                        Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Importe y documentación')
                    ->schema([
                        TextInput::make('expected_amount')
                            ->label('Monto esperado')
                            ->numeric()
                            ->minValue(0),

                        Select::make('currency')
                            ->label('Moneda')
                            ->options([
                                'PEN' => 'Soles (PEN)',
                                'USD' => 'Dólares (USD)',
                                'EUR' => 'Euros (EUR)',
                            ])
                            ->default('PEN')
                            ->native(false)
                            ->required(),

                        TextInput::make('drive_url')
                            ->label('Carpeta o documento en Drive')
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
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Obligación')
                    ->description(
                        fn (RecurringObligation $record): ?string =>
                            $record->provider,
                    )
                    ->searchable()
                    ->sortable(),

                TextColumn::make('organization.name')
                    ->label('Empresa / ámbito')
                    ->badge(),

                TextColumn::make('category')
                    ->label('Categoría')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            RecurringObligation::categoryOptions()[$state]
                                ?? 'Otro',
                    ),

                TextColumn::make('frequency')
                    ->label('Frecuencia')
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            RecurringObligation::frequencyOptions()[$state]
                                ?? 'Sin definir',
                    ),

                TextColumn::make('expected_amount')
                    ->label('Monto esperado')
                    ->state(
                        fn (RecurringObligation $record): string =>
                            $record->expected_amount === null
                                ? 'Variable'
                                : $record->currency.' '
                                    .number_format(
                                        (float) $record->expected_amount,
                                        2,
                                    ),
                    ),

                IconColumn::make('is_critical')
                    ->label('Crítica')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
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

                SelectFilter::make('category')
                    ->label('Categoría')
                    ->options(
                        RecurringObligation::categoryOptions(),
                    ),

                SelectFilter::make('frequency')
                    ->label('Frecuencia')
                    ->options(
                        RecurringObligation::frequencyOptions(),
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
            return parent::getEloquentQuery()
                ->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->visibleTo($user);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRecurringObligations::route('/'),
        ];
    }
}
