<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MachineGroupResource\Pages\CreateMachineGroup;
use App\Filament\Admin\Resources\MachineGroupResource\Pages\EditMachineGroup;
use App\Filament\Admin\Resources\MachineGroupResource\Pages\ListMachineGroups;
use App\Filament\Admin\Resources\MachineGroupResource\Pages\ViewMachineGroup;
use App\Models\MachineGroup;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MachineGroupResource extends Resource
{
    protected static ?string $model = MachineGroup::class;

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog';

    protected static string|\UnitEnum|null $navigationGroup = 'Admin Operations';

    // Add this for custom labels and navigation
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('group_name')
                    ->label('Group Name')
                    ->required()
                    ->unique(
                        table: 'machine_groups',
                        column: 'group_name',
                        ignoreRecord: true,
                        modifyRuleUsing: function ($rule) {
                            return $rule->where('factory_id', auth()->user()->factory_id);
                        }
                    ),
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
                    EditAction::make()->label('Edit')->size('sm'),
                    ViewAction::make()->label('View')->size('sm'),
                ])->size('sm')->tooltip('Action')->dropdownPlacement('right'),
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
