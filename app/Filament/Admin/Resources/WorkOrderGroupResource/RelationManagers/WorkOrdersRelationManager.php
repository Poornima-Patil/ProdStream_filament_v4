<?php

namespace App\Filament\Admin\Resources\WorkOrderGroupResource\RelationManagers;

use App\Models\Bom;
use App\Models\Machine;
use App\Models\Operator;
use App\Models\PartNumber;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Exception;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class WorkOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'workOrders';

    public function form(Schema $schema): Schema
    {
        $user = Auth::user();
        $isAdminOrManager = $user && $user->can('Edit Bom');

        return $schema
            ->components([
                Select::make('part_number_id')
                    ->label('Partnumber')
                    ->options(function () {
                        $factoryId = $this->getOwnerRecord()->factory_id;

                        return PartNumber::where('factory_id', $factoryId)
                            ->get()
                            ->mapWithKeys(function ($partNumber) {
                                return [
                                    $partNumber->id => $partNumber->partnumber.' - '.$partNumber->revision,
                                ];
                            });
                    })
                    ->searchable()
                    ->reactive()
                    ->required()
                    ->preload()
                    ->disabled(! $isAdminOrManager),

                Select::make('bom_id')
                    ->label('BOM')
                    ->options(function (callable $get) {
                        $partNumberId = $get('part_number_id');
                        if (! $partNumberId) {
                            return [];
                        }

                        return Bom::whereHas('purchaseOrder', function ($query) use ($partNumberId) {
                            $query->where('part_number_id', $partNumberId);
                        })->get()->mapWithKeys(function ($bom) {
                            $partNumberDescription = $bom->purchaseOrder->partNumber->description ?? '';

                            return [
                                $bom->id => "BOM ID: {$bom->unique_id} - Part Description: {$partNumberDescription}",
                            ];
                        });
                    })
                    ->required()
                    ->reactive()
                    ->searchable()
                    ->disabled(! $isAdminOrManager),

                Select::make('machine_id')
                    ->label('Machine')
                    ->options(function (callable $get) {
                        $bomId = $get('bom_id');
                        $factoryId = $this->getOwnerRecord()->factory_id;

                        if (! $bomId) {
                            return [];
                        }

                        $bom = Bom::find($bomId);
                        $machineGroupId = $bom?->machine_group_id;

                        $allMachines = Machine::where('factory_id', $factoryId)
                            ->active()
                            ->get();

                        return $allMachines->mapWithKeys(function ($machine) use ($machineGroupId) {
                            $inGroup = $machine->machine_group_id == $machineGroupId;
                            $color = $inGroup ? '🟢' : '🔴';
                            $label = "{$color} Asset ID: {$machine->assetId} - Name: {$machine->name}";

                            return [(int) $machine->id => $label];
                        })->toArray();
                    })
                    ->reactive()
                    ->required()
                    ->searchable()
                    ->disabled(! $isAdminOrManager)
                    ->helperText(function (callable $get) {
                        $machineId = $get('machine_id');
                        $bomId = $get('bom_id');
                        if ($machineId && $bomId) {
                            $bom = Bom::find($bomId);
                            $machine = Machine::find($machineId);
                            if ($bom && $machine && $machine->machine_group_id != $bom->machine_group_id) {
                                return '⚠️ This Machine is not as per BOM Specifications.';
                            }
                        }

                        return null;
                    })
                    ->afterStateUpdated(function (callable $get, callable $set, $state) {
                        $bomId = $get('bom_id');
                        if ($bomId && $state) {
                            $bom = Bom::find($bomId);
                            $machine = Machine::find($state);
                            if ($bom && $machine && $machine->machine_group_id != $bom->machine_group_id) {
                                Notification::make()
                                    ->title('Warning')
                                    ->body('This Machine is not as per BOM Specifications.')
                                    ->warning()
                                    ->send();
                            }
                        }
                    }),

                TextInput::make('qty')
                    ->label('Quantity')
                    ->required()
                    ->disabled(! $isAdminOrManager)
                    ->live(onBlur: true)
                    ->numeric()
                    ->afterStateUpdated(function (callable $get, callable $set) {
                        $partNumberId = $get('part_number_id');
                        $qty = (int) $get('qty');

                        if (! $partNumberId || ! $qty) {
                            $set('time_to_complete', '00:00:00');

                            return;
                        }

                        $bom = Bom::find($get('bom_id'));
                        if ($bom) {
                            $purchaseOrder = $bom->purchaseOrder;
                            if ($purchaseOrder && $qty > $purchaseOrder->QTY) {
                                Notification::make()
                                    ->title('Quantity Exceeded')
                                    ->body("The entered quantity cannot exceed the Sales Order quantity: {$purchaseOrder->QTY}.")
                                    ->danger()
                                    ->send();
                                $set('qty', 0);

                                return;
                            }
                        }

                        $cycleTimeInSeconds = PartNumber::where('id', $partNumberId)->value('cycle_time');
                        if (! $cycleTimeInSeconds) {
                            $set('time_to_complete', '00:00:00');

                            return;
                        }

                        $totalSeconds = $cycleTimeInSeconds * $qty;
                        $hours = floor($totalSeconds / 3600);
                        $minutes = floor(($totalSeconds % 3600) / 60);
                        $seconds = $totalSeconds % 60;
                        $set('time_to_complete', sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds));
                    }),

                Select::make('operator_id')
                    ->label('Operator')
                    ->disabled(! $isAdminOrManager)
                    ->options(function (callable $get) {
                        $bomId = $get('bom_id');
                        if (! $bomId) {
                            return [];
                        }

                        $operatorProficiencyId = Bom::find($bomId)->operator_proficiency_id;
                        $factoryId = $this->getOwnerRecord()->factory_id;

                        return Operator::where('factory_id', $factoryId)
                            ->with(['user', 'shift'])
                            ->get()
                            ->mapWithKeys(function ($operator) use ($operatorProficiencyId) {
                                $shiftInfo = $operator->shift
                                    ? " ({$operator->shift->name}: {$operator->shift->start_time}-{$operator->shift->end_time})"
                                    : '';
                                $isMatch = $operator->operator_proficiency_id == $operatorProficiencyId;
                                $color = $isMatch ? '🟢' : '🔴';
                                $label = "{$color} {$operator->user->first_name} {$operator->user->last_name}{$shiftInfo}";

                                return [$operator->id => $label];
                            });
                    })
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->helperText(function (callable $get) {
                        $operatorId = $get('operator_id');
                        $bomId = $get('bom_id');
                        if ($operatorId && $bomId) {
                            $bom = Bom::find($bomId);
                            $operator = Operator::find($operatorId);
                            if ($bom && $operator && $operator->operator_proficiency_id != $bom->operator_proficiency_id) {
                                return "⚠️ The Operator's Proficiency does not match with the BOM specifications.";
                            }
                        }

                        return null;
                    })
                    ->afterStateUpdated(function (callable $get, callable $set, $state) {
                        $bomId = $get('bom_id');
                        if ($bomId && $state) {
                            $bom = Bom::find($bomId);
                            $operator = Operator::find($state);
                            if ($bom && $operator && $operator->operator_proficiency_id != $bom->operator_proficiency_id) {
                                Notification::make()
                                    ->title('Warning')
                                    ->body("The Operator's Proficiency does not match with the BOM specifications.")
                                    ->warning()
                                    ->send();
                            }
                        }
                    }),

                TextInput::make('time_to_complete')
                    ->label('Approx time required')
                    ->hint('Time is calculated based on the cycle time provided in the Part number')
                    ->visible($isAdminOrManager)
                    ->disabled()
                    ->dehydrated(),

                DateTimePicker::make('start_time')
                    ->required()
                    ->disabled(! $isAdminOrManager)
                    ->label('Planned Start Time')
                    ->minDate(fn (string $operation): ?\Carbon\Carbon => $operation === 'create' ? now()->startOfDay() : null)
                    ->seconds(false)
                    ->native(false)
                    ->displayFormat('d M Y, H:i')
                    ->timezone('Asia/Kolkata')
                    ->live(onBlur: true),

                DateTimePicker::make('end_time')
                    ->label('Planned End Time')
                    ->minDate(fn (string $operation): ?\Carbon\Carbon => $operation === 'create' ? now()->startOfDay() : null)
                    ->disabled(! $isAdminOrManager)
                    ->seconds(false)
                    ->native(false)
                    ->displayFormat('d M Y, H:i')
                    ->timezone('Asia/Kolkata')
                    ->live(onBlur: true)
                    ->required()
                    ->helperText(function (callable $get) {
                        $bomId = $get('bom_id');
                        if (! $bomId) {
                            return null;
                        }
                        $bom = Bom::find($bomId);
                        if ($bom && $bom->lead_time) {
                            return 'BOM Target Completion Time: '.Carbon::parse($bom->lead_time)->format('d M Y');
                        }

                        return null;
                    })
                    ->reactive(),

                // Machine Scheduling Information Section
                Section::make('Machine Scheduling Information')
                    ->schema([
                        Placeholder::make('machine_status')
                            ->label('Current Machine Status')
                            ->content(function (callable $get) {
                                $machineId = $get('machine_id');
                                $startTime = $get('start_time');
                                $endTime = $get('end_time');
                                $status = $get('status');
                                $factoryId = $this->getOwnerRecord()->factory_id;

                                if (! $machineId || ! $factoryId) {
                                    return new HtmlString('<div class="text-gray-500 italic">Select a machine to see status</div>');
                                }

                                try {
                                    $machine = Machine::where('id', $machineId)
                                        ->where('factory_id', $factoryId)
                                        ->first();

                                    if (! $machine) {
                                        return new HtmlString('<div class="text-red-600">⚠️ Machine not found or belongs to different factory</div>');
                                    }

                                    $machineName = "({$machine->assetId} - {$machine->name})";

                                    // Check if machine is currently occupied
                                    if (WorkOrder::isMachineCurrentlyOccupied($machineId, $factoryId)) {
                                        $currentWO = WorkOrder::getCurrentRunningWorkOrder($machineId, $factoryId);
                                        $woLink = '#'; // Can't generate edit link in relation manager context
                                        $estimatedCompletion = Carbon::parse($currentWO->end_time)->format('M d, H:i');

                                        return new HtmlString(
                                            '<div class="bg-red-50 border border-red-200 rounded-lg p-3">'.
                                                '<div class="flex items-center text-red-800 font-semibold mb-2">'.
                                                '<span class="text-lg mr-2">🔴</span>'.
                                                '<span>OCCUPIED '.htmlspecialchars($machineName).'</span>'.
                                                '</div>'.
                                                '<div class="text-red-700 text-sm">'.
                                                'Running WO #'.htmlspecialchars($currentWO->unique_id).'<br>'.
                                                '<span class="text-gray-600">Est. completion:</span> '.htmlspecialchars($estimatedCompletion).
                                                '</div>'.
                                                '</div>'
                                        );
                                    }

                                    // If start and end times are provided, check for scheduling conflicts
                                    if ($startTime && $endTime) {
                                        $validation = WorkOrder::validateScheduling([
                                            'machine_id' => $machineId,
                                            'factory_id' => $factoryId,
                                            'start_time' => $startTime,
                                            'end_time' => $endTime,
                                            'status' => $status,
                                            'id' => $get('id'),
                                        ]);

                                        if (! $validation['is_valid']) {
                                            $conflictCount = count($validation['conflicts']);

                                            return new HtmlString(
                                                '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">'.
                                                    '<div class="flex items-center text-yellow-800 font-semibold">'.
                                                    '<span class="text-lg mr-2">⚠️</span>'.
                                                    '<span>CONFLICTS DETECTED '.htmlspecialchars($machineName).'</span>'.
                                                    '</div>'.
                                                    '<div class="text-yellow-700 text-sm mt-1">'.
                                                    htmlspecialchars($conflictCount).' scheduling conflict(s) found'.
                                                    '</div>'.
                                                    '</div>'
                                            );
                                        }
                                    }

                                    return new HtmlString(
                                        '<div class="bg-green-50 border border-green-200 rounded-lg p-3">'.
                                            '<div class="flex items-center text-green-800 font-semibold">'.
                                            '<span class="text-lg mr-2">🟢</span>'.
                                            '<span>AVAILABLE '.htmlspecialchars($machineName).'</span>'.
                                            '</div>'.
                                            '<div class="text-green-700 text-sm mt-1">'.
                                            'Machine is ready for scheduling'.
                                            '</div>'.
                                            '</div>'
                                    );
                                } catch (Exception $e) {
                                    return new HtmlString(
                                        '<div class="text-red-500 italic">Unable to check machine status<br>'.
                                            '<small>Error: '.htmlspecialchars($e->getMessage()).'</small></div>'
                                    );
                                }
                            })
                            ->live(),
                    ])
                    ->visible($isAdminOrManager)
                    ->collapsible(),

                // Operator Scheduling Information Section
                Section::make('Operator Scheduling Information')
                    ->schema([
                        Placeholder::make('operator_status')
                            ->label('Current Operator Status')
                            ->content(function (callable $get) {
                                $operatorId = $get('operator_id');
                                $startTime = $get('start_time');
                                $endTime = $get('end_time');
                                $status = $get('status');
                                $factoryId = $this->getOwnerRecord()->factory_id;

                                if (! $operatorId || ! $factoryId) {
                                    return new HtmlString('<div class="text-gray-500 italic">Select an operator to see status</div>');
                                }

                                try {
                                    // Get operator details and ensure it belongs to the same factory
                                    $operator = Operator::where('id', $operatorId)
                                        ->where('factory_id', $factoryId)
                                        ->with(['user', 'shift'])
                                        ->first();

                                    if (! $operator) {
                                        return new HtmlString('<div class="text-red-600">⚠️ Operator not found or belongs to different factory</div>');
                                    }

                                    $operatorName = "({$operator->user->first_name} {$operator->user->last_name})";
                                    $shiftInfo = $operator->shift
                                        ? " - Shift: {$operator->shift->name} ({$operator->shift->start_time} - {$operator->shift->end_time})"
                                        : ' - No shift assigned';

                                    // Check if operator is currently occupied
                                    if ($operator->isCurrentlyOccupied($factoryId)) {
                                        $currentWO = $operator->getCurrentRunningWorkOrder($factoryId);
                                        // Don't show as occupied if it's the current work order being edited
                                        if (! $get('id') || $currentWO->id !== $get('id')) {
                                            $woLink = '#'; // Can't generate edit link in relation manager context
                                            $estimatedCompletion = Carbon::parse($currentWO->end_time)->format('M d, H:i');

                                            return new HtmlString(
                                                '<div class="bg-red-50 border border-red-200 rounded-lg p-3">'.
                                                    '<div class="flex items-center text-red-800 font-semibold mb-2">'.
                                                    '<span class="text-lg mr-2">🔴</span>'.
                                                    '<span>OCCUPIED '.htmlspecialchars($operatorName).'</span>'.
                                                    '</div>'.
                                                    '<div class="text-red-700 text-sm">'.
                                                    'Working on WO #'.htmlspecialchars($currentWO->unique_id).'<br>'.
                                                    '<span class="text-gray-600">Est. completion:</span> '.htmlspecialchars($estimatedCompletion).'<br>'.
                                                    '<span class="text-gray-600">'.htmlspecialchars($shiftInfo).'</span>'.
                                                    '</div>'.
                                                    '</div>'
                                            );
                                        }
                                    }

                                    // If start and end times are provided, check for scheduling conflicts
                                    if ($startTime && $endTime) {
                                        $validation = WorkOrder::validateScheduling([
                                            'machine_id' => $get('machine_id'),
                                            'operator_id' => $operatorId,
                                            'factory_id' => $factoryId,
                                            'start_time' => $startTime,
                                            'end_time' => $endTime,
                                            'status' => $status,
                                            'id' => $get('id'), // For edit mode
                                        ]);

                                        // Check for shift conflicts (warnings, not blocking)
                                        if (! empty($validation['shift_conflicts'])) {
                                            $shiftConflict = $validation['shift_conflicts'][0];

                                            return new HtmlString(
                                                '<div class="bg-orange-50 border border-orange-200 rounded-lg p-3">'.
                                                    '<div class="flex items-center text-orange-800 font-semibold mb-2">'.
                                                    '<span class="text-lg mr-2">⚠️</span>'.
                                                    '<span>SHIFT CONFLICT '.htmlspecialchars($operatorName).'</span>'.
                                                    '</div>'.
                                                    '<div class="text-orange-700 text-sm">'.
                                                    htmlspecialchars($shiftConflict['message']).'<br>'.
                                                    '<span class="text-gray-600">'.htmlspecialchars($shiftInfo).'</span><br>'.
                                                    '<span class="text-gray-600 italic">Work order is scheduled outside operator\'s shift hours.</span>'.
                                                    '</div>'.
                                                    '</div>'
                                            );
                                        }

                                        // Check for operator availability conflicts (blocking)
                                        if (! empty($validation['operator_conflicts'])) {
                                            $operatorConflictCount = count(array_filter($validation['operator_conflicts'],
                                                fn ($c) => $c['type'] === 'work_order_conflict'));

                                            if ($operatorConflictCount > 0) {
                                                return new HtmlString(
                                                    '<div class="bg-red-50 border border-red-200 rounded-lg p-3">'.
                                                        '<div class="flex items-center text-red-800 font-semibold mb-2">'.
                                                        '<span class="text-lg mr-2">🚫</span>'.
                                                        '<span>OPERATOR CONFLICTS '.htmlspecialchars($operatorName).'</span>'.
                                                        '</div>'.
                                                        '<div class="text-red-700 text-sm">'.
                                                        htmlspecialchars($operatorConflictCount).' scheduling conflict(s) found<br>'.
                                                        '<span class="text-gray-600">'.htmlspecialchars($shiftInfo).'</span><br>'.
                                                        '<span class="text-gray-600 italic">Operator is already assigned to other work orders during this time.</span>'.
                                                        '</div>'.
                                                        '</div>'
                                                );
                                            }
                                        }
                                    }

                                    return new HtmlString(
                                        '<div class="bg-green-50 border border-green-200 rounded-lg p-3">'.
                                            '<div class="flex items-center text-green-800 font-semibold mb-2">'.
                                            '<span class="text-lg mr-2">🟢</span>'.
                                            '<span>AVAILABLE '.htmlspecialchars($operatorName).'</span>'.
                                            '</div>'.
                                            '<div class="text-green-700 text-sm">'.
                                            'Operator is ready for scheduling<br>'.
                                            '<span class="text-gray-600">'.htmlspecialchars($shiftInfo).'</span>'.
                                            '</div>'.
                                            '</div>'
                                    );
                                } catch (Exception $e) {
                                    Log::error('Operator status check failed: '.$e->getMessage(), [
                                        'exception' => $e,
                                        'operator_id' => $operatorId,
                                        'factory_id' => $factoryId,
                                        'status' => $status,
                                    ]);

                                    return new HtmlString(
                                        '<div class="text-red-500 italic">Unable to check operator status<br>'.
                                            '<small>Error: '.htmlspecialchars($e->getMessage()).'</small></div>'
                                    );
                                }
                            })
                            ->live(),
                    ])
                    ->visible(function (callable $get) use ($isAdminOrManager) {
                        return $get('operator_id') && $isAdminOrManager;
                    })
                    ->collapsible()
                    ->collapsed(),

                TextInput::make('material_batch')
                    ->label('Material Batch ID')
                    ->helperText('Optional during creation, required when starting the work order')
                    ->disabled(fn ($record) => $record && $record->material_batch),

                Select::make('hold_reason_id')
                    ->label('Hold Reason')
                    ->relationship('holdReason', 'description', function ($query) {
                        return $query->where('factory_id', $this->getOwnerRecord()->factory_id);
                    })
                    ->visible(fn ($get) => in_array($get('status'), ['Hold']))
                    ->reactive()
                    ->required(fn ($get) => in_array($get('status'), ['Hold']))
                    ->columnSpanFull(),

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('unique_id')
            ->columns([
                TextColumn::make('unique_id')
                    ->label('Work Order ID')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('bom.purchaseOrder.partnumber.partnumber')
                    ->label('Part Number')
                    ->searchable()
                    ->limit(20),
                TextColumn::make('qty')
                    ->label('Quantity')
                    ->alignCenter(),
                BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'Waiting',
                        'secondary' => 'Assigned',
                        'primary' => 'Start',
                        'warning' => 'Hold',
                        'success' => 'Completed',
                        'danger' => 'Cancelled',
                    ]),
                TextColumn::make('sequence_order')
                    ->label('Sequence')
                    ->alignCenter()
                    ->sortable(),
                BadgeColumn::make('dependency_status')
                    ->label('Dep. Status')
                    ->colors([
                        'secondary' => 'unassigned',
                        'success' => 'ready',
                        'primary' => 'assigned',
                        'danger' => 'blocked',
                    ]),
                ToggleColumn::make('is_dependency_root')
                    ->label('Root')
                    ->alignCenter(),
                TextColumn::make('machine.name')
                    ->label('Machine')
                    ->toggleable(),
                TextColumn::make('operator.user.first_name')
                    ->label('Operator')
                    ->formatStateUsing(fn ($record) => $record->operator?->user ? $record->operator->user->first_name.' '.$record->operator->user->last_name : 'N/A')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Waiting' => 'Waiting',
                        'Assigned' => 'Assigned',
                        'Start' => 'Start',
                        'Hold' => 'Hold',
                        'Completed' => 'Completed',
                        'Cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('dependency_status')
                    ->options([
                        'unassigned' => 'Unassigned',
                        'ready' => 'Ready',
                        'assigned' => 'Assigned',
                        'blocked' => 'Blocked',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Create Work Order')
                    ->mutateFormDataUsing(function (array $data): array {
                        // Generate unique_id using same logic as CreateWorkOrder
                        $currentDate = Carbon::now();
                        $dateFormat = $currentDate->format('mdy'); // MMDDYY format

                        // Get the related Bom's unique_id
                        $bom = Bom::find($data['bom_id']);
                        $bomUniqueId = $bom ? $bom->unique_id : 'UNKNOWN';

                        // Get factory_id from the work order group
                        $factoryId = $this->getOwnerRecord()->factory_id;

                        // Get the latest WorkOrder for this specific factory to determine the next sequential number
                        $lastWorkOrder = WorkOrder::withTrashed()
                            ->where('factory_id', $factoryId) // Filter by factory
                            ->whereDate('created_at', 'like', $currentDate->format('Y-m').'%')
                            ->orderByDesc('unique_id')
                            ->first();

                        // Generate the sequence number (reset to 1 if no record is found for the current month)
                        $sequenceNumber = 1;
                        if ($lastWorkOrder) {
                            // Extract the current sequence number from the last unique_id (WXXXX)
                            $sequenceNumber = (int) substr($lastWorkOrder->unique_id, 1, 4) + 1;
                        }

                        // Pad the sequence number to 4 digits (e.g., 0001, 0002, ...)
                        $sequenceNumber = str_pad($sequenceNumber, 4, '0', STR_PAD_LEFT);

                        // Generate unique_id format: WXXXX_MMDDYY_BOMUNIQUE_ID
                        $data['unique_id'] = 'W'.$sequenceNumber.'_'.$dateFormat.'_'.$bomUniqueId;

                        // Set the work order group relationship and factory
                        $data['work_order_group_id'] = $this->getOwnerRecord()->id;
                        $data['factory_id'] = $this->getOwnerRecord()->factory_id;

                        // Set initial status based on group state and position
                        $group = $this->getOwnerRecord();
                        $existingWorkOrders = $group->workOrders()->count();

                        // If this is the first work order in the group, make it a root
                        if ($existingWorkOrders === 0) {
                            $data['is_dependency_root'] = true;
                            // Root WO should start as Waiting until group is activated
                            $data['status'] = 'Waiting';
                            $data['dependency_status'] = 'ready';
                        } else {
                            // For subsequent work orders, they should always start as Waiting
                            $data['is_dependency_root'] = false;
                            $data['status'] = 'Waiting';
                            $data['dependency_status'] = 'blocked';
                        }

                        // Set default values if not provided
                        if (! isset($data['sequence_order'])) {
                            $data['sequence_order'] = $this->getOwnerRecord()->workOrders()->max('sequence_order') + 1;
                        }

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DissociateAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make(),
                ]),
            ])
            ->defaultSort('sequence_order');
    }
}
