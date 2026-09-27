<?php

namespace App\Filament\Resources\WorkTeams;

use App\Filament\Resources\WorkTeams\Pages\ManageWorkTeams;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkTeam;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkTeamResource extends Resource
{
    protected static ?string $model = WorkTeam::class;

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Equipos de trabajo';

    protected static ?string $modelLabel = 'equipo de trabajo';

    protected static ?string $pluralModelLabel = 'equipos de trabajo';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'equipos-trabajo';

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Equipo')
                    ->description(
                        'El equipo es transversal: puede atender tareas de distintas empresas sin otorgar acceso general a esas empresas.'
                    )
                    ->schema([
                        Select::make('home_organization_id')
                            ->label('Empresa base')
                            ->options(static::manageableOrganizationOptions())
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->helperText(
                                'Ejemplo: Administración puede tener como base ARPYNET y atender tareas de PC SOTEC.'
                            ),

                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Equipo activo')
                            ->default(true)
                            ->required(),
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
                    ->label('Equipo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('homeOrganization.name')
                    ->label('Empresa base')
                    ->badge()
                    ->placeholder('Sin empresa base'),

                TextColumn::make('users_count')
                    ->label('Miembros')
                    ->counts('users')
                    ->badge(),

                TextColumn::make('tasks_count')
                    ->label('Tareas')
                    ->counts('tasks')
                    ->badge(),

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
                    ->falseLabel('Solo inactivos'),
            ])
            ->recordActions([
                Action::make('members')
                    ->label('Miembros')
                    ->icon(Heroicon::OutlinedUsers)
                    ->modalHeading(
                        fn (WorkTeam $record): string =>
                            'Miembros de '.$record->name,
                    )
                    ->modalSubmitActionLabel('Guardar miembros')
                    ->fillForm(
                        fn (WorkTeam $record): array => [
                            'memberships' => $record->users()
                                ->orderBy('users.name')
                                ->get()
                                ->map(
                                    fn (User $user): array => [
                                        'user_id' => $user->id,
                                        'role' => $user->pivot->role,
                                        'is_active' => (bool) $user->pivot->is_active,
                                    ],
                                )
                                ->all(),
                        ],
                    )
                    ->schema([
                        Repeater::make('memberships')
                            ->label('Personas del equipo')
                            ->schema([
                                Select::make('user_id')
                                    ->label('Persona')
                                    ->options(
                                        fn (WorkTeam $record): array =>
                                            static::eligibleUserOptions($record),
                                    )
                                    ->searchable()
                                    ->native(false)
                                    ->required()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                                Select::make('role')
                                    ->label('Rol')
                                    ->options(WorkTeam::roleOptions())
                                    ->default('member')
                                    ->native(false)
                                    ->required(),

                                Toggle::make('is_active')
                                    ->label('Activo')
                                    ->default(true),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Agregar persona'),
                    ])
                    ->action(
                        function (WorkTeam $record, array $data): void {
                            abort_unless(
                                static::canManage($record),
                                403,
                            );

                            $eligibleIds = array_map(
                                'intval',
                                array_keys(
                                    static::eligibleUserOptions($record),
                                ),
                            );

                            $payload = [];

                            foreach ($data['memberships'] ?? [] as $membership) {
                                $userId = (int) ($membership['user_id'] ?? 0);

                                if (! in_array($userId, $eligibleIds, true)) {
                                    continue;
                                }

                                $role = (string) ($membership['role'] ?? 'member');

                                if (! array_key_exists($role, WorkTeam::roleOptions())) {
                                    $role = 'member';
                                }

                                $payload[$userId] = [
                                    'role' => $role,
                                    'is_active' => (bool) ($membership['is_active'] ?? true),
                                ];
                            }

                            $record->users()->sync($payload);
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

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit($record): bool
    {
        return $record instanceof WorkTeam
            && static::canManage($record);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if (! $user) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        $manageableIds = $user->manageableOrganizationIds();

        return parent::getEloquentQuery()
            ->where(
                function (Builder $query) use (
                    $user,
                    $manageableIds,
                ): void {
                    if ($manageableIds !== []) {
                        $query->whereIn(
                            'home_organization_id',
                            $manageableIds,
                        );
                    } else {
                        $query->whereRaw('1 = 0');
                    }

                    $query->orWhere(
                        'created_by',
                        $user->id,
                    );
                },
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWorkTeams::route('/'),
        ];
    }

    public static function manageableOrganizationOptions(): array
    {
        $ids = auth()->user()?->manageableOrganizationIds() ?? [];

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

    public static function eligibleUserOptions(
        WorkTeam $record,
    ): array {
        $organizationId =
            (int) $record->home_organization_id;

        if ($organizationId < 1) {
            return [];
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas(
                'organizations',
                fn (Builder $query): Builder => $query
                    ->where(
                        'organizations.id',
                        $organizationId,
                    )
                    ->where(
                        'organization_user.is_active',
                        true,
                    ),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function canManage(
        WorkTeam $team,
    ): bool {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return $user->canManageOrganization(
            (int) $team->home_organization_id,
        ) || (int) $team->created_by === (int) $user->id;
    }
}
