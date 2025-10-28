<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WorkOrderResource\Pages\CreateWorkOrder;
use App\Filament\Admin\Resources\WorkOrderResource\Pages\EditWorkOrder;
use App\Filament\Admin\Resources\WorkOrderResource\Pages\ListWorkOrders;
use App\Filament\Admin\Resources\WorkOrderResource\Pages\ViewWorkOrder;
use App\Filament\Admin\Resources\WorkOrderResource\Widgets\SimpleWorkOrderGantt;
use App\Filament\Admin\Resources\WorkOrderResource\Widgets\WorkOrderProgress;
use App\Models\Bom;
use App\Models\InfoMessage;
use App\Models\Machine;
use App\Models\Operator;
use App\Models\PartNumber;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Closure;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class WorkOrderResource extends Resource
{
    protected static ?string $model = WorkOrder::class;

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Process Operations';

    public static function form(Schema $schema): Schema
    {

        $user = Auth::user();
        $isAdminOrManager = $user && $user->can(abilities: 'Edit Bom');        // dd($isAdminOrManager);

        return $schema
            ->components([
                Select::make('part_number_id')
                    ->label('Partnumber')
                    ->options(function () {
                        $factoryId = Auth::user()->factory_id; // Get the factory ID of the logged-in user

                        // Query the PartNumber model and include the partnumber and revision
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
                    ->disabled(! $isAdminOrManager)
                    ->formatStateUsing(function ($record) {
                        if ($record && $record->bom) {
                            return $record->bom->purchaseOrder->partnumber->id ?? null;
                        }

                        return null; // Return null if the part number doesn't exist
                    }),
                Select::make('bom_id')
                    ->label('BOM')
                    ->options(function (callable $get) {
                        $partNumberId = $get('part_number_id'); // Get the selected Part Number ID
                        if (! $partNumberId) {
                            return []; // No Part Number selected, return empty options
                        }

                        // Query BOMs through the Purchase Order link and include Part Number description
                        return Bom::whereHas('purchaseOrder', function ($query) use ($partNumberId) {
                            $query->where('part_number_id', $partNumberId);
                        })->get()->mapWithKeys(function ($bom) {
                            $partNumberDescription = $bom->purchaseOrder->partNumber->description ?? ''; // Assuming PartNumber has a 'description' field

                            return [
                                $bom->id => "BOM ID: {$bom->unique_id} - Part Description: {$partNumberDescription}",
                            ];
                        });
                    })
                    ->required()
                    ->reactive()
                    ->searchable()
                    ->disabled(! $isAdminOrManager),

                // ...existing code...
                Select::make('machine_id')
                    ->label('Machine')
                    ->options(function (callable $get) {
                        $bomId = $get('bom_id');
                        $user = Auth::user();

                        if (! $bomId) {
                            return [];
                        }

                        $bom = Bom::find($bomId);
                        $machineGroupId = $bom?->machine_group_id;

                        // Fetch all active machines in the factory
                        $allMachines = Machine::where('factory_id', $user->factory_id)
                            ->active()
                            ->get();

                        // Mark machines in the group as green, others as red
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
                    ->rules([
                        function (callable $get) {
                            return function (string $attribute, $value, Closure $fail) use ($get) {
                                $bomId = $get('bom_id');
                                if ($bomId && $value) {
                                    $bom = Bom::find($bomId);
                                    $machine = Machine::find($value);
                                    if ($bom && $machine && $machine->machine_group_id != $bom->machine_group_id) {
                                        $fail('This Machine is not as per BOM Specifications.');
                                    }
                                }
                                // ...existing validation logic for scheduling...
                                $startTime = $get('start_time');
                                $endTime = $get('end_time');
                                if ($value && $startTime && $endTime) {
                                    $errorMessage = self::getSchedulingValidationError($get, $value, $startTime, $endTime);
                                    if ($errorMessage) {
                                        $fail($errorMessage);
                                    }
                                }
                            };
                        },
                    ])
                    ->afterStateUpdated(function (callable $get, callable $set, $state) {
                        // Show warning if machine is not as per BOM
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
                        // Only validate if all required fields are filled and this is not the initial load
                        if ($state && $get('start_time') && $get('end_time')) {
                            self::validateMachineScheduling($get, $set);
                        }
                    }),

                TextInput::make('qty')
                    ->label('Quantity')
                    ->required()
                    ->disabled(! $isAdminOrManager)
                    ->live(onBlur: true)
                    ->numeric()
                    ->afterStateUpdated(function (callable $get, callable $set) {
                        $partNumberId = $get('part_number_id'); // Get selected Part Number ID
                        $qty = (int) $get('qty'); // Get entered quantity

                        if (! $partNumberId || ! $qty) {
                            $set('time_to_complete', '00:00:00'); // Reset if no part number or qty

                            return;
                        }

                        // Get the BOM associated with this Work Order
                        $bom = Bom::find($get('bom_id'));

                        if ($bom) {
                            // Get the related Purchase Order and its quantity
                            $purchaseOrder = $bom->purchaseOrder;

                            // Check if the entered quantity exceeds the Purchase Order quantity
                            if ($purchaseOrder && $qty > $purchaseOrder->QTY) {
                                Notification::make()
                                    ->title('Quantity Exceeded')
                                    ->body("The entered quantity cannot exceed the Sales Order quantity: {$purchaseOrder->QTY}.")
                                    ->danger() // Red notification
                                    ->send();

                                // Reset the qty field to 0
                                $set('qty', 0);

                                return;
                            }
                        }

                        // Get the cycle time from the part number
                        $cycleTimeInSeconds = PartNumber::where('id', $partNumberId)->value('cycle_time');

                        if (! $cycleTimeInSeconds) {
                            $set('time_to_complete', '00:00:00'); // Default if no cycle time is set

                            return;
                        }

                        // Calculate total time
                        $totalSeconds = $cycleTimeInSeconds * $qty;
                        $set('time_to_complete', self::convertSecondsToTime($totalSeconds));
                    }),
                Select::make('operator_id')
                    ->label('Operator')
                    ->disabled(! $isAdminOrManager)
                    ->options(function (callable $get) {
                        $bomId = $get('bom_id'); // Get selected BOM ID

                        if (! $bomId) {
                            return []; // No BOM selected, return empty options
                        }

                        // Get the operator proficiency ID from the BOM's linked Purchase Order
                        $operatorProficiencyId = Bom::find($bomId)->operator_proficiency_id;

                        // Get all operators for the factory
                        $factoryId = Auth::user()->factory_id; // Get the logged-in user's factory_id

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
                                return "⚠️ The Operator's Profiency does not match with the BOM specifications.";
                            }
                        }
                        // ...existing helperText logic for shift conflicts...
                        $startTime = $get('start_time');
                        $endTime = $get('end_time');
                        $factoryId = Auth::user()->factory_id ?? null;

                        if (! $operatorId || ! $startTime || ! $endTime || ! $factoryId) {
                            return null;
                        }

                        try {
                            $validation = WorkOrder::validateScheduling([
                                'machine_id' => $get('machine_id'),
                                'operator_id' => $operatorId,
                                'factory_id' => $factoryId,
                                'start_time' => $startTime,
                                'end_time' => $endTime,
                                'status' => $get('status'),
                                'id' => $get('id'),
                            ]);

                            // Check for shift conflicts and show prominent warning
                            if (! empty($validation['shift_conflicts'])) {
                                $shiftConflict = $validation['shift_conflicts'][0];

                                return '⚠️ SHIFT CONFLICT: '.$shiftConflict['message'];
                            }
                        } catch (Exception $e) {
                            return null;
                        }

                        return null;
                    })
                    ->rules([
                        function (callable $get) {
                            return function (string $attribute, $value, Closure $fail) use ($get) {
                                $bomId = $get('bom_id');
                                if ($bomId && $value) {
                                    $bom = Bom::find($bomId);
                                    $operator = Operator::find($value);
                                    if ($bom && $operator && $operator->operator_proficiency_id != $bom->operator_proficiency_id) {
                                        $fail("The Operator's Profiency does not match with the BOM specifications.");
                                    }
                                }
                                $startTime = $get('start_time');
                                $endTime = $get('end_time');

                                if ($value && $startTime && $endTime) {
                                    $errorMessage = self::getOperatorSchedulingValidationError($get, $value, $startTime, $endTime);
                                    if ($errorMessage) {
                                        $fail($errorMessage);
                                    }
                                }
                            };
                        },
                    ])
                    ->afterStateUpdated(function (callable $get, callable $set, $state) {
                        // Only show warning if proficiency does not match
                        $bomId = $get('bom_id');
                        if ($bomId && $state) {
                            $bom = Bom::find($bomId);
                            $operator = Operator::find($state);
                            if ($bom && $operator && $operator->operator_proficiency_id != $bom->operator_proficiency_id) {
                                Notification::make()
                                    ->title('Warning')
                                    ->body("The Operator's Profiency does not match with the BOM specifications.")
                                    ->warning()
                                    ->send();
                            }
                        }
                        // Only validate if all required fields are filled and this is not the initial load
                        if ($state && $get('start_time') && $get('end_time')) {
                            self::validateOperatorScheduling($get, $set);
                        }
                    }),
                TextInput::make('time_to_complete')
                    ->label('Approx time required')
                    ->hint('Time is calculated based on the cycle time provided in the Part number')
                    ->visible($isAdminOrManager)
                    ->disabled()
                    ->dehydrated()
                    ->afterStateHydrated(function ($component, $state, $record) {
                        if ($record) {
                            $partNumberId = $record->bom->purchaseOrder->part_number_id;
                            $qty = $record->qty;

                            if ($partNumberId && $qty) {
                                $cycleTimeInSeconds = PartNumber::where('id', $partNumberId)->value('cycle_time');
                                if ($cycleTimeInSeconds) {
                                    $totalSeconds = $cycleTimeInSeconds * $qty;
                                    $component->state(self::convertSecondsToTime($totalSeconds));
                                }
                            }
                        }
                    })
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        $partNumberId = $get('part_number_id');
                        $qty = (int) $get('qty');

                        if (! $partNumberId || ! $qty) {
                            $set('time_to_complete', '00:00:00');

                            return;
                        }

                        // Get the cycle time from the part number
                        $cycleTimeInSeconds = PartNumber::where('id', $partNumberId)->value('cycle_time');

                        if (! $cycleTimeInSeconds) {
                            $set('time_to_complete', '00:00:00');

                            return;
                        }

                        // Calculate total time
                        $totalSeconds = $cycleTimeInSeconds * $qty;
                        $set('time_to_complete', self::convertSecondsToTime($totalSeconds));
                    }),

                DateTimePicker::make('start_time')
                    ->required()
                    ->disabled(! $isAdminOrManager)
                    ->label('Planned Start Time')
                    ->minDate(fn (string $operation): ?\Carbon\Carbon => $operation === 'create' ? now()->startOfDay() : null)
                    ->seconds(false)
                    ->native(false)
                    ->displayFormat('d-m-Y H:i')
                    ->timezone(config('app.timezone'))
                    ->live(onBlur: true)
                    ->rules([
                        function (callable $get) {
                            return function (string $attribute, $value, Closure $fail) use ($get) {
                                $machineId = $get('machine_id');
                                $endTime = $get('end_time');

                                if ($machineId && $value && $endTime) {
                                    $errorMessage = self::getSchedulingValidationError($get, $machineId, $value, $endTime);
                                    if ($errorMessage) {
                                        $fail($errorMessage);
                                    }
                                }
                            };
                        },
                    ])
                    ->afterStateUpdated(function (callable $get, callable $set, $state) {
                        // Validate if all required fields are filled
                        if ($state && $get('machine_id') && $get('end_time')) {
                            self::validateMachineScheduling($get, $set);
                        }
                    }),

                TimePicker::make('delay_time')
                    ->disabled(! $isAdminOrManager)
                    ->label('Acceptable Delay time')
                    ->default('00:00')
                    ->seconds(false)
                    ->native(false)
                    ->displayFormat('H:i')
                    ->timezone(config('app.timezone'))
                    ->live(onBlur: true)
                    ->helperText('Set the acceptable delay time for this work order.'),

                DateTimePicker::make('end_time')
                    ->required()
                    ->disabled(! $isAdminOrManager)
                    ->label('Planned End Time')
                    ->minDate(fn (string $operation): ?\Carbon\Carbon => $operation === 'create' ? now()->startOfDay() : null)
                    ->seconds(false)
                    ->native(false)
                    ->displayFormat('d-m-Y H:i')
                    ->timezone(config('app.timezone'))
                    ->live(onBlur: true)
                    ->rules([
                        function (callable $get) {
                            return function (string $attribute, $value, Closure $fail) use ($get) {
                                $machineId = $get('machine_id');
                                $startTime = $get('start_time');

                                if ($machineId && $startTime && $value) {
                                    $errorMessage = self::getSchedulingValidationError($get, $machineId, $startTime, $value);
                                    if ($errorMessage) {
                                        $fail($errorMessage);
                                    }
                                }
                            };
                        },
                    ])
                    ->afterStateUpdated(function (callable $get, callable $set, $state) {
                        // Validate if all required fields are filled
                        if ($state && $get('machine_id') && $get('start_time')) {
                            self::validateMachineScheduling($get, $set);
                        }
                    })
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
                    }),

                // Machine Status Information Section
                Section::make('Machine Scheduling Information')
                    ->schema([
                        Placeholder::make('machine_status')
                            ->label('Current Machine Status')
                            ->content(function (callable $get) {
                                $machineId = $get('machine_id');
                                $startTime = $get('start_time');
                                $endTime = $get('end_time');
                                $status = $get('status');
                                $factoryId = Auth::user()->factory_id ?? null;

                                if (! $machineId || ! $factoryId) {
                                    return new HtmlString('<div class="text-gray-500 italic">Select a machine to see status</div>');
                                }

                                try {
                                    // Get machine details and ensure it belongs to the same factory
                                    $machine = Machine::where('id', $machineId)
                                        ->where('factory_id', $factoryId)
                                        ->first();

                                    if (! $machine) {
                                        return new HtmlString('<div class="text-red-600">⚠️ Machine not found or belongs to different factory</div>');
                                    }

                                    $machineName = "({$machine->assetId} - {$machine->name})";

                                    // Check if user is trying to start this work order
                                    if ($status === 'Start') {
                                        $startValidation = WorkOrder::validateStartStatusTransition($machineId, $factoryId, $get('id'));
                                        if (! $startValidation['can_start']) {
                                            $conflictingWO = $startValidation['conflicting_work_order'];
                                            $operatorName = $conflictingWO->operator?->user
                                                ? "{$conflictingWO->operator->user->first_name} {$conflictingWO->operator->user->last_name}"
                                                : 'Unknown';
                                            $estimatedCompletion = $conflictingWO->end_time
                                                ? Carbon::parse($conflictingWO->end_time)->format('M d, H:i')
                                                : 'Unknown';

                                            // Generate the edit URL for the conflicting work order
                                            $woLink = url("/admin/{$factoryId}/work-orders/{$conflictingWO->id}");

                                            // Determine if it's a running conflict or scheduled conflict
                                            if ($conflictingWO->status === 'Start') {
                                                // Work order is actually running
                                                return new HtmlString(
                                                    '<div class="bg-red-50 border border-red-200 rounded-lg p-3">'.
                                                        '<div class="flex items-center text-red-800 font-semibold mb-2">'.
                                                        '<span class="text-lg mr-2">🔴</span>'.
                                                        '<span>CANNOT START '.htmlspecialchars($machineName).'</span>'.
                                                        '</div>'.
                                                        '<div class="text-red-700 text-sm">'.
                                                        'Already running <a href="'.$woLink.'" class="font-medium text-red-600 underline hover:text-red-800" target="_blank">WO #'.htmlspecialchars($conflictingWO->unique_id).'</a><br>'.
                                                        '<span class="text-gray-600">Operator:</span> '.htmlspecialchars($operatorName).'<br>'.
                                                        '<span class="text-gray-600">Est. completion:</span> '.htmlspecialchars($estimatedCompletion).'<br>'.
                                                        '<span class="text-gray-600 italic">Complete or hold the running work order first.</span>'.
                                                        '</div>'.
                                                        '</div>'
                                                );
                                            } else {
                                                // Work order is scheduled (planned conflict)
                                                $scheduledStart = Carbon::parse($conflictingWO->start_time)->format('M d, H:i');
                                                $scheduledEnd = Carbon::parse($conflictingWO->end_time)->format('M d, H:i');

                                                return new HtmlString(
                                                    '<div class="bg-orange-50 border border-orange-200 rounded-lg p-3">'.
                                                        '<div class="flex items-center text-orange-800 font-semibold mb-2">'.
                                                        '<span class="text-lg mr-2">⏰</span>'.
                                                        '<span>SCHEDULED CONFLICT '.htmlspecialchars($machineName).'</span>'.
                                                        '</div>'.
                                                        '<div class="text-orange-700 text-sm">'.
                                                        'Conflicts with planned <a href="'.$woLink.'" class="font-medium text-orange-600 underline hover:text-orange-800" target="_blank">WO #'.htmlspecialchars($conflictingWO->unique_id).'</a><br>'.
                                                        '<span class="text-gray-600">Operator:</span> '.htmlspecialchars($operatorName).'<br>'.
                                                        '<span class="text-gray-600">Planned:</span> '.htmlspecialchars($scheduledStart).' - '.htmlspecialchars($scheduledEnd).'<br>'.
                                                        '<span class="text-gray-600 italic">This work order is scheduled to run during this time slot.</span>'.
                                                        '</div>'.
                                                        '</div>'
                                                );
                                            }
                                        }
                                    }

                                    // Check if machine is currently occupied
                                    if (WorkOrder::isMachineCurrentlyOccupied($machineId, $factoryId)) {
                                        $currentWO = WorkOrder::getCurrentRunningWorkOrder($machineId, $factoryId);
                                        // Don't show as occupied if it's the current work order being edited
                                        if (! $get('id') || $currentWO->id !== $get('id')) {
                                            $woLink = url("/admin/{$factoryId}/work-orders/{$currentWO->id}");
                                            $estimatedCompletion = Carbon::parse($currentWO->end_time)->format('M d, H:i');

                                            return new HtmlString(
                                                '<div class="bg-red-50 border border-red-200 rounded-lg p-3">'.
                                                    '<div class="flex items-center text-red-800 font-semibold mb-2">'.
                                                    '<span class="text-lg mr-2">🔴</span>'.
                                                    '<span>OCCUPIED '.htmlspecialchars($machineName).'</span>'.
                                                    '</div>'.
                                                    '<div class="text-red-700 text-sm">'.
                                                    'Running <a href="'.$woLink.'" class="font-medium text-red-600 underline hover:text-red-800" target="_blank">WO #'.htmlspecialchars($currentWO->unique_id).'</a><br>'.
                                                    '<span class="text-gray-600">Est. completion:</span> '.htmlspecialchars($estimatedCompletion).
                                                    '</div>'.
                                                    '</div>'
                                            );
                                        }
                                    }

                                    // If start and end times are provided, check for scheduling conflicts
                                    if ($startTime && $endTime) {
                                        $validation = WorkOrder::validateScheduling([
                                            'machine_id' => $machineId,
                                            'factory_id' => $factoryId,
                                            'start_time' => $startTime,
                                            'end_time' => $endTime,
                                            'status' => $status,
                                            'id' => $get('id'), // For edit mode
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
                                    // Log the actual error for debugging
                                    Log::error('Machine status check failed: '.$e->getMessage(), [
                                        'exception' => $e,
                                        'machine_id' => $machineId,
                                        'factory_id' => $factoryId,
                                        'status' => $status,
                                    ]);

                                    return new HtmlString(
                                        '<div class="text-red-500 italic">Unable to check machine status<br>'.
                                            '<small>Error: '.htmlspecialchars($e->getMessage()).'</small></div>'
                                    );
                                }
                            })
                            ->live(),
                        Placeholder::make('upcoming_schedule')
                            ->label('Relevant Schedule')
                            ->content(function (callable $get) {
                                $machineId = $get('machine_id');
                                $startTime = $get('start_time');
                                $endTime = $get('end_time');
                                $factoryId = Auth::user()->factory_id ?? null;

                                if (! $machineId || ! $factoryId) {
                                    return new HtmlString('<div class="text-gray-500 italic">Select a machine to see relevant schedule</div>');
                                }

                                try {
                                    $schedule = [];

                                    // If user has entered scheduling times, show only relevant work orders
                                    if ($startTime && $endTime) {
                                        $newStart = Carbon::parse($startTime);
                                        $newEnd = Carbon::parse($endTime);

                                        // Get ALL work orders for this machine to check for conflicts and same-date items
                                        $allWOs = WorkOrder::where('machine_id', $machineId)
                                            ->where('factory_id', $factoryId)
                                            ->where('status', 'Assigned')
                                            ->orderBy('start_time')
                                            ->get();

                                        foreach ($allWOs as $wo) {
                                            $woStart = Carbon::parse($wo->start_time);
                                            $woEnd = Carbon::parse($wo->end_time);

                                            $includeInList = false;
                                            $icon = '📅';
                                            $status = '';
                                            $bgColor = 'bg-blue-50 border-blue-200';
                                            $textColor = 'text-blue-800';

                                            // Check if this WO conflicts (overlaps) with the new scheduling
                                            if ($newStart < $woEnd && $newEnd > $woStart) {
                                                $icon = '⚠️';
                                                $status = 'CONFLICT';
                                                $bgColor = 'bg-red-50 border-red-200';
                                                $textColor = 'text-red-800';
                                                $includeInList = true;
                                            }
                                            // Check if it's on the same date but doesn't conflict
                                            elseif ($woStart->isSameDay($newStart)) {
                                                $icon = '📍';
                                                $status = 'Same Day';
                                                $bgColor = 'bg-yellow-50 border-yellow-200';
                                                $textColor = 'text-yellow-800';
                                                $includeInList = true;
                                            }

                                            // Only add to schedule if it meets our criteria
                                            if ($includeInList) {
                                                $woLink = url("/admin/{$factoryId}/work-orders/{$wo->id}");
                                                $schedule[] = [
                                                    'work_order' => $wo,
                                                    'link' => $woLink,
                                                    'icon' => $icon,
                                                    'status' => $status,
                                                    'bg_color' => $bgColor,
                                                    'text_color' => $textColor,
                                                    'start_formatted' => $woStart->format('M d, H:i'),
                                                    'end_formatted' => $woEnd->format('H:i'),
                                                ];
                                            }
                                        }
                                    } else {
                                        // If no scheduling times entered, show next few upcoming work orders
                                        $upcomingWOs = WorkOrder::where('machine_id', $machineId)
                                            ->where('factory_id', $factoryId)
                                            ->where('status', 'Assigned')
                                            ->where('start_time', '>', now())
                                            ->orderBy('start_time')
                                            ->limit(3)
                                            ->get();

                                        foreach ($upcomingWOs as $wo) {
                                            $woLink = url("/admin/{$factoryId}/work-orders/{$wo->id}");
                                            $schedule[] = [
                                                'work_order' => $wo,
                                                'link' => $woLink,
                                                'icon' => '📅',
                                                'status' => 'Upcoming',
                                                'bg_color' => 'bg-blue-50 border-blue-200',
                                                'text_color' => 'text-blue-800',
                                                'start_formatted' => Carbon::parse($wo->start_time)->format('M d, H:i'),
                                                'end_formatted' => Carbon::parse($wo->end_time)->format('H:i'),
                                            ];
                                        }
                                    }

                                    if (empty($schedule)) {
                                        $message = $startTime && $endTime ?
                                            'No conflicting or same-day work orders found' :
                                            'No upcoming scheduled work orders';

                                        return new HtmlString(
                                            '<div class="bg-green-50 border border-green-200 rounded-lg p-3">'.
                                                '<div class="flex items-center text-green-800">'.
                                                '<span class="text-lg mr-2">✅</span>'.
                                                '<span>'.htmlspecialchars($message).'</span>'.
                                                '</div>'.
                                                '</div>'
                                        );
                                    }

                                    // Build the HTML content
                                    $content = '<div class="space-y-2">';
                                    foreach ($schedule as $item) {
                                        $operatorName = $item['work_order']->operator?->user
                                            ? "{$item['work_order']->operator->user->first_name} {$item['work_order']->operator->user->last_name}"
                                            : 'Unassigned';

                                        $content .= '<div class="'.$item['bg_color'].' border rounded-lg p-3">'.
                                            '<div class="flex items-center justify-between mb-2">'.
                                            '<div class="flex items-center">'.
                                            '<span class="text-lg mr-2">'.$item['icon'].'</span>'.
                                            '<a href="'.$item['link'].'" class="font-medium '.$item['text_color'].' underline hover:opacity-80" target="_blank">'.
                                            'WO #'.htmlspecialchars($item['work_order']->unique_id).'</a>'.
                                            '</div>'.
                                            '<span class="px-2 py-1 text-xs rounded-full bg-white '.$item['text_color'].' font-medium">'.
                                            htmlspecialchars($item['status']).'</span>'.
                                            '</div>'.
                                            '<div class="text-sm '.$item['text_color'].'">'.
                                            '<div><span class="font-medium">Time:</span> '.htmlspecialchars($item['start_formatted']).' - '.htmlspecialchars($item['end_formatted']).'</div>'.
                                            '<div><span class="font-medium">Operator:</span> '.htmlspecialchars($operatorName).'</div>'.
                                            '</div>'.
                                            '</div>';
                                    }
                                    $content .= '</div>';

                                    return new HtmlString($content);
                                } catch (Exception $e) {
                                    // Log the actual error for debugging
                                    Log::error('Schedule information failed: '.$e->getMessage(), [
                                        'exception' => $e,
                                        'machine_id' => $machineId,
                                        'factory_id' => $factoryId,
                                        'start_time' => $startTime,
                                        'end_time' => $endTime,
                                    ]);

                                    return new HtmlString(
                                        '<div class="text-red-500 italic">Unable to load schedule information<br>'.
                                            '<small>Error: '.htmlspecialchars($e->getMessage()).'</small></div>'
                                    );
                                }
                            })
                            ->live(),
                    ])
                    ->visible(function (callable $get) {
                        return $get('machine_id') && Auth::user()->factory_id ?? false;
                    })
                    ->collapsible()
                    ->collapsed(),

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
                                $factoryId = Auth::user()->factory_id ?? null;

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
                                            $woLink = url("/admin/{$factoryId}/work-orders/{$currentWO->id}");
                                            $estimatedCompletion = Carbon::parse($currentWO->end_time)->format('M d, H:i');

                                            return new HtmlString(
                                                '<div class="bg-red-50 border border-red-200 rounded-lg p-3">'.
                                                    '<div class="flex items-center text-red-800 font-semibold mb-2">'.
                                                    '<span class="text-lg mr-2">🔴</span>'.
                                                    '<span>OCCUPIED '.htmlspecialchars($operatorName).'</span>'.
                                                    '</div>'.
                                                    '<div class="text-red-700 text-sm">'.
                                                    'Working on <a href="'.$woLink.'" class="font-medium text-red-600 underline hover:text-red-800" target="_blank">WO #'.htmlspecialchars($currentWO->unique_id).'</a><br>'.
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
                                    // Log the actual error for debugging
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
                    ->visible(function (callable $get) {
                        return $get('operator_id') && Auth::user()->factory_id ?? false;
                    })
                    ->collapsible()
                    ->collapsed(),

                // Batch Status Information Section
                Section::make('Batch Status & Key Information')
                    ->schema([
                        Placeholder::make('batch_status')
                            ->label('Current Batch Status')
                            ->content(function ($record) {
                                if (! $record || ! $record->usesBatchSystem()) {
                                    return new HtmlString('<div class="text-gray-500 italic">Individual work order - no batch system</div>');
                                }

                                $currentBatch = $record->getCurrentBatch();
                                $batchProgress = $record->getBatchProgress();

                                if (! $currentBatch) {
                                    return new HtmlString(
                                        '<div class="bg-blue-50 border border-blue-200 rounded-lg p-3">'.
                                            '<div class="flex items-center text-blue-800 font-semibold mb-2">'.
                                            '<span class="text-lg mr-2">📋</span>'.
                                            '<span>NO ACTIVE BATCH</span>'.
                                            '</div>'.
                                            '<div class="text-blue-700 text-sm">'.
                                            'Start a new batch to begin production<br>'.
                                            '<span class="text-gray-600">Total Progress:</span> '.$batchProgress['batches_completed'].'/'.$batchProgress['batches_total'].' batches completed<br>'.
                                            '<span class="text-gray-600">Total Production:</span> '.$batchProgress['total_completed'].' units completed'.
                                            '</div>'.
                                            '</div>'
                                    );
                                }

                                $statusIcon = match ($currentBatch->status) {
                                    'planned' => '📋',
                                    'in_progress' => '⚡',
                                    'completed' => '✅',
                                    default => '❓'
                                };

                                $statusColor = match ($currentBatch->status) {
                                    'planned' => 'blue',
                                    'in_progress' => 'green',
                                    'completed' => 'gray',
                                    default => 'gray'
                                };

                                return new HtmlString(
                                    '<div class="bg-'.$statusColor.'-50 border border-'.$statusColor.'-200 rounded-lg p-3">'.
                                        '<div class="flex items-center text-'.$statusColor.'-800 font-semibold mb-2">'.
                                        '<span class="text-lg mr-2">'.$statusIcon.'</span>'.
                                        '<span>BATCH #'.htmlspecialchars($currentBatch->batch_number).' - '.strtoupper($currentBatch->status).'</span>'.
                                        '</div>'.
                                        '<div class="text-'.$statusColor.'-700 text-sm">'.
                                        '<span class="text-gray-600">Planned Quantity:</span> '.htmlspecialchars($currentBatch->planned_quantity).' units<br>'.
                                        '<span class="text-gray-600">Actual Quantity:</span> '.htmlspecialchars($currentBatch->actual_quantity ?? 0).' units<br>'.
                                        '<span class="text-gray-600">Progress:</span> '.round($currentBatch->getProgressPercentage(), 1).'%<br>'.
                                        '<span class="text-gray-600">Started:</span> '.($currentBatch->started_at ? htmlspecialchars($currentBatch->started_at->format('M d, H:i')) : 'Not started').
                                        '</div>'.
                                        '</div>'
                                );
                            })
                            ->live(),

                        Placeholder::make('key_status')
                            ->label('Key Requirements & Availability')
                            ->content(function ($record) {
                                if (! $record || ! $record->usesBatchSystem()) {
                                    return new HtmlString('<div class="text-gray-500 italic">No key system for individual work orders</div>');
                                }

                                if ($record->is_dependency_root) {
                                    $generatedKeys = $record->batchKeys()->count();
                                    $availableKeys = $record->getAvailableKeys()->count();

                                    return new HtmlString(
                                        '<div class="bg-green-50 border border-green-200 rounded-lg p-3">'.
                                            '<div class="flex items-center text-green-800 font-semibold mb-2">'.
                                            '<span class="text-lg mr-2">🔑</span>'.
                                            '<span>ROOT WORK ORDER - GENERATES KEYS</span>'.
                                            '</div>'.
                                            '<div class="text-green-700 text-sm">'.
                                            '<span class="text-gray-600">Total Keys Generated:</span> '.htmlspecialchars($generatedKeys).'<br>'.
                                            '<span class="text-gray-600">Available Keys:</span> '.htmlspecialchars($availableKeys).'<br>'.
                                            '<span class="text-gray-600 italic">Complete batches to generate keys for dependent work orders</span>'.
                                            '</div>'.
                                            '</div>'
                                    );
                                }

                                $keysInfo = $record->getRequiredKeysInfo();
                                if (empty($keysInfo)) {
                                    return new HtmlString('<div class="text-gray-500 italic">No key requirements found</div>');
                                }

                                $content = '<div class="space-y-2">';
                                foreach ($keysInfo as $keyInfo) {
                                    $statusIcon = $keyInfo['is_satisfied'] ? '✅' : '❌';
                                    $statusColor = $keyInfo['is_satisfied'] ? 'green' : 'red';
                                    $statusText = $keyInfo['is_satisfied'] ? 'AVAILABLE' : 'NOT AVAILABLE';

                                    $content .= '<div class="bg-'.$statusColor.'-50 border border-'.$statusColor.'-200 rounded-lg p-3">'.
                                        '<div class="flex items-center text-'.$statusColor.'-800 font-semibold mb-2">'.
                                        '<span class="text-lg mr-2">'.$statusIcon.'</span>'.
                                        '<span>KEYS FROM '.htmlspecialchars($keyInfo['predecessor_name']).' - '.$statusText.'</span>'.
                                        '</div>'.
                                        '<div class="text-'.$statusColor.'-700 text-sm">'.
                                        '<span class="text-gray-600">Dependency Type:</span> '.htmlspecialchars($keyInfo['dependency_type']).'<br>'.
                                        '<span class="text-gray-600">Available Keys:</span> '.htmlspecialchars($keyInfo['available_keys_count']).'<br>';

                                    if ($keyInfo['is_satisfied'] && count($keyInfo['available_keys']) > 0) {
                                        $content .= '<span class="text-gray-600">Latest Key:</span> '.htmlspecialchars($keyInfo['available_keys'][0]['key_code']).' ('.htmlspecialchars($keyInfo['available_keys'][0]['quantity_produced']).' units)<br>';
                                    }

                                    if (! $keyInfo['is_satisfied']) {
                                        $content .= '<span class="text-gray-600 italic">Wait for '.htmlspecialchars($keyInfo['predecessor_name']).' to complete batches</span><br>';
                                    }

                                    $content .= '</div></div>';
                                }
                                $content .= '</div>';

                                return new HtmlString($content);
                            })
                            ->live(),
                    ])
                    ->visible(function ($record) {
                        return $record && $record->usesBatchSystem() && Auth::user()->hasRole('Operator');
                    })
                    ->collapsible()
                    ->collapsed(false), // Keep this section expanded by default for operators

                Select::make('status')
                    ->label('Status')
                    ->required()
                    ->live()
                    ->options(function ($record) {
                        if ($record) {
                            $user = Auth::user(); // Get the logged-in user

                            if ($user->hasRole('Operator')) {
                                return $record->getOperatorStatusOptions();
                            }
                        }

                        // Default options for non-operators
                        return [
                            'Assigned' => 'Assigned',
                            'Setup' => 'Setup',
                            'Start' => 'Start',
                            'Hold' => 'Hold',
                            'Completed' => 'Completed',
                            'Waiting' => 'Waiting',
                        ];
                    })
                    ->disabled(function ($record) {
                        if (! $record || ! $record->usesBatchSystem()) {
                            return false; // Individual work orders - allow status changes
                        }

                        // For grouped work orders, check if there's an active batch
                        $currentBatch = $record->getCurrentBatch();

                        // Disable if no active batch in progress (applies to ALL users including Super Admin)
                        return ! $currentBatch || $currentBatch->status !== 'in_progress';
                    })
                    ->rules([
                        function (callable $get) {
                            return function (string $attribute, $value, Closure $fail) use ($get) {
                                $recordId = $get('id');
                                if (! $recordId) {
                                    return; // Skip validation for new records
                                }

                                $record = WorkOrder::find($recordId);
                                if (! $record) {
                                    return;
                                }

                                // For grouped work orders, validate batch requirements
                                if ($record->usesBatchSystem()) {
                                    $canChange = $record->canOperatorChangeStatus($value);

                                    if (! $canChange['can_change']) {
                                        $message = $canChange['reason'];

                                        if ($canChange['required_action'] === 'start_new_batch') {
                                            $message .= '. Use the "Start New Batch" action button instead.';
                                        } elseif ($canChange['required_action'] === 'wait_for_keys') {
                                            $keysInfo = $record->getRequiredKeysInfo();
                                            $missingFromWOs = array_filter($keysInfo, fn ($info) => ! $info['is_satisfied']);
                                            $woNames = array_map(fn ($info) => $info['predecessor_name'], $missingFromWOs);
                                            $message .= '. Wait for these work orders to complete batches: '.implode(', ', $woNames);
                                        }

                                        $fail($message);
                                    }
                                }

                                // Traditional machine validation for Start status
                                if ($value === 'Start') {
                                    $machineId = $get('machine_id');
                                    $factoryId = Auth::user()->factory_id ?? null;

                                    if ($machineId && $factoryId) {
                                        $startValidation = WorkOrder::validateStartStatusTransition($machineId, $factoryId, $recordId);

                                        if (! $startValidation['can_start']) {
                                            $fail($startValidation['message']);
                                        }
                                    }

                                    // Note: Early start validation for Operators is handled in EditWorkOrder::beforeSave()
                                    // This allows for a better UX with notification actions
                                }
                            };
                        },
                    ])
                    ->helperText(function ($record) {
                        if (! $record || ! $record->usesBatchSystem()) {
                            return null;
                        }

                        $currentBatch = $record->getCurrentBatch();
                        if (! $currentBatch) {
                            return '🎯 Grouped work order: Use "Start New Batch" action to begin production';
                        }

                        if ($currentBatch->status === 'in_progress') {
                            return '⚡ Batch #'.$currentBatch->batch_number.' is in progress - you can change status';
                        }

                        return '📋 No active batch - start a new batch to enable status changes';
                    })
                    ->reactive()
                    ->afterStateUpdated(function ($set, $get, $livewire, $record, $state) {
                        $status = $get('status');

                        // EARLY/LATE START VALIDATION FOR OPERATORS
                        if ($status === 'Start' && Auth::user()->hasRole('Operator') && $record) {
                            // Check if this is the first start
                            $existingStartLog = $record->workOrderLogs()->where('status', 'Start')->exists();

                            if (! $existingStartLog) {
                                // Use the model's helper method to validate start time
                                $validation = $record->canOperatorStartNow();

                                if (! $validation['can_start']) {
                                    \Filament\Notifications\Notification::make()
                                        ->title($validation['reason'] === 'early_start' ? 'Cannot Start Before Planned Time' : 'Time Limit Exceeded')
                                        ->body($validation['message'])
                                        ->danger()
                                        ->persistent()
                                        ->send();

                                    // Revert status back to original
                                    $set('status', $record->status);
                                    $livewire->data['status'] = $record->status;

                                    return; // Stop further processing
                                }
                            }
                        }

                        // Clear hold_reason_id when status is not Hold
                        if ($status !== 'Hold') {
                            $livewire->data['hold_reason_id'] = null;
                            $set('hold_reason_id', null);
                            if ($record) {
                                $record->hold_reason_id = null;
                                $record->save();
                            }
                        }
                        // Note: Start log creation is now handled in WorkOrder model's booted() method
                        // after validation passes. This prevents premature log creation that would
                        // bypass the early start validation in EditWorkOrder::beforeSave()

                        // Trigger machine status update when status changes
                        if ($state && $get('machine_id')) {
                            self::validateMachineScheduling($get, $set);
                        }
                    }),

                TextInput::make('material_batch')
                    ->label('Material Batch ID')
                    ->required(fn ($get, $record) => $get('status') === 'Start' && ! $record?->material_batch)
                    ->visible(fn ($get) => in_array($get('status'), ['Start', 'Hold', 'Completed']))
                    ->disabled(fn ($record) => $record && $record->material_batch)
                    ->helperText(
                        fn ($get, $record) => $get('status') === 'Start' && ! $record?->material_batch
                            ? 'Material Batch ID is required when starting the work order'
                            : null
                    ),

                Select::make('hold_reason_id')
                    ->label('Hold Reason')
                    ->relationship('holdReason', 'description', function ($query) {
                        return $query->where('factory_id', auth()->user()->factory_id);
                    })
                    ->visible(fn ($get) => in_array($get('status'), ['Hold']))
                    ->reactive()
                    ->required(fn ($get) => in_array($get('status'), ['Hold']))
                    ->columnSpanFull(),

                Section::make('Quantities')
                    ->schema([
                        Repeater::make('quantities')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('ok_quantity')
                                            ->label('OK Quantity')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->disabled(fn ($record) => $record && $record->exists),
                                        TextInput::make('scrapped_quantity')
                                            ->label('Scrapped Quantity')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->disabled(fn ($record) => $record && $record->exists),
                                        Select::make('reason_id')
                                            ->label('Scrapped Reason')
                                            ->relationship(
                                                'reason',
                                                'description',
                                                fn ($query) => $query->where('factory_id', Auth::user()->factory_id) // Filter reasons by factory
                                            )->visible(fn ($get) => $get('scrapped_quantity') > 0)
                                            ->required(fn ($get) => $get('scrapped_quantity') > 0)
                                            ->disabled(fn ($record) => $record && $record->exists),
                                    ]),
                            ])
                            ->columns(1)
                            ->defaultItems(1)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(
                                fn (array $state): ?string => ($state['ok_quantity'] ?? 0) > 0 || ($state['scrapped_quantity'] ?? 0) > 0
                                    ? 'OK: '.($state['ok_quantity'] ?? 0).', Scrapped: '.($state['scrapped_quantity'] ?? 0)
                                    : null
                            )
                            ->live()
                            ->afterStateUpdated(function ($livewire, $state, $set, $get) {
                                // Calculate totals from all quantities
                                $totalOk = 0;
                                $totalScrapped = 0;

                                foreach ($state as $item) {
                                    $totalOk += (int) ($item['ok_quantity'] ?? 0);
                                    $totalScrapped += (int) ($item['scrapped_quantity'] ?? 0);
                                }

                                // Update the work order's total quantities
                                $workOrder = $get('record');
                                if ($workOrder) {
                                    $workOrder->ok_qtys = $totalOk;
                                    $workOrder->scrapped_qtys = $totalScrapped;
                                    $workOrder->save();
                                }

                                // Update both form state and Livewire data
                                $set('ok_qtys', $totalOk);
                                $set('scrapped_qtys', $totalScrapped);

                                $livewire->data['ok_qtys'] = $totalOk;
                                $livewire->data['scrapped_qtys'] = $totalScrapped;
                            })
                            ->relationship('quantities', function ($query) {
                                return $query->orderBy('created_at', 'desc');
                            })
                            ->createItemButtonLabel('Add Quantities')
                            ->defaultItems(1)
                            ->visible(fn ($get) => in_array($get('status'), ['Hold', 'Completed']))
                            ->beforeStateDehydrated(function ($state, $record, $get) {
                                if ($record && $record->exists) {
                                    return;
                                }

                                // Get the work order instance
                                $workOrder = $get('record');

                                // Get the latest work order log
                                $latestLog = $workOrder->workOrderLogs()->latest()->first();

                                if (! $latestLog) {
                                    // If no log exists, create one
                                    $latestLog = $workOrder->createWorkOrderLog($workOrder->status);
                                }

                                // Add the work_order_log_id to the state                                return array_merge($state, ['work_order_log_id' => $latestLog->id]);
                            })
                            ->afterStateHydrated(function ($state, $record, $set) {
                                if ($record) {
                                    // Calculate totals from existing quantities
                                    $totalOk = $record->quantities->sum('ok_quantity');
                                    $totalScrapped = $record->quantities->sum('scrapped_quantity');

                                    // Update the form state
                                    $set('ok_qtys', $totalOk);
                                    $set('scrapped_qtys', $totalScrapped);
                                }
                            }),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('ok_qtys')
                                    ->label('Total OK Quantities')
                                    ->default(0)
                                    ->readonly()
                                    ->visible(fn ($get) => in_array($get('status'), ['Hold', 'Completed'])),

                                TextInput::make('scrapped_qtys')
                                    ->label('Total Scrapped Quantities')
                                    ->default(0)
                                    ->readonly()
                                    ->visible(fn ($get) => in_array($get('status'), ['Hold', 'Completed'])),
                            ]),
                    ])
                    ->columnSpanFull()
                    ->visible(fn ($get) => in_array($get('status'), ['Hold', 'Completed'])),
            ]);
    }

    protected static function validateQuantities(callable $get, callable $set)
    {
        $status = $get('status');
        $okQtys = $get('ok_qtys') ?? 0;
        $scrappedQtys = $get('scrapped_qtys') ?? 0;
        $totalQty = $get('quantity');

        if ($status === 'Hold' && ($okQtys + $scrappedQtys > $totalQty)) {
            $set('ok_qtys', 0); // Reset invalid values
            $set('scrapped_qtys', 0);
            Notification::make()
                ->title('Error')
                ->body('Ok Qtys + Scrapped Quantities cannot exceed the total quantity when status is "Hold".')
                ->warning()
                ->send();
        }

        if ($status === 'Completed' && ($okQtys + $scrappedQtys !== $totalQty)) {
            Notification::make()
                ->title('Error')
                ->body('Ok Qtys + Scrapped Quantities must exactly match the total quantity when status is "Completed".')
                ->warning()
                ->send();
        }
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $isAdminOrManager = $user && $user->can('Edit Bom');

        return $table
            ->columns([
                TextColumn::make('unique_id')
                    ->label('Unique ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('bom.purchaseorder.partnumber.description')
                    ->label('BOM')
                    ->hidden(! $isAdminOrManager)
                    ->toggleable()
                    ->wrap(),
                TextColumn::make('bom.purchaseorder.partnumber.partnumber')
                    ->label('Part Number')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('bom.purchaseorder.partnumber.revision')
                    ->label('Revision'),
                TextColumn::make('machine.name')
                    ->label('Machine')
                    ->formatStateUsing(fn ($record) => "{$record->machine->assetId}")
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('operator.user.first_name')
                    ->label('Operator')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('qty')
                    ->label('Qty')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Assigned' => 'gray',
                        'Setup' => 'primary',
                        'Start' => 'warning',
                        'Hold' => 'danger',
                        'Completed' => 'success',
                        'Closed' => 'info',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('start_time')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('end_time')
                    ->date()
                    ->sortable()
                    ->toggleable()->formatStateUsing(function ($state, $record) {
                        // Format the date as usual
                        return Carbon::parse($state)->format('d M Y H:i');
                    })
                    ->extraAttributes(function ($record) {
                        // Check if BOM exists and has a lead_time
                        if ($record->bom && $record->bom->lead_time && $record->end_time) {
                            $plannedEnd = Carbon::parse($record->end_time);
                            $bomLead = Carbon::parse($record->bom->lead_time)->endOfDay();
                            if ($plannedEnd->greaterThan($bomLead)) {
                                // Add a background color (e.g., red-100) if planned end exceeds BOM lead_time
                                return [
                                    'style' => 'background-color: #FCA5A5; cursor: pointer;', // Tailwind red-100
                                ];
                            }
                        }

                        return [];
                    })
                    ->tooltip(function ($record) {
                        if ($record->bom && $record->bom->lead_time && $record->end_time) {
                            $plannedEnd = Carbon::parse($record->end_time);
                            $bomLead = Carbon::parse($record->bom->lead_time)->endOfDay();
                            if ($plannedEnd->greaterThan($bomLead)) {
                                return 'BOM Target Completion Time: '.Carbon::parse($record->bom->lead_time)->format('d M Y');
                            }
                        }

                        return null;
                    }),
                TextColumn::make('ok_qtys')
                    ->label('OK Qtys')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('scrapped_qtys')
                    ->label('KO Qtys')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->modifyQueryUsing(function (Builder $query) {
                $userId = Auth::id();
                $user = User::find($userId);
                if ($user && $user->hasRole('operator')) {
                    $operator = Operator::where('user_id', $userId)->first();

                    if ($operator && $user->factory_id) {
                        return $query->where('operator_id', $operator->id)
                            ->where('factory_id', $user->factory_id);
                    }
                }

                return $query;
            })
            ->filters([
                TrashedFilter::make(),

            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->visible(
                            fn ($record) => (auth()->user()->hasRole('Operator') && $record->status !== 'Closed') ||
                                $isAdminOrManager
                        ),
                    ViewAction::make()->hiddenLabel(),

                    // Batch Management Actions
                    Action::make('start_batch')
                        ->label(function (WorkOrder $record) {
                            if (! $record->usesBatchSystem()) {
                                return 'Start New Batch';
                            }

                            if (! $record->is_dependency_root && ! $record->hasRequiredKeys()) {
                                return 'Start New Batch (Keys Required)';
                            }

                            $currentBatch = $record->getCurrentBatch();
                            if ($currentBatch && $currentBatch->status === 'in_progress') {
                                return 'Batch In Progress';
                            }

                            return 'Start New Batch';
                        })
                        ->icon('heroicon-o-play')
                        ->color(function (WorkOrder $record) {
                            if (! $record->usesBatchSystem()) {
                                return 'success';
                            }

                            if (! $record->is_dependency_root && ! $record->hasRequiredKeys()) {
                                return 'warning'; // Yellow for waiting on keys
                            }

                            $currentBatch = $record->getCurrentBatch();
                            if ($currentBatch && $currentBatch->status === 'in_progress') {
                                return 'gray'; // Gray for already in progress
                            }

                            return 'success';
                        })
                        ->disabled(function (WorkOrder $record) {
                            // Disable if batch is already in progress
                            $currentBatch = $record->getCurrentBatch();
                            if ($currentBatch && $currentBatch->status === 'in_progress') {
                                return true;
                            }

                            // Disable if keys are required but not available
                            if (! $record->is_dependency_root && ! $record->hasRequiredKeys()) {
                                return true;
                            }

                            return false;
                        })
                        ->visible(function (WorkOrder $record) {
                            // Only show for WorkOrders that use batch system
                            if (! $record->usesBatchSystem()) {
                                return false;
                            }

                            // Show for all grouped work orders
                            return $record->work_order_group_id !== null;
                        })
                        ->tooltip(function (WorkOrder $record) {
                            if (! $record->usesBatchSystem()) {
                                return null;
                            }

                            $currentBatch = $record->getCurrentBatch();
                            if ($currentBatch && $currentBatch->status === 'in_progress') {
                                return 'Complete current batch #'.$currentBatch->batch_number.' before starting a new one';
                            }

                            if (! $record->is_dependency_root && ! $record->hasRequiredKeys()) {
                                $keysInfo = $record->getRequiredKeysInfo();
                                $missingFromWOs = array_filter($keysInfo, fn ($info) => ! $info['is_satisfied']);
                                $woNames = array_map(fn ($info) => $info['predecessor_name'], $missingFromWOs);

                                return 'Waiting for keys from: '.implode(', ', $woNames);
                            }

                            if ($record->is_dependency_root) {
                                return 'Root work order - no keys required. Start a batch to begin production.';
                            }

                            return 'All requirements satisfied - ready to start new batch';
                        })
                        ->form(function (WorkOrder $record) {
                            $formFields = [
                                TextInput::make('planned_quantity')
                                    ->label('Planned Quantity for this Batch')
                                    ->numeric()
                                    ->required()
                                    ->default(25)
                                    ->minValue(1),
                            ];

                            // For non-root work orders, add key selection
                            if (! $record->is_dependency_root) {
                                $dependencies = \App\Models\WorkOrderDependency::where('successor_work_order_id', $record->id)
                                    ->where('work_order_group_id', $record->work_order_group_id)
                                    ->with('predecessor')
                                    ->get();

                                foreach ($dependencies as $dependency) {
                                    $availableKeys = $dependency->predecessor->getAvailableKeys();

                                    if ($availableKeys->isEmpty()) {
                                        $formFields[] = \Filament\Forms\Components\Placeholder::make('no_keys_'.$dependency->predecessor_work_order_id)
                                            ->label('Required Keys from '.$dependency->predecessor->unique_id)
                                            ->content('❌ No keys available from '.$dependency->predecessor->unique_id.'. Complete predecessor work orders first to generate keys.');
                                    } else {
                                        $formFields[] = Select::make('selected_keys.'.$dependency->predecessor_work_order_id)
                                            ->label('Select Key from '.$dependency->predecessor->unique_id)
                                            ->options(function () use ($dependency) {
                                                // Always fetch fresh available keys to prevent stale data
                                                $freshAvailableKeys = $dependency->predecessor->fresh()->getAvailableKeys();

                                                return $freshAvailableKeys->mapWithKeys(function ($key) {
                                                    return [$key->id => $key->key_code.' ('.$key->quantity_produced.' units) - Available'];
                                                });
                                            })
                                            ->required()
                                            ->helperText('Select a key to consume for this batch (only showing available/unconsumed keys)')
                                            ->placeholder('Choose an available key...')
                                            ->searchable();
                                    }
                                }
                            }

                            return $formFields;
                        })
                        ->action(function (array $data, WorkOrder $record) {
                            try {
                                // Get required keys for non-root work orders
                                $keysRequired = [];
                                $selectedKeys = [];

                                if (! $record->is_dependency_root) {
                                    $dependencies = \App\Models\WorkOrderDependency::where('successor_work_order_id', $record->id)
                                        ->where('work_order_group_id', $record->work_order_group_id)
                                        ->with('predecessor')
                                        ->get();

                                    foreach ($dependencies as $dependency) {
                                        $keysRequired[] = [
                                            'work_order_id' => $dependency->predecessor_work_order_id,
                                            'dependency_type' => $dependency->dependency_type,
                                            'quantity_needed' => 1, // One key per batch
                                            'work_order_name' => $dependency->predecessor->unique_id,
                                        ];

                                        // Get selected key for this dependency
                                        if (isset($data['selected_keys'][$dependency->predecessor_work_order_id])) {
                                            $selectedKeys[] = $data['selected_keys'][$dependency->predecessor_work_order_id];
                                        }
                                    }

                                    // Validate that keys are available and not already consumed
                                    foreach ($selectedKeys as $keyId) {
                                        $key = \App\Models\WorkOrderBatchKey::find($keyId);
                                        if (! $key || ! $key->isAvailable()) {
                                            throw new \Exception('Selected key is no longer available. Please refresh and try again.');
                                        }
                                    }
                                }

                                // Create the batch
                                $batch = $record->createBatch($data['planned_quantity'], $keysRequired);

                                if (! $batch) {
                                    throw new \Exception('Failed to create batch');
                                }

                                // Start the batch immediately (which will consume the keys)
                                $batchStarted = $batch->startBatch($selectedKeys);

                                if (! $batchStarted) {
                                    // If starting failed, delete the batch
                                    $batch->delete();
                                    throw new \Exception('Failed to start batch. Keys may no longer be available.');
                                }

                                \Filament\Notifications\Notification::make()
                                    ->title('Batch Started Successfully')
                                    ->body("Batch #{$batch->batch_number} has been started and is now in progress.".
                                           (! empty($selectedKeys) ? ' Consumed '.count($selectedKeys).' key(s).' : ''))
                                    ->success()
                                    ->send();

                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Failed to Start Batch')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('complete_batch')
                        ->label('Complete Batch')
                        ->icon('heroicon-o-check-circle')
                        ->color('warning')
                        ->visible(function (WorkOrder $record) {
                            if (! $record->usesBatchSystem()) {
                                return false;
                            }
                            $currentBatch = $record->getCurrentBatch();

                            return $currentBatch && $currentBatch->status === 'in_progress';
                        })
                        ->form([
                            TextInput::make('actual_quantity')
                                ->label('Actual Quantity Produced')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->helperText(function (WorkOrder $record) {
                                    $batch = $record->getCurrentBatch();

                                    return $batch ? "Maximum: {$batch->planned_quantity} units" : '';
                                })
                                ->rules(function (WorkOrder $record) {
                                    $batch = $record->getCurrentBatch();
                                    $maxQty = $batch ? $batch->planned_quantity : 999;

                                    return ["max:{$maxQty}"];
                                }),
                        ])
                        ->action(function (array $data, WorkOrder $record) {
                            try {
                                $currentBatch = $record->getCurrentBatch();
                                if (! $currentBatch || $currentBatch->status !== 'in_progress') {
                                    throw new \Exception('No batch in progress to complete');
                                }

                                $success = $currentBatch->completeBatch($data['actual_quantity']);

                                if ($success) {
                                    // Check if a key was generated
                                    $generatedKey = $currentBatch->fresh()->batchKey;
                                    $keyMessage = $generatedKey ? " Key generated: {$generatedKey->key_code}" : '';

                                    \Filament\Notifications\Notification::make()
                                        ->title('Batch Completed Successfully')
                                        ->body("Batch #{$currentBatch->batch_number} completed with {$data['actual_quantity']} units.{$keyMessage}")
                                        ->success()
                                        ->send();

                                    // Update dependent work orders if in a group
                                    if ($record->work_order_group_id) {
                                        $record->workOrderGroup->updateWaitingWorkOrderStatuses();
                                    }
                                } else {
                                    throw new \Exception('Failed to complete batch');
                                }
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Failed to Complete Batch')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    Action::make('Alert Manager')
                        ->visible(fn () => Auth::check() && Auth::user()->hasRole('Operator'))
                        ->schema([
                            Textarea::make('comments')
                                ->label('Comments')
                                ->required(),
                            Select::make('priority')
                                ->label('Priority')
                                ->options([
                                    'High' => 'High',
                                    'Medium' => 'Medium',
                                    'Low' => 'Low',
                                ])
                                ->required(),
                        ])
                        ->modalHeading('Send Alert to Manager') // Title of the modal
                        ->action(function (array $data, $record) {
                            self::sendAlert($data, $record);
                        })
                        ->button(), // Ensures it's a button, not a link

                    Action::make('Alert Operator')
                        ->visible(fn () => Auth::check() && (Auth::user()->hasRole('Manager') || Auth::user()->hasRole('Factory Admin')))
                        ->schema([
                            Textarea::make('comments')
                                ->label('Comments')
                                ->required(),
                            Select::make('priority')
                                ->label('Priority')
                                ->options([
                                    'High' => 'High',
                                    'Medium' => 'Medium',
                                    'Low' => 'Low',
                                ])
                                ->required(),
                        ])
                        ->modalHeading('Send Alert to Operator') // Title of the modal
                        ->action(function (array $data, $record) {
                            self::sendAlert($data, $record);
                        })
                        ->button(),
                ])->size('sm')->tooltip('Action')->dropdownPlacement('right'),
            ], position: RecordActionsPosition::BeforeColumns)
            ->headerActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function sendAlert(array $data, WorkOrder $record)
    {

        InfoMessage::create([
            'work_order_id' => $record->id,
            'user_id' => auth()->id(), // Assuming the logged-in user sends the alert
            'message' => $data['comments'],
            'priority' => $data['priority'],
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
            'index' => ListWorkOrders::route('/'),
            'create' => CreateWorkOrder::route('/create'),
            'edit' => EditWorkOrder::route('/{record}/edit'),
            'view' => ViewWorkOrder::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $user = Auth::user();

        if (Auth::check() && $user->hasRole('Operator')) {
            $userId = Auth::id(); // Get the logged-in user's ID
            $query->whereHas('operator', function ($operatorQuery) use ($userId) {
                $operatorQuery->where('user_id', $userId);
            });
        }

        return $query;
    }

    protected static function convertSecondsToTime($seconds): string
    {
        if (! $seconds) {
            return '00:00:00';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }

    public static function getWidgets(): array
    {
        return [
            WorkOrderProgress::class,
            SimpleWorkOrderGantt::class,
        ];
    }

    /**
     * Get scheduling validation error message for form rules
     */
    protected static function getSchedulingValidationError(callable $get, $machineId, $startTime, $endTime): ?string
    {
        $factoryId = Auth::user()->factory_id ?? null;
        $recordId = $get('id');
        $status = $get('status');

        if (! $factoryId) {
            return null;
        }

        try {
            $validation = WorkOrder::validateScheduling([
                'machine_id' => $machineId,
                'factory_id' => $factoryId,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => $status,
                'id' => $recordId,
            ]);

            // Check for start status conflicts first (more critical)
            if (isset($validation['start_validation']) && ! $validation['start_validation']['can_start']) {
                return $validation['start_validation']['message'];
            }

            // Check for scheduling conflicts
            if (! $validation['is_valid']) {
                $conflictMessages = [];
                foreach ($validation['conflicts'] as $conflict) {
                    $conflictMessages[] = "Conflict with WO #{$conflict['work_order_unique_id']} ({$conflict['status']}) from ".
                        Carbon::parse($conflict['planned_start'])->format('M d, H:i').
                        ' to '.Carbon::parse($conflict['planned_end'])->format('M d, H:i');
                }

                return 'Machine scheduling conflict detected: '.implode('; ', $conflictMessages);
            }
        } catch (Exception $e) {
            return 'Unable to validate machine scheduling: '.$e->getMessage();
        }

        return null;
    }

    /**
     * Validate machine scheduling for conflicts
     */
    protected static function validateMachineScheduling(callable $get, callable $set)
    {
        $machineId = $get('machine_id');
        $startTime = $get('start_time');
        $endTime = $get('end_time');
        $factoryId = Auth::user()->factory_id ?? null;
        $recordId = $get('id'); // For edit mode

        // Only validate if we have all required data
        if (! $machineId || ! $startTime || ! $endTime || ! $factoryId) {
            return;
        }

        try {
            // Prepare validation data
            $workOrderData = [
                'machine_id' => $machineId,
                'factory_id' => $factoryId,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'id' => $recordId,
            ];

            // Validate scheduling
            $validation = WorkOrder::validateScheduling($workOrderData);

            // Show notifications based on validation results
            if (! $validation['is_valid']) {
                // Show conflict notification
                $conflictMessages = [];
                foreach ($validation['conflicts'] as $conflict) {
                    $conflictMessages[] = "Conflict with WO #{$conflict['work_order_unique_id']} ({$conflict['status']}) from ".
                        Carbon::parse($conflict['planned_start'])->format('M d, H:i').
                        ' to '.Carbon::parse($conflict['planned_end'])->format('M d, H:i');
                }

                Notification::make()
                    ->title('🚨 Machine Scheduling Conflict!')
                    ->body('The selected time slot conflicts with existing work orders:'.PHP_EOL.implode(PHP_EOL, $conflictMessages))
                    ->danger()
                    ->persistent()
                    ->send();

                // Show recommendations if available
                if (! empty($validation['recommendations'])) {
                    $recommendationMessages = [];
                    foreach ($validation['recommendations'] as $recommendation) {
                        if ($recommendation['type'] === 'reschedule_after_conflicts') {
                            $recommendationMessages[] = '💡 '.$recommendation['message'];
                        }
                    }

                    if (! empty($recommendationMessages)) {
                        Notification::make()
                            ->title('Scheduling Recommendations')
                            ->body(implode(PHP_EOL, $recommendationMessages))
                            ->warning()
                            ->persistent()
                            ->send();
                    }
                }
            } else {
                // Show warnings if machine is currently occupied
                if (! empty($validation['warnings'])) {
                    foreach ($validation['warnings'] as $warning) {
                        if ($warning['type'] === 'machine_currently_occupied') {
                            Notification::make()
                                ->title('⚠️ Machine Currently Occupied')
                                ->body($warning['message'].' (Est. completion: '.
                                    Carbon::parse($warning['estimated_completion'])->format('M d, H:i').')')
                                ->warning()
                                ->send();
                        }
                    }
                }
                // Removed the success notification to avoid spam
            }
        } catch (Exception $e) {
            // Handle any validation errors gracefully
            Notification::make()
                ->title('Validation Error')
                ->body('Unable to validate machine scheduling: '.$e->getMessage())
                ->warning()
                ->send();
        }
    }

    /**
     * Get operator scheduling validation error message for form rules
     */
    protected static function getOperatorSchedulingValidationError(callable $get, $operatorId, $startTime, $endTime): ?string
    {
        $factoryId = Auth::user()->factory_id ?? null;
        $recordId = $get('id');
        $status = $get('status');

        if (! $factoryId) {
            return null;
        }

        try {
            $validation = WorkOrder::validateScheduling([
                'machine_id' => $get('machine_id'),
                'operator_id' => $operatorId,
                'factory_id' => $factoryId,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'status' => $status,
                'id' => $recordId,
            ]);

            // Check for operator availability conflicts (blocking)
            if (! empty($validation['operator_conflicts'])) {
                $operatorConflictCount = count(array_filter($validation['operator_conflicts'],
                    fn ($c) => $c['type'] === 'work_order_conflict'));

                if ($operatorConflictCount > 0) {
                    return 'Operator scheduling conflict detected: operator is already assigned to other work orders during this time.';
                }
            }

        } catch (Exception $e) {
            return 'Unable to validate operator scheduling: '.$e->getMessage();
        }

        return null;
    }

    /**
     * Validate operator scheduling for conflicts and shift compliance
     */
    protected static function validateOperatorScheduling(callable $get, callable $set)
    {
        $operatorId = $get('operator_id');
        $startTime = $get('start_time');
        $endTime = $get('end_time');
        $factoryId = Auth::user()->factory_id ?? null;
        $recordId = $get('id'); // For edit mode

        // Only validate if we have all required data
        if (! $operatorId || ! $startTime || ! $endTime || ! $factoryId) {
            return;
        }

        try {
            // Prepare validation data
            $workOrderData = [
                'machine_id' => $get('machine_id'),
                'operator_id' => $operatorId,
                'factory_id' => $factoryId,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'id' => $recordId,
            ];

            // Validate scheduling
            $validation = WorkOrder::validateScheduling($workOrderData);

            // Show notifications for shift conflicts (warnings, not blocking)
            if (! empty($validation['shift_conflicts'])) {
                foreach ($validation['shift_conflicts'] as $conflict) {
                    Notification::make()
                        ->title('⚠️ Operator Shift Warning')
                        ->body($conflict['message'])
                        ->warning()
                        ->send();
                }
            }

            // Show notifications for operator availability conflicts (blocking)
            if (! empty($validation['operator_conflicts'])) {
                $operatorConflictCount = count(array_filter($validation['operator_conflicts'],
                    fn ($c) => $c['type'] === 'work_order_conflict'));

                if ($operatorConflictCount > 0) {
                    $conflictMessages = [];
                    foreach ($validation['operator_conflicts'] as $conflict) {
                        if ($conflict['type'] === 'work_order_conflict') {
                            $conflictMessages[] = "Conflict with WO #{$conflict['work_order_unique_id']} ({$conflict['status']}) from ".
                                Carbon::parse($conflict['planned_start'])->format('M d, H:i').
                                ' to '.Carbon::parse($conflict['planned_end'])->format('M d, H:i');
                        }
                    }

                    if (! empty($conflictMessages)) {
                        Notification::make()
                            ->title('🚨 Operator Scheduling Conflict!')
                            ->body('The selected time slot conflicts with operator\'s existing assignments:'.PHP_EOL.implode(PHP_EOL, $conflictMessages))
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }

                // Show operator-specific recommendations if available
                if (! empty($validation['recommendations'])) {
                    $recommendationMessages = [];
                    foreach ($validation['recommendations'] as $recommendation) {
                        if (in_array($recommendation['type'], ['reschedule_within_shift', 'reschedule_after_operator_conflicts'])) {
                            $recommendationMessages[] = '💡 '.$recommendation['message'];
                        }
                    }

                    if (! empty($recommendationMessages)) {
                        Notification::make()
                            ->title('Operator Scheduling Recommendations')
                            ->body(implode(PHP_EOL, $recommendationMessages))
                            ->warning()
                            ->persistent()
                            ->send();
                    }
                }
            }

            // Show warnings if operator is currently occupied
            if (! empty($validation['warnings'])) {
                foreach ($validation['warnings'] as $warning) {
                    if ($warning['type'] === 'operator_shift_conflict') {
                        Notification::make()
                            ->title('⚠️ Shift Timing Notice')
                            ->body($warning['message'])
                            ->warning()
                            ->send();
                    } elseif ($warning['type'] === 'operator_availability_conflict') {
                        Notification::make()
                            ->title('🚫 Operator Not Available')
                            ->body($warning['message'])
                            ->danger()
                            ->send();
                    }
                }
            }
        } catch (Exception $e) {
            // Handle any validation errors gracefully
            Notification::make()
                ->title('Validation Error')
                ->body('Unable to validate operator scheduling: '.$e->getMessage())
                ->warning()
                ->send();
        }
    }
}
