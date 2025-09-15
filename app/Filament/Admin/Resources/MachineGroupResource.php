<?php

namespace App\Filament\Admin\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Admin\Resources\MachineGroupResource\Pages\ListMachineGroups;
use App\Filament\Admin\Resources\MachineGroupResource\Pages\CreateMachineGroup;
use App\Filament\Admin\Resources\MachineGroupResource\Pages\EditMachineGroup;
use App\Filament\Admin\Resources\MachineGroupResource\Pages\ViewMachineGroup;
use App\Filament\Admin\Resources\MachineGroupResource\Pages;
use App\Models\MachineGroup;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class MachineGroupResource extends Resource
{
    protected static ?string $model = MachineGroup::class;

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog';

    protected static string | \UnitEnum | null $navigationGroup = 'Admin Operations';

    // Add this for custom labels and navigation
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('group_name')
                    ->label('Group Name')
                    ->required(),
                Textarea::make('description')
                    ->label('Description')
                    ->required(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('group_name')
                    ->label('Group Name')
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50),

            ])
            ->filters([
                TrashedFilter::make(),
            ])
          ->recordActions([
                ActionGroup::make([
                    EditAction::make()->label('Edit'),
                    ViewAction::make()->label('View'),
                ])
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMachineGroups::route('/'),
            'create' => CreateMachineGroup::route('/create'),
            'edit' => EditMachineGroup::route('/{record}/edit'),
            'view' => ViewMachineGroup::route('/{record}/view'),
        ];
    }
}
