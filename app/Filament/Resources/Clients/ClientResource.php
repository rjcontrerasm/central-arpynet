<?php

namespace App\Filament\Resources\Clients;

use App\Filament\Resources\Clients\Pages\ManageClients;
use App\Models\Client;
use BackedEnum;
use Filament\Actions\EditAction;
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

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Clientes';

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clientes';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'clientes';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cliente')
                    ->schema([
                        Select::make('organization_id')
                            ->label('Empresa que atiende')
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
                            ->label('Nombre comercial')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('legal_name')
                            ->label('Razón social')
                            ->maxLength(255),

                        TextInput::make('tax_id')
                            ->label('RUC')
                            ->maxLength(20),

                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Contacto y documentos')
                    ->schema([
                        TextInput::make('contact_name')
                            ->label('Contacto')
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Teléfono')
                            ->maxLength(40),

                        TextInput::make('drive_url')
                            ->label('Carpeta de Google Drive')
                            ->url()
                            ->maxLength(255),

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
                    ->label('Cliente')
                    ->description(
                        fn (Client $record): ?string =>
                            $record->legal_name,
                    )
                    ->searchable()
                    ->sortable(),

                TextColumn::make('organization.name')
                    ->label('Empresa')
                    ->badge()
                    ->sortable(),

                TextColumn::make('tax_id')
                    ->label('RUC')
                    ->placeholder('Sin registrar')
                    ->searchable(),

                TextColumn::make('contact_name')
                    ->label('Contacto')
                    ->placeholder('Sin registrar')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
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
            'index' => ManageClients::route('/'),
        ];
    }
}
