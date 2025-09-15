<?php

namespace App\Filament\Admin\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Admin\Resources\MachineResource\Pages\ListMachines;
use App\Filament\Admin\Resources\MachineResource\Pages\CreateMachine;
use App\Filament\Admin\Resources\MachineResource\Pages\EditMachine;
use App\Filament\Admin\Resources\MachineResource\Pages\ViewMachine;
use App\Filament\Admin\Resources\MachineResource\Pages;
use App\Models\Machine;
use App\Models\MachineGroup;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
class MachineResource extends Resource
{
    protected static ?string $model = Machine::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog';

    protected static string | \UnitEnum | null $navigationGroup = 'Admin Operations';

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('assetId')->required(),
                TextInput::make('name')
                    ->unique(ignoreRecord: true)->required(),
                Select::make('status')->options([
                    1 => 'Active',
                    0 => 'Inactive',
                ])->default(1)
                    ->required(),

                Select::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name', function ($query) {
                        return $query->where('factory_id', auth()->user()->factory_id);
                    })
                    ->searchable()
                    ->preload()
                    ->default(1)
                    ->required(),
                Select::make('machine_group_id')
                    ->label('Machine Group')
                    ->options(MachineGroup::all()->pluck('group_name', 'id'))
                    ->required()  // You can make this required or optional
                    ->searchable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('assetId')->searchable(),
                TextColumn::make('name')->searchable()->sortable(),
                IconColumn::make('status')
                    ->label('Status')
                    ->boolean(),
                TextColumn::make('department.name')->label('Department Name'),
                TextColumn::make('machineGroup.group_name')  // Display the group name
                    ->label('Machine Group')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                EditAction::make()
                    ->label('Edit'),
                ViewAction::make()
                    ->label('View'),
                Action::make('calendar')
                    ->label('View Schedule')
                    ->icon('heroicon-o-calendar-days')
                    ->color('info')
                    ->url(fn($record) => MachineResource::getUrl('view', ['record' => $record]) . '#machine-schedule-section')
                    ->openUrlInNewTab(false),
            ])
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMachines::route('/'),
            'create' => CreateMachine::route('/create'),
            'edit' => EditMachine::route(path: '/{record}/edit'),
            'view' => ViewMachine::route(path: '/{record}/'),

        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
