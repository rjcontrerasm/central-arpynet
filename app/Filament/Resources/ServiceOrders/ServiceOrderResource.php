<?php

namespace App\Filament\Resources\ServiceOrders;

use App\Filament\Resources\ServiceOrders\Pages\ManageServiceOrders;
use App\Models\Client;
use App\Models\ServiceOrder;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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

class ServiceOrderResource extends Resource
{
    protected static ?string $model = ServiceOrder::class;

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Órdenes y servicios';

    protected static ?string $modelLabel = 'seguimiento';

    protected static ?string $pluralModelLabel = 'órdenes y servicios';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $slug = 'ordenes-servicio';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Seguimiento comercial')
                    ->schema([
                        Select::make('organization_id')
                            ->label('Empresa')
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

                        Select::make('client_id')
                            ->label('Cliente')
                            ->options(function (): array {
                                $user = auth()->user();

                                if (! $user) {
                                    return [];
                                }

                                return Client::query()
                                    ->visibleTo($user)
                                    ->where('is_active', true)
                                    ->with('organization')
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(
                                        fn (Client $client): array => [
                                            $client->id =>
                                                $client->name
                                                .' — '
                                                .$client->organization->name,
                                        ],
                                    )
                                    ->all();
                            })
                            ->searchable()
                            ->native(false)
                            ->required(),

                        TextInput::make('title')
                            ->label('Servicio / asunto')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Select::make('stage')
                            ->label('Etapa')
                            ->options(
                                ServiceOrder::stageOptions(),
                            )
                            ->default('opportunity')
                            ->native(false)
                            ->required(),

                        TextInput::make('next_action')
                            ->label('Próxima acción')
                            ->maxLength(255),

                        DateTimePicker::make('next_action_at')
                            ->label('Fecha de seguimiento')
                            ->displayFormat('d/m/Y H:i'),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Cotización y orden')
                    ->schema([
                        TextInput::make('quotation_number')
                            ->label('N.º de cotización')
                            ->maxLength(80),

                        DatePicker::make('quotation_date')
                            ->label('Fecha de cotización')
                            ->displayFormat('d/m/Y'),

                        TextInput::make('order_number')
                            ->label('N.º de orden')
                            ->maxLength(100),

                        DatePicker::make('order_received_date')
                            ->label('Fecha de recepción de orden')
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('start_date')
                            ->label('Inicio')
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('end_date')
                            ->label('Fin previsto / contractual')
                            ->displayFormat('d/m/Y'),
                    ])
                    ->columns(2),

                Section::make('Monto')
                    ->schema([
                        TextInput::make('amount')
                            ->label('Monto de la operación')
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

                        Toggle::make('includes_tax')
                            ->label('Monto incluye IGV')
                            ->default(true),
                    ])
                    ->columns(3),

                Section::make('Entregable, conformidad y facturación')
                    ->schema([
                        DatePicker::make('report_submitted_date')
                            ->label('Informe presentado')
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('conformity_date')
                            ->label('Conformidad recibida')
                            ->displayFormat('d/m/Y'),

                        TextInput::make('invoice_number')
                            ->label('Factura')
                            ->maxLength(100),

                        DatePicker::make('invoice_date')
                            ->label('Fecha de factura')
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('invoice_due_date')
                            ->label('Vencimiento de factura')
                            ->displayFormat('d/m/Y'),

                        TextInput::make('invoice_amount')
                            ->label('Monto facturado')
                            ->numeric()
                            ->minValue(0),

                        DatePicker::make('paid_date')
                            ->label('Fecha de pago')
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('closed_date')
                            ->label('Fecha de cierre')
                            ->displayFormat('d/m/Y'),
                    ])
                    ->columns(2),

                Section::make('Documentos y notas')
                    ->schema([
                        TextInput::make('drive_url')
                            ->label('Carpeta de Google Drive')
                            ->url()
                            ->maxLength(255),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label('Servicio / asunto')
                    ->description(
                        fn (ServiceOrder $record): ?string =>
                            $record->order_number
                                ? 'OS: '.$record->order_number
                                : (
                                    $record->quotation_number
                                        ? 'Cotización: '
                                            .$record->quotation_number
                                        : null
                                ),
                    )
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('organization.name')
                    ->label('Empresa')
                    ->badge()
                    ->sortable(),

                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('stage')
                    ->label('Etapa')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            ServiceOrder::stageOptions()[$state]
                                ?? 'Sin definir',
                    )
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'opportunity' => 'gray',
                            'quotation' => 'info',
                            'order_received',
                            'execution' => 'warning',
                            'report_submitted',
                            'conformity' => 'primary',
                            'invoiced' => 'warning',
                            'paid',
                            'closed' => 'success',
                            'cancelled' => 'gray',
                            default => 'gray',
                        },
                    ),

                TextColumn::make('attention_label')
                    ->label('Atención')
                    ->state(
                        fn (ServiceOrder $record): string =>
                            $record->attention_label,
                    )
                    ->badge()
                    ->color(
                        fn (ServiceOrder $record): string =>
                            $record->attention_color,
                    ),

                TextColumn::make('next_action_at')
                    ->label('Próximo seguimiento')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Sin fecha')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->state(
                        fn (ServiceOrder $record): string =>
                            $record->amount === null
                                ? 'Sin registrar'
                                : $record->currency.' '
                                    .number_format(
                                        (float) $record->amount,
                                        2,
                                    ),
                    )
                    ->sortable(),

                TextColumn::make('days_in_stage')
                    ->label('Días en etapa')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->state(
                        fn (ServiceOrder $record): int =>
                            $record->days_in_stage,
                    )
                    ->sortable(
                        query: fn (
                            Builder $query,
                            string $direction,
                        ): Builder =>
                            $query->orderBy(
                                'stage_changed_at',
                                $direction === 'asc'
                                    ? 'desc'
                                    : 'asc',
                            ),
                    ),
            ])
            ->filters([
                SelectFilter::make('organization_id')
                    ->label('Empresa')
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

                SelectFilter::make('client_id')
                    ->label('Cliente')
                    ->options(
                        fn (): array => auth()->user()
                            ? Client::query()
                                ->visibleTo(auth()->user())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                            : [],
                    ),

                SelectFilter::make('stage')
                    ->label('Etapa')
                    ->options(
                        ServiceOrder::stageOptions(),
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
            ->visibleTo($user)
            ->with([
                'organization',
                'client',
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageServiceOrders::route('/'),
        ];
    }
}
