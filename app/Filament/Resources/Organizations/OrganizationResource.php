<?php

namespace App\Filament\Resources\Organizations;

use App\Filament\Resources\Organizations\Pages\ManageOrganizations;
use App\Models\Organization;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string | BackedEnum | null $navigationIcon =
        Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Empresas y ámbitos';

    protected static ?string $modelLabel = 'empresa o ámbito';

    protected static ?string $pluralModelLabel = 'empresas y ámbitos';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $slug = 'empresas';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('slug')
                            ->label('Identificador')
                            ->helperText(
                                'Se utiliza internamente y no debe repetirse.'
                            )
                            ->required()
                            ->alphaDash()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Select::make('category')
                            ->label('Tipo')
                            ->options([
                                'personal' => 'Personal',
                                'employment' => 'Trabajo dependiente',
                                'company' => 'Empresa',
                            ])
                            ->required()
                            ->native(false),

                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Datos legales y configuración')
                    ->schema([
                        TextInput::make('legal_name')
                            ->label('Razón social')
                            ->maxLength(255),

                        TextInput::make('tax_id')
                            ->label('RUC')
                            ->numeric()
                            ->minLength(11)
                            ->maxLength(11)
                            ->helperText(
                                'Para empresas peruanas, ingresa los 11 dígitos.'
                            ),

                        TextInput::make('timezone')
                            ->label('Zona horaria')
                            ->default('America/Lima')
                            ->required()
                            ->maxLength(60),

                        ColorPicker::make('color')
                            ->label('Color de identificación'),

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
                    ->label('Nombre')
                    ->description(
                        fn (Organization $record): ?string =>
                            $record->legal_name,
                    )
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'personal' => 'Personal',
                            'employment' => 'Trabajo',
                            'company' => 'Empresa',
                            default => 'Otro',
                        },
                    )
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'personal' => 'info',
                            'employment' => 'warning',
                            'company' => 'success',
                            default => 'gray',
                        },
                    )
                    ->sortable(),

                TextColumn::make('tax_id')
                    ->label('RUC')
                    ->placeholder('Sin registrar')
                    ->searchable(),

                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),

                TextColumn::make('timezone')
                    ->label('Zona horaria')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Tipo')
                    ->options([
                        'personal' => 'Personal',
                        'employment' => 'Trabajo dependiente',
                        'company' => 'Empresa',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos')
                    ->native(false),
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
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->whereHas(
                'users',
                fn (Builder $query): Builder => $query
                    ->where('users.id', $user->id)
                    ->where('organization_user.is_active', true),
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrganizations::route('/'),
        ];
    }
}
