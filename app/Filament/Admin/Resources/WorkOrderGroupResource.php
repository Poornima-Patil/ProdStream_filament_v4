<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WorkOrderGroupResource\Pages;
use App\Filament\Admin\Resources\WorkOrderGroupResource\RelationManagers;
use App\Models\WorkOrderGroup;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Enums\RecordActionsPosition;

class WorkOrderGroupResource extends Resource
{
    protected static ?string $model = WorkOrderGroup::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    protected static string | \UnitEnum | null $navigationGroup = 'Process Operations';

    protected static ?string $navigationLabel = 'WO Groups';

    protected static ?string $pluralModelLabel = 'Work Order Groups';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic Information')
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g., ProductX_Assembly_Group'),
                        Textarea::make('description')
                            ->placeholder('Describe the interdependent work orders group')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Planning Details')
                    ->components([
                        Select::make('planner_id')
                            ->relationship('planner', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->first_name . ' ' . $record->last_name)
                            ->default(auth()->id())
                            ->required()
                            ->placeholder('Select planner'),
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'active' => 'Active',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->required(),
                        DateTimePicker::make('planned_start_date')
                            ->label('Planned Start Date'),
                        DateTimePicker::make('planned_completion_date')
                            ->label('Planned Completion Date'),
                    ])->columns(2),

                Section::make('System Fields')
                    ->components([
                        TextInput::make('unique_id')
                            ->disabled()
                            ->placeholder('Auto-generated on save'),
                        DateTimePicker::make('actual_start_date')
                            ->disabled()
                            ->label('Actual Start Date'),
                        DateTimePicker::make('actual_completion_date')
                            ->disabled()
                            ->label('Actual Completion Date'),
                    ])->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unique_id')
                    ->label('Group ID')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'secondary' => 'draft',
                        'primary' => 'active',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ]),
                Tables\Columns\TextColumn::make('planner.first_name')
                    ->label('Planner')
                    ->formatStateUsing(fn ($record) => $record->planner ? $record->planner->first_name . ' ' . $record->planner->last_name : 'N/A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('workOrders')
                    ->label('Work Orders')
                    ->formatStateUsing(fn (WorkOrderGroup $record) => $record->workOrders()->count())
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('progress')
                    ->label('Progress')
                    ->formatStateUsing(fn (WorkOrderGroup $record) => $record->progress_percentage . '%')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('planned_start_date')
                    ->label('Planned Start')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('planned_completion_date')
                    ->label('Planned End')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('planner')
                    ->relationship('planner', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->first_name . ' ' . $record->last_name),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('View'),
                    EditAction::make()->label('Edit'),
                    Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->visible(fn (WorkOrderGroup $record) => $record->status === 'draft')
                        ->authorize('activate')
                        ->action(function (WorkOrderGroup $record) {
                            // Update group status to active
                            $record->update(['status' => 'active']);

                            // Initialize work order statuses based on dependencies
                            $record->initializeWorkOrderStatuses();

                            // Show success notification
                            \Filament\Notifications\Notification::make()
                                ->title('Work Order Group Activated')
                                ->body('Work order statuses have been initialized based on dependencies.')
                                ->success()
                                ->send();
                        }),
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
            RelationManagers\WorkOrdersRelationManager::class,
            RelationManagers\DependenciesRelationManager::class,
            RelationManagers\WorkOrderGroupLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWorkOrderGroups::route('/'),
            'create' => Pages\CreateWorkOrderGroup::route('/create'),
            'edit' => Pages\EditWorkOrderGroup::route('/{record}/edit'),
        ];
    }
}