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

                Section::make('Batch Configuration')
                    ->description('Configure batch sizes for work orders in this group')
                    ->components([
                        \Filament\Forms\Components\Placeholder::make('batch_config_info')
                            ->label('Batch Configuration')
                            ->content(function ($record) {
                                if (!$record || !$record->workOrders()->exists()) {
                                    return 'Save the group first and add work orders to configure batch sizes.';
                                }

                                $configurations = $record->getBatchConfigurations();
                                if (empty($configurations)) {
                                    return 'No batch configurations set. Use the edit form to configure batch sizes for each work order.';
                                }

                                $content = '<div class="space-y-2">';
                                foreach ($configurations as $config) {
                                    $content .= '<div class="flex items-center justify-between p-2 bg-gray-50 rounded">';
                                    $content .= '<span class="font-medium">' . $config['work_order_name'] . '</span>';
                                    $content .= '<span class="text-sm text-gray-600">' . $config['batch_size'] . ' units per batch</span>';
                                    $content .= '</div>';
                                }
                                $content .= '</div>';

                                return new \Illuminate\Support\HtmlString($content);
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn ($operation) => $operation === 'view')
                    ->collapsible(),

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
                    ViewAction::make()->label('View')->size('sm'),
                    EditAction::make()->label('Edit')->size('sm'),
                    Action::make('activate')
                        ->label('Activate')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->size('sm')
                        ->visible(fn (WorkOrderGroup $record) => $record->status === 'draft')
                        ->authorize('activate')
                        ->action(function (WorkOrderGroup $record) {
                            // Check if the group can be activated
                            if (!$record->canActivate()) {
                                $errors = $record->getActivationValidationErrors();

                                \Filament\Notifications\Notification::make()
                                    ->title('Cannot Activate WorkOrder Group')
                                    ->body('Dependencies must be defined before activation: ' . implode('; ', $errors))
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                return;
                            }

                            // Update group status to active
                            $updateResult = $record->update(['status' => 'active']);

                            if (!$updateResult) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Activation Failed')
                                    ->body('Failed to activate the WorkOrder Group. Please check dependencies.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            // Initialize work order statuses based on dependencies
                            $record->initializeWorkOrderStatuses();

                            // Show success notification
                            \Filament\Notifications\Notification::make()
                                ->title('Work Order Group Activated')
                                ->body('Work order statuses have been initialized based on dependencies.')
                                ->success()
                                ->send();
                        }),
                ])->size('sm')->tooltip('Action')->dropdownPlacement('right')
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