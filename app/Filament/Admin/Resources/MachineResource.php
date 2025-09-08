<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MachineResource\Pages;
use App\Models\Machine;
use App\Models\MachineGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Enums\ActionsPosition;
class MachineResource extends Resource
{
    protected static ?string $model = Machine::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog';

    protected static ?string $navigationGroup = 'Admin Operations';

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('assetId')->required(),
                Forms\Components\TextInput::make('name')
                    ->unique(ignoreRecord: true)->required(),
                Forms\Components\Select::make('status')->options([
                    1 => 'Active',
                    0 => 'Inactive',
                ])->default(1)
                    ->required(),

                Forms\Components\Select::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name', function ($query) {
                        return $query->where('factory_id', auth()->user()->factory_id);
                    })
                    ->searchable()
                    ->preload()
                    ->default(1)
                    ->required(),
                Forms\Components\Select::make('machine_group_id')
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
                Tables\Columns\TextColumn::make('assetId')->searchable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\IconColumn::make('status')
                    ->label('Status')
                    ->boolean(),
                Tables\Columns\TextColumn::make('department.name')->label('Department Name'),
                Tables\Columns\TextColumn::make('machineGroup.group_name')  // Display the group name
                    ->label('Machine Group')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
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
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListMachines::route('/'),
            'create' => Pages\CreateMachine::route('/create'),
            'edit' => Pages\EditMachine::route(path: '/{record}/edit'),
            'view' => Pages\ViewMachine::route(path: '/{record}/'),

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
