<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Organization;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Equipo';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'equipo';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Usuario')
                    ->description(
                        'El acceso real se determina por su estado y las empresas a las que pertenece.'
                    )
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Correo')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label('Contraseña')
                            ->password()
                            ->revealable()
                            ->required(fn (?User $record): bool => $record === null)
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->minLength(8)
                            ->helperText(
                                'En edición, déjala vacía para conservar la contraseña actual.'
                            ),

                        Toggle::make('is_active')
                            ->label('Usuario activo')
                            ->default(true)
                            ->required()
                            ->helperText(
                                'Un usuario inactivo no puede ingresar a Central.'
                            ),
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
                    ->label('Usuario')
                    ->description(fn (User $record): string => $record->email)
                    ->searchable(['name', 'email'])
                    ->sortable(),

                TextColumn::make('currentOrganization.name')
                    ->label('Empresa actual')
                    ->badge()
                    ->placeholder('Sin empresa'),

                TextColumn::make('organizations_count')
                    ->label('Empresas')
                    ->counts('organizations')
                    ->badge()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos')
                    ->native(false),
            ])
            ->recordActions([
                Action::make('memberships')
                    ->label('Empresas y roles')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->modalHeading(
                        fn (User $record): string => 'Empresas y roles de '.$record->name,
                    )
                    ->modalSubmitActionLabel('Guardar accesos')
                    ->fillForm(
                        fn (User $record): array => [
                            'memberships' => $record->organizations()
                                ->whereIn(
                                    'organizations.id',
                                    static::manageableOrganizationIds(),
                                )
                                ->orderBy('organizations.name')
                                ->get()
                                ->map(
                                    fn (Organization $organization): array => [
                                        'organization_id' => $organization->id,
                                        'role' => $organization->pivot->role,
                                        'is_active' => (bool) $organization->pivot->is_active,
                                        'is_default' => (bool) $organization->pivot->is_default,
                                    ],
                                )
                                ->all(),
                        ],
                    )
                    ->form([
                        Repeater::make('memberships')
                            ->label('Accesos por empresa')
                            ->schema([
                                Select::make('organization_id')
                                    ->label('Empresa o ámbito')
                                    ->options(static::manageableOrganizationOptions())
                                    ->searchable()
                                    ->native(false)
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                                Select::make('role')
                                    ->label('Rol')
                                    ->options(User::organizationRoleOptions())
                                    ->default('member')
                                    ->native(false)
                                    ->required(),

                                Toggle::make('is_active')
                                    ->label('Acceso activo')
                                    ->default(true),

                                Toggle::make('is_default')
                                    ->label('Empresa predeterminada')
                                    ->helperText(
                                        'Si marcas más de una, Central conservará solo la primera.'
                                    ),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar empresa'),
                    ])
                    ->action(
                        function (User $record, array $data): void {
                            $manageableIds = static::manageableOrganizationIds();
                            $selectedDefault = null;
                            $payload = [];

                            foreach ($data['memberships'] ?? [] as $membership) {
                                $organizationId = (int) ($membership['organization_id'] ?? 0);

                                if (! in_array($organizationId, $manageableIds, true)) {
                                    continue;
                                }

                                $isDefault = (bool) ($membership['is_default'] ?? false);

                                if ($isDefault && $selectedDefault === null) {
                                    $selectedDefault = $organizationId;
                                } else {
                                    $isDefault = false;
                                }

                                $payload[$organizationId] = [
                                    'role' => $membership['role'] ?? 'member',
                                    'is_default' => $isDefault,
                                    'is_active' => (bool) ($membership['is_active'] ?? true),
                                ];
                            }

                            foreach ($manageableIds as $organizationId) {
                                $record->organizations()->detach($organizationId);
                            }

                            if ($payload !== []) {
                                $record->organizations()->syncWithoutDetaching($payload);
                            }

                            $activeOrganizationIds = $record->organizations()
                                ->wherePivot('is_active', true)
                                ->where('organizations.is_active', true)
                                ->pluck('organizations.id')
                                ->map(fn ($id): int => (int) $id)
                                ->all();

                            $currentOrganizationId = (int) ($record->current_organization_id ?? 0);

                            if (
                                ! in_array(
                                    $currentOrganizationId,
                                    $activeOrganizationIds,
                                    true,
                                )
                            ) {
                                $record->forceFill([
                                    'current_organization_id' => $selectedDefault
                                        ?? ($activeOrganizationIds[0] ?? null),
                                ])->save();
                            } elseif ($selectedDefault !== null) {
                                $record->forceFill([
                                    'current_organization_id' => $selectedDefault,
                                ])->save();
                            }
                        },
                    ),

                EditAction::make()
                    ->label('Editar'),
            ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canManageTeam() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $organizationIds = static::manageableOrganizationIds();

        if ($organizationIds === []) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->whereHas(
                'organizations',
                fn (Builder $query): Builder => $query
                    ->whereIn('organizations.id', $organizationIds)
                    ->where('organization_user.is_active', true),
            )
            ->distinct();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
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

        return Organization::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
