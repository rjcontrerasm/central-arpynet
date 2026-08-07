<?php

namespace App\Filament\Resources\ObligationOccurrences;

use App\Filament\Resources\ObligationOccurrences\Pages\ManageObligationOccurrences;
use App\Models\ObligationOccurrence;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ObligationOccurrenceResource extends Resource
{
    protected static ?string $model =
        ObligationOccurrence::class;

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedCalendar;

    protected static ?string $navigationLabel = 'Vencimientos';

    protected static ?string $modelLabel = 'vencimiento';

    protected static ?string $pluralModelLabel = 'vencimientos';

    protected static ?string $recordTitleAttribute = 'due_date';

    protected static ?string $slug = 'vencimientos';

    protected static ?int $navigationSort = 61;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Vencimiento')
                    ->schema([
                        TextInput::make('obligation_name')
                            ->label('Obligación')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(
                                fn (
                                    ObligationOccurrence $record,
                                ): ?string =>
                                    $record->obligation?->name,
                            ),

                        DatePicker::make('due_date')
                            ->label('Vencimiento')
                            ->displayFormat('d/m/Y')
                            ->disabled()
                            ->dehydrated(false),

                        Select::make('status')
                            ->label('Estado')
                            ->options(
                                ObligationOccurrence::statusOptions(),
                            )
                            ->native(false)
                            ->required(),

                        TextInput::make('expected_amount')
                            ->label('Monto esperado')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),

                        TextInput::make('actual_amount')
                            ->label('Monto pagado')
                            ->numeric()
                            ->minValue(0),

                        DatePicker::make('paid_date')
                            ->label('Fecha de pago')
                            ->displayFormat('d/m/Y'),

                        TextInput::make('payment_reference')
                            ->label('N.º operación / referencia')
                            ->maxLength(255),

                        TextInput::make('receipt_url')
                            ->label('Comprobante / Drive')
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
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('due_date')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('obligation.name')
                    ->label('Obligación')
                    ->description(
                        fn (ObligationOccurrence $record): ?string =>
                            $record->obligation?->provider,
                    )
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('organization.name')
                    ->label('Empresa / ámbito')
                    ->badge(),

                TextColumn::make('attention_label')
                    ->label('Atención')
                    ->state(
                        fn (ObligationOccurrence $record): string =>
                            $record->attention_label,
                    )
                    ->badge()
                    ->color(
                        fn (ObligationOccurrence $record): string =>
                            $record->attention_color,
                    ),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            ObligationOccurrence::statusOptions()[$state]
                                ?? 'Pendiente',
                    ),

                TextColumn::make('expected_amount')
                    ->label('Esperado')
                    ->state(
                        fn (ObligationOccurrence $record): string =>
                            $record->expected_amount === null
                                ? 'Variable'
                                : $record->currency.' '
                                    .number_format(
                                        (float) $record->expected_amount,
                                        2,
                                    ),
                    ),

                TextColumn::make('actual_amount')
                    ->label('Pagado')
                    ->state(
                        fn (ObligationOccurrence $record): string =>
                            $record->actual_amount === null
                                ? '—'
                                : $record->currency.' '
                                    .number_format(
                                        (float) $record->actual_amount,
                                        2,
                                    ),
                    ),
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

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(
                        ObligationOccurrence::statusOptions(),
                    ),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Registrar / editar'),
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
                'obligation',
                'organization',
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageObligationOccurrences::route('/'),
        ];
    }
}
