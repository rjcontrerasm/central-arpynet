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
        return $schema->components([
            Section::make('Cliente')
                ->description(
                    'La ficha es maestra y puede compartirse entre empresas. Las asociaciones múltiples se administran desde CENTRAL Front.',
                )
                ->schema([
                    Select::make('organization_id')
                        ->label('Empresa inicial / compatibilidad')
                        ->options(
                            fn (): array =>
                                static::manageableOrganizationOptions(),
                        )
                        ->default(
                            fn (): ?int =>
                                static::defaultManageableOrganizationId(),
                        )
                        ->helperText(
                            'Al crear o cambiar este valor se añade la empresa a la ficha compartida; no elimina asociaciones existentes.',
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
                    ->description(fn (Client $record): ?string => $record->legal_name)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('organizations.name')
                    ->label('Empresas')
                    ->badge(),

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
                SelectFilter::make('organization')
                    ->label('Empresa')
                    ->options(
                        fn (): array =>
                            static::manageableOrganizationOptions(),
                    )
                    ->query(function (Builder $query, array $data): Builder {
                        $organizationId = (int) ($data['value'] ?? 0);

                        return $organizationId > 0
                            ? $query->forOrganization($organizationId)
                            : $query;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->visible(
                        fn (Client $record): bool =>
                            static::canEdit($record),
                    ),
            ]);
    }

    public static function canCreate(): bool
    {
        return static::manageableOrganizationIds() !== [];
    }

    public static function canEdit($record): bool
    {
        $manageableIds = static::manageableOrganizationIds();

        if ($manageableIds === []) {
            return false;
        }

        $linkedIds = $record->organizations()
            ->wherePivot('is_active', true)
            ->where('organizations.is_active', true)
            ->pluck('organizations.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($linkedIds === [] && $record->organization_id) {
            $linkedIds = [(int) $record->organization_id];
        }

        return $linkedIds !== []
            && array_diff($linkedIds, $manageableIds) === [];
    }

    public static function getEloquentQuery(): Builder
    {
        $manageableIds = static::manageableOrganizationIds();

        if ($manageableIds === []) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->whereHas(
                'organizations',
                fn (Builder $query): Builder => $query
                    ->whereIn('organizations.id', $manageableIds)
                    ->where('organizations.is_active', true)
                    ->where('client_organization.is_active', true),
            )
            ->with([
                'organizations' => fn ($query) => $query
                    ->whereIn('organizations.id', $manageableIds)
                    ->where('organizations.is_active', true)
                    ->wherePivot('is_active', true)
                    ->orderBy('organizations.name'),
            ]);
    }

    public static function manageableOrganizationIds(): array
    {
        return auth()->user()?->manageableOrganizationIds() ?? [];
    }

    public static function manageableOrganizationOptions(): array
    {
        $ids = static::manageableOrganizationIds();

        if ($ids === []) {
            return [];
        }

        return auth()->user()
            ->organizations()
            ->whereIn('organizations.id', $ids)
            ->wherePivot('is_active', true)
            ->where('organizations.is_active', true)
            ->orderBy('organizations.name')
            ->pluck('organizations.name', 'organizations.id')
            ->all();
    }

    public static function defaultManageableOrganizationId(): ?int
    {
        $ids = static::manageableOrganizationIds();
        $currentId = (int) (auth()->user()?->current_organization_id ?? 0);

        return in_array($currentId, $ids, true)
            ? $currentId
            : ($ids[0] ?? null);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClients::route('/'),
        ];
    }
}
