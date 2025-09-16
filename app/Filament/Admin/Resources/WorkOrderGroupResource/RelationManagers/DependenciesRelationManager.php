<?php

namespace App\Filament\Admin\Resources\WorkOrderGroupResource\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use App\Models\WorkOrder;

class DependenciesRelationManager extends RelationManager
{
    protected static string $relationship = 'dependencies';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('predecessor_work_order_id')
                    ->label('Predecessor Work Order')
                    ->options(function () {
                        // Only show work orders that are already in this group
                        return $this->getOwnerRecord()->workOrders()
                            ->with(['bom.purchaseOrder.partNumber'])
                            ->get()
                            ->mapWithKeys(function ($workOrder) {
                                $partNumber = $workOrder->bom?->purchaseOrder?->partNumber?->partnumber ?? 'Unknown';
                                $label = "{$workOrder->unique_id} - {$partNumber} (Qty: {$workOrder->qty}) [Seq: {$workOrder->sequence_order}]";
                                return [$workOrder->id => $label];
                            });
                    })
                    ->searchable()
                    ->required()
                    ->placeholder('Select the work order that must be completed first')
                    ->helperText('Only work orders already in this group are shown'),
                Select::make('successor_work_order_id')
                    ->label('Successor Work Order')
                    ->options(function () {
                        // Only show work orders that are already in this group
                        return $this->getOwnerRecord()->workOrders()
                            ->with(['bom.purchaseOrder.partNumber'])
                            ->get()
                            ->mapWithKeys(function ($workOrder) {
                                $partNumber = $workOrder->bom?->purchaseOrder?->partNumber?->partnumber ?? 'Unknown';
                                $label = "{$workOrder->unique_id} - {$partNumber} (Qty: {$workOrder->qty}) [Seq: {$workOrder->sequence_order}]";
                                return [$workOrder->id => $label];
                            });
                    })
                    ->searchable()
                    ->required()
                    ->placeholder('Select the work order that depends on the predecessor')
                    ->helperText('Only work orders already in this group are shown'),
                TextInput::make('required_quantity')
                    ->label('Required Quantity')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->helperText('Quantity needed from predecessor to start successor'),
                Select::make('dependency_type')
                    ->options([
                        'quantity_based' => 'Quantity Based',
                        'completion_based' => 'Completion Based',
                    ])
                    ->default('quantity_based')
                    ->required()
                    ->helperText('Quantity based: requires specific quantity. Completion based: requires full completion.'),

                // Work Order Group Settings Section
                Section::make('Work Order Group Settings')
                    ->description('Manage sequence and dependency settings for work orders in this group')
                    ->schema([
                        Select::make('manage_work_order')
                            ->label('Manage Work Order')
                            ->options(function () {
                                // Show all work orders in this group for management
                                return $this->getOwnerRecord()->workOrders()
                                    ->with(['bom.purchaseOrder.partNumber'])
                                    ->get()
                                    ->mapWithKeys(function ($workOrder) {
                                        $partNumber = $workOrder->bom?->purchaseOrder?->partNumber?->partnumber ?? 'Unknown';
                                        $label = "{$workOrder->unique_id} - {$partNumber} [Seq: {$workOrder->sequence_order}]";
                                        return [$workOrder->id => $label];
                                    });
                            })
                            ->searchable()
                            ->reactive()
                            ->placeholder('Select work order to manage')
                            ->helperText('Select a work order to update its sequence or dependency settings')
                            ->afterStateUpdated(function (callable $get, callable $set, $state) {
                                if ($state) {
                                    $workOrder = WorkOrder::find($state);
                                    if ($workOrder) {
                                        $set('sequence_order', $workOrder->sequence_order);
                                        $set('dependency_status', $workOrder->dependency_status);
                                        $set('is_dependency_root', $workOrder->is_dependency_root);
                                    }
                                }
                            }),

                        TextInput::make('sequence_order')
                            ->label('Sequence Order')
                            ->numeric()
                            ->placeholder('Order within the group (1, 2, 3...)')
                            ->helperText('Lower numbers execute first')
                            ->visible(fn (callable $get) => $get('manage_work_order')),

                        Select::make('dependency_status')
                            ->options([
                                'unassigned' => 'Unassigned',
                                'ready' => 'Ready',
                                'assigned' => 'Assigned',
                                'blocked' => 'Blocked',
                            ])
                            ->label('Dependency Status')
                            ->helperText('Current status of this work order in the dependency chain')
                            ->visible(fn (callable $get) => $get('manage_work_order')),

                        Toggle::make('is_dependency_root')
                            ->label('First in dependency chain')
                            ->helperText('Mark as true if this is the first work order that can start without dependencies')
                            ->visible(fn (callable $get) => $get('manage_work_order')),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('predecessor.unique_id')
                    ->label('Predecessor WO')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('successor.unique_id')
                    ->label('Successor WO')
                    ->searchable()
                    ->copyable(),
                BadgeColumn::make('dependency_type')
                    ->label('Type')
                    ->colors([
                        'primary' => 'quantity_based',
                        'secondary' => 'completion_based',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'quantity_based' => 'Quantity',
                        'completion_based' => 'Completion',
                        default => $state,
                    }),
                TextColumn::make('required_quantity')
                    ->label('Required Qty')
                    ->alignCenter(),
                TextColumn::make('satisfaction_progress')
                    ->label('Progress')
                    ->formatStateUsing(fn ($record) => $record->satisfaction_progress . '%')
                    ->alignCenter(),
                IconColumn::make('is_satisfied')
                    ->boolean()
                    ->label('Satisfied')
                    ->alignCenter(),
                TextColumn::make('satisfied_at')
                    ->label('Satisfied At')
                    ->dateTime('M d, H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('dependency_type')
                    ->options([
                        'quantity_based' => 'Quantity Based',
                        'completion_based' => 'Completion Based',
                    ]),
                SelectFilter::make('is_satisfied')
                    ->options([
                        true => 'Satisfied',
                        false => 'Not Satisfied',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['work_order_group_id'] = $this->getOwnerRecord()->id;

                        // Note: Work orders must be created in the group first before they can be used in dependencies

                        // Handle work order management updates
                        if (isset($data['manage_work_order']) && $data['manage_work_order']) {
                            $workOrderToUpdate = WorkOrder::find($data['manage_work_order']);
                            if ($workOrderToUpdate) {
                                $updateData = [];

                                if (isset($data['sequence_order'])) {
                                    $updateData['sequence_order'] = $data['sequence_order'];
                                }

                                if (isset($data['dependency_status'])) {
                                    $updateData['dependency_status'] = $data['dependency_status'];
                                }

                                if (isset($data['is_dependency_root'])) {
                                    $updateData['is_dependency_root'] = $data['is_dependency_root'];
                                }

                                if (!empty($updateData)) {
                                    $workOrderToUpdate->update($updateData);
                                }
                            }
                        }

                        // Remove management fields from dependency data
                        unset($data['manage_work_order'], $data['sequence_order'], $data['dependency_status'], $data['is_dependency_root']);

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('check_satisfaction')
                    ->label('Check')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(fn ($record) => $record->checkSatisfaction())
                    ->visible(fn ($record) => !$record->is_satisfied),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at');
    }
}
