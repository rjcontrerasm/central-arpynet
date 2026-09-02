<?php

namespace App\Filament\Resources\Projects;

use App\Filament\Resources\Projects\Pages\ManageProjects;
use App\Models\Project;
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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedFolderOpen;
    protected static ?string $navigationLabel = 'Proyectos';
    protected static ?string $modelLabel = 'proyecto';
    protected static ?string $pluralModelLabel = 'proyectos';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $slug = 'proyectos';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Proyecto')->schema([
                Select::make('organization_id')
                    ->label('Empresa o ámbito')
                    ->options(fn (): array => auth()->user()?->organizations()
                        ->wherePivot('is_active', true)
                        ->orderBy('organizations.name')
                        ->pluck('organizations.name', 'organizations.id')
                        ->all() ?? [])
                    ->default(fn (): ?int => auth()->user()?->current_organization_id)
                    ->searchable()->native(false)->required(),

                TextInput::make('name')->label('Nombre')->required()->maxLength(255)->columnSpanFull(),

                Select::make('type')->label('Tipo')->options(Project::typeOptions())
                    ->default('project')->native(false)->required(),

                Select::make('horizon')->label('Horizonte')->options(Project::horizonOptions())
                    ->default('short')->native(false)
                    ->helperText('Corto: hasta 1 mes. Mediano: 1 a 12 meses. Largo: más de 12 meses.')
                    ->required(),

                Select::make('status')->label('Estado')->options(Project::statusOptions())
                    ->default('planned')->native(false)->required(),

                TextInput::make('next_action')->label('Próxima acción')
                    ->helperText('El siguiente paso concreto para mantener el proyecto en movimiento.')
                    ->maxLength(255)->columnSpanFull(),

                Textarea::make('description')->label('Descripción')->rows(4)->columnSpanFull(),
            ])->columns(2),

            Section::make('Planificación')->schema([
                DatePicker::make('start_date')->label('Fecha de inicio')->displayFormat('d/m/Y'),
                DatePicker::make('target_date')->label('Fecha objetivo')->displayFormat('d/m/Y'),
                TextInput::make('budget')->label('Presupuesto')->numeric()->minValue(0),
                Select::make('currency')->label('Moneda')->options([
                    'PEN' => 'Soles (PEN)', 'USD' => 'Dólares (USD)', 'EUR' => 'Euros (EUR)',
                ])->default('PEN')->native(false)->required(),
                Textarea::make('blockers')->label('Bloqueadores')->rows(3)->columnSpanFull(),
                Textarea::make('notes')->label('Notas')->rows(3)->columnSpanFull(),
                Toggle::make('is_private')->label('Privado')
                    ->helperText('No se compartirá con futuros colaboradores.'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('target_date')->columns([
            TextColumn::make('name')->label('Proyecto')
                ->description(fn (Project $record): ?string => $record->next_action ? 'Siguiente: '.$record->next_action : null)
                ->searchable()->sortable()->wrap(),
            TextColumn::make('organization.name')->label('Empresa / ámbito')->badge()->sortable(),
            TextColumn::make('type')->label('Tipo')->badge()
                ->formatStateUsing(fn (?string $state): string => Project::typeOptions()[$state] ?? 'Proyecto')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('horizon')->label('Horizonte')->badge()
                ->formatStateUsing(fn (?string $state): string => Project::horizonOptions()[$state] ?? 'Sin definir')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('status')->label('Estado')->badge()
                ->formatStateUsing(fn (?string $state): string => Project::statusOptions()[$state] ?? 'Sin definir'),
            TextColumn::make('progress_percent')->label('Avance')
                ->state(fn (Project $record): string => $record->progress_percent.'%'),
            TextColumn::make('target_date')->label('Fecha objetivo')->date('d/m/Y')->placeholder('Sin fecha')->sortable(),
            TextColumn::make('stagnation_label')->label('Movimiento')
                ->state(fn (Project $record): string => $record->stagnation_label)->badge()
                ->color(fn (Project $record): string => match ($record->stagnation_label) {
                    'Estancado' => 'danger', 'Revisar' => 'warning', 'En movimiento' => 'success', default => 'gray',
                }),
        ])->filters([
            SelectFilter::make('organization_id')->label('Empresa o ámbito')
                ->options(fn (): array => auth()->user()?->organizations()
                    ->wherePivot('is_active', true)
                    ->orderBy('organizations.name')
                    ->pluck('organizations.name', 'organizations.id')->all() ?? []),
            SelectFilter::make('type')->label('Tipo')->options(Project::typeOptions()),
            SelectFilter::make('horizon')->label('Horizonte')->options(Project::horizonOptions()),
            SelectFilter::make('status')->label('Estado')->options(Project::statusOptions()),
        ])->recordActions([
            EditAction::make()->label('Editar'),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        if (! $user) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()->visibleTo($user)->withCount([
            'tasks',
            'tasks as completed_tasks_count' => fn (Builder $query) => $query->where('status', 'completed'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageProjects::route('/')];
    }
}
