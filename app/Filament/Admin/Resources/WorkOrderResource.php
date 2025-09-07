<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WorkOrderResource\Pages;
use App\Models\InfoMessage;
use App\Models\Machine;
use App\Models\Operator;
use App\Models\User;
use App\Models\WorkOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class WorkOrderResource extends Resource
{
    protected static ?string $model = WorkOrder::class;

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Process Operations';

    public static function form(Form $form): Form
    {

        $user = Auth::user();
        $isAdminOrManager = $user && $user->can(abilities: 'Edit Bom');        // dd($isAdminOrManager);

        return $form
            ->schema([
                Forms\Components\Select::make('part_number_id')
                    ->label('Partnumber')
                    ->options(function () {
                        $factoryId = Auth::user()->factory_id; // Get the factory ID of the logged-in user

                        // Query the PartNumber model and include the partnumber and revision
                        return \App\Models\PartNumber::where('factory_id', $factoryId)
                            ->get()
                            ->mapWithKeys(function ($partNumber) {
                                return [
                                    $partNumber->id => $partNumber->partnumber.' - '.$partNumber->revision,
                                ];
                            });
                    })
                    ->searchable()
                    ->reactive()
                    ->preload()
                    ->disabled(! $isAdminOrManager)
                    ->formatStateUsing(function ($record) {
                        if ($record && $record->bom) {
                            return $record->bom->purchaseOrder->partnumber->id ?? null;
                        }

                        return null; // Return null if the part number doesn't exist
                    }),
                Forms\Components\Select::make('bom_id')
                    ->label('BOM')
                    ->options(function (callable $get) {
                        $partNumberId = $get('part_number_id'); // Get the selected Part Number ID
                        if (! $partNumberId) {
                            return []; // No Part Number selected, return empty options
                        }

                        // Query BOMs through the Purchase Order link and include Part Number description
                        return \App\Models\Bom::whereHas('purchaseOrder', function ($query) use ($partNumberId) {
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

                Forms\Components\Select::make('machine_id')
                    ->label('Machine')
                    ->options(function (callable $get) {
                        $bomId = $get('bom_id'); // Get the selected BOM ID

                        if (! $bomId) {
                            return []; // No BOM selected, return empty options
                        }

                        // Find the BOM and get its machine group
                        $bom = \App\Models\Bom::find($bomId);
                        if (! $bom || ! $bom->machine_group_id) {
                            return []; // BOM not found or has no associated machine group
                        }

                        // Fetch all active machines in the machine group within the current factory
                        return \App\Models\Machine::where('machine_group_id', $bom->machine_group_id)
                            ->where('factory_id', \Illuminate\Support\Facades\Auth::user()->factory_id)
                            ->active()
                            ->get()
                            ->mapWithKeys(fn ($machine) => [
                                (int) $machine->id => "Asset ID: {$machine->assetId} - Name: {$machine->name}",
                            ])
                            ->toArray();
                    })
                    ->reactive()
                    ->required()
                    ->disabled(! $isAdminOrManager)
                    ->rules([
                        function (callable $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
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
                        // Only validate if all required fields are filled and this is not the initial load
                        if ($state && $get('start_time') && $get('end_time')) {
                            self::validateMachineScheduling($get, $set);
                        }
                    }),

                Forms\Components\TextInput::make('qty')
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
                        $bom = \App\Models\Bom::find($get('bom_id'));

                        if ($bom) {
                            // Get the related Purchase Order and its quantity
                            $purchaseOrder = $bom->purchaseOrder;

                            // Check if the entered quantity exceeds the Purchase Order quantity
                            if ($purchaseOrder && $qty > $purchaseOrder->QTY) {
                                \Filament\Notifications\Notification::make()
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
                        $cycleTimeInSeconds = \App\Models\PartNumber::where('id', $partNumberId)->value('cycle_time');

                        if (! $cycleTimeInSeconds) {
                            $set('time_to_complete', '00:00:00'); // Default if no cycle time is set

                            return;
                        }

                        // Calculate total time
                        $totalSeconds = $cycleTimeInSeconds * $qty;
                        $set('time_to_complete', self::convertSecondsToTime($totalSeconds));
                    }),

                Forms\Components\Select::make('operator_id')
                    ->label('Operator')
                    ->disabled(! $isAdminOrManager)
                    ->options(function (callable $get) {
                        $bomId = $get('bom_id'); // Get selected BOM ID

                        if (! $bomId) {
                            return []; // No BOM selected, return empty options
                        }

                        // Get the operator proficiency ID from the BOM's linked Purchase Order
                        $operatorProficiencyId = \App\Models\Bom::find($bomId)->operator_proficiency_id;

                        // Get operators based on the proficiency ID
                        $factoryId = Auth::user()->factory_id; // Get the logged-in user's factory_id

                        return Operator::where('factory_id', $factoryId)
                            ->where('operator_proficiency_id', $operatorProficiencyId) // Filter by proficiency
                            ->with(['user', 'shift']) // Get the associated user and shift
                            ->get()
                            ->mapWithKeys(function ($operator) {
                                $shiftInfo = $operator->shift
                                    ? " ({$operator->shift->name}: {$operator->shift->start_time}-{$operator->shift->end_time})"
                                    : '';

                                return [$operator->id => $operator->user->first_name.' '.$operator->user->last_name.$shiftInfo];
                            });
                    })
                    ->searchable()
                    ->required()
                    ->reactive()
                    ->helperText(function (callable $get) {
                        $operatorId = $get('operator_id');
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
                        } catch (\Exception $e) {
                            return null;
                        }

                        return null;
                    })
                    ->rules([
                        function (callable $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
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
                        // Only validate if all required fields are filled and this is not the initial load
                        if ($state && $get('start_time') && $get('end_time')) {
                            self::validateOperatorScheduling($get, $set);
                        }
                    }),

                Forms\Components\TextInput::make('time_to_complete')
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
                                $cycleTimeInSeconds = \App\Models\PartNumber::where('id', $partNumberId)->value('cycle_time');
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
                        $cycleTimeInSeconds = \App\Models\PartNumber::where('id', $partNumberId)->value('cycle_time');

                        if (! $cycleTimeInSeconds) {
                            $set('time_to_complete', '00:00:00');

                            return;
                        }

                        // Calculate total time
                        $totalSeconds = $cycleTimeInSeconds * $qty;
                        $set('time_to_complete', self::convertSecondsToTime($totalSeconds));
                    }),

                Forms\Components\DateTimePicker::make('start_time')
                    ->disabled(! $isAdminOrManager)
                    ->label('Planned Start Time')
                    ->seconds(false) // Cleaner UI without seconds
                    ->native(false) // Better custom picker
                    ->displayFormat('d M Y, H:i') // "21 Jul 2025, 14:30"
                    ->timezone('Asia/Kolkata')
                    ->live(onBlur: true)
                    ->required()
                    ->rules([
                        function (callable $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
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
                        // Only validate if all required fields are filled
                        if ($state && $get('machine_id') && $get('end_time')) {
                            self::validateMachineScheduling($get, $set);
                        }
                    }),
                Forms\Components\DateTimePicker::make('end_time')
                    ->label('Planned End Time')
                    ->disabled(! $isAdminOrManager)
                    ->seconds(false) // Cleaner UI without seconds
                    ->native(false) // Better custom picker
                    ->displayFormat('d M Y, H:i') // "21 Jul 2025, 14:30"
                    ->timezone('Asia/Kolkata')
                    ->live(onBlur: true)
                    ->required()
                    ->rules([
                        function (callable $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
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
                        // Only validate if all required fields are filled
                        if ($state && $get('machine_id') && $get('start_time')) {
                            self::validateMachineScheduling($get, $set);
                        }
                    })
                    ->helperText(function (callable $get) {
                        $bomId = $get('bom_id');
                        if (! $bomId) {
                            return null;
                        }
                        $bom = \App\Models\Bom::find($bomId);
                        if ($bom && $bom->lead_time) {
                            return 'BOM Target Completion Time: '.\Carbon\Carbon::parse($bom->lead_time)->format('d M Y');
                        }

                        return null;
                    })
                    ->reactive(),

                // Machine Status Information Section
                Forms\Components\Section::make('Machine Scheduling Information')
                    ->schema([
                        Forms\Components\Placeholder::make('machine_status')
                            ->label('Current Machine Status')
                            ->content(function (callable $get) {
                                $machineId = $get('machine_id');
                                $startTime = $get('start_time');
                                $endTime = $get('end_time');
                                $status = $get('status');
                                $factoryId = Auth::user()->factory_id ?? null;

                                if (! $machineId || ! $factoryId) {
                                    return new \Illuminate\Support\HtmlString('<div class="text-gray-500 italic">Select a machine to see status</div>');
                                }

                                try {
                                    // Get machine details and ensure it belongs to the same factory
                                    $machine = \App\Models\Machine::where('id', $machineId)
                                        ->where('factory_id', $factoryId)
                                        ->first();

                                    if (! $machine) {
                                        return new \Illuminate\Support\HtmlString('<div class="text-red-600">⚠️ Machine not found or belongs to different factory</div>');
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
                                                ? \Carbon\Carbon::parse($conflictingWO->end_time)->format('M d, H:i')
                                                : 'Unknown';

                                            // Generate the edit URL for the conflicting work order
                                            $woLink = url("/admin/{$factoryId}/work-orders/{$conflictingWO->id}");

                                            // Determine if it's a running conflict or scheduled conflict
                                            if ($conflictingWO->status === 'Start') {
                                                // Work order is actually running
                                                return new \Illuminate\Support\HtmlString(
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
                                                $scheduledStart = \Carbon\Carbon::parse($conflictingWO->start_time)->format('M d, H:i');
                                                $scheduledEnd = \Carbon\Carbon::parse($conflictingWO->end_time)->format('M d, H:i');

                                                return new \Illuminate\Support\HtmlString(
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
                                            $estimatedCompletion = \Carbon\Carbon::parse($currentWO->end_time)->format('M d, H:i');

                                            return new \Illuminate\Support\HtmlString(
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

                                            return new \Illuminate\Support\HtmlString(
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

                                    return new \Illuminate\Support\HtmlString(
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
                                } catch (\Exception $e) {
                                    // Log the actual error for debugging
                                    \Illuminate\Support\Facades\Log::error('Machine status check failed: '.$e->getMessage(), [
                                        'exception' => $e,
                                        'machine_id' => $machineId,
                                        'factory_id' => $factoryId,
                                        'status' => $status,
                                    ]);

                                    return new \Illuminate\Support\HtmlString(
                                        '<div class="text-red-500 italic">Unable to check machine status<br>'.
                                            '<small>Error: '.htmlspecialchars($e->getMessage()).'</small></div>'
                                    );
                                }
                            })
                            ->live(),
                        Forms\Components\Placeholder::make('upcoming_schedule')
                            ->label('Relevant Schedule')
                            ->content(function (callable $get) {
                                $machineId = $get('machine_id');
                                $startTime = $get('start_time');
                                $endTime = $get('end_time');
                                $factoryId = Auth::user()->factory_id ?? null;

                                if (! $machineId || ! $factoryId) {
                                    return new \Illuminate\Support\HtmlString('<div class="text-gray-500 italic">Select a machine to see relevant schedule</div>');
                                }

                                try {
                                    $schedule = [];

                                    // If user has entered scheduling times, show only relevant work orders
                                    if ($startTime && $endTime) {
                                        $newStart = \Carbon\Carbon::parse($startTime);
                                        $newEnd = \Carbon\Carbon::parse($endTime);

                                        // Get ALL work orders for this machine to check for conflicts and same-date items
                                        $allWOs = WorkOrder::where('machine_id', $machineId)
                                            ->where('factory_id', $factoryId)
                                            ->where('status', 'Assigned')
                                            ->orderBy('start_time')
                                            ->get();

                                        foreach ($allWOs as $wo) {
                                            $woStart = \Carbon\Carbon::parse($wo->start_time);
                                            $woEnd = \Carbon\Carbon::parse($wo->end_time);

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
                                                'start_formatted' => \Carbon\Carbon::parse($wo->start_time)->format('M d, H:i'),
                                                'end_formatted' => \Carbon\Carbon::parse($wo->end_time)->format('H:i'),
                                            ];
                                        }
                                    }

                                    if (empty($schedule)) {
                                        $message = $startTime && $endTime ?
                                            'No conflicting or same-day work orders found' :
                                            'No upcoming scheduled work orders';

                                        return new \Illuminate\Support\HtmlString(
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

                                    return new \Illuminate\Support\HtmlString($content);
                                } catch (\Exception $e) {
                                    // Log the actual error for debugging
                                    \Illuminate\Support\Facades\Log::error('Schedule information failed: '.$e->getMessage(), [
                                        'exception' => $e,
                                        'machine_id' => $machineId,
                                        'factory_id' => $factoryId,
                                        'start_time' => $startTime,
                                        'end_time' => $endTime,
                                    ]);

                                    return new \Illuminate\Support\HtmlString(
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
                Forms\Components\Section::make('Operator Scheduling Information')
                    ->schema([
                        Forms\Components\Placeholder::make('operator_status')
                            ->label('Current Operator Status')
                            ->content(function (callable $get) {
                                $operatorId = $get('operator_id');
                                $startTime = $get('start_time');
                                $endTime = $get('end_time');
                                $status = $get('status');
                                $factoryId = Auth::user()->factory_id ?? null;

                                if (! $operatorId || ! $factoryId) {
                                    return new \Illuminate\Support\HtmlString('<div class="text-gray-500 italic">Select an operator to see status</div>');
                                }

                                try {
                                    // Get operator details and ensure it belongs to the same factory
                                    $operator = \App\Models\Operator::where('id', $operatorId)
                                        ->where('factory_id', $factoryId)
                                        ->with(['user', 'shift'])
                                        ->first();

                                    if (! $operator) {
                                        return new \Illuminate\Support\HtmlString('<div class="text-red-600">⚠️ Operator not found or belongs to different factory</div>');
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
                                            $estimatedCompletion = \Carbon\Carbon::parse($currentWO->end_time)->format('M d, H:i');

                                            return new \Illuminate\Support\HtmlString(
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

                                            return new \Illuminate\Support\HtmlString(
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
                                                return new \Illuminate\Support\HtmlString(
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

                                    return new \Illuminate\Support\HtmlString(
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
                                } catch (\Exception $e) {
                                    // Log the actual error for debugging
                                    \Illuminate\Support\Facades\Log::error('Operator status check failed: '.$e->getMessage(), [
                                        'exception' => $e,
                                        'operator_id' => $operatorId,
                                        'factory_id' => $factoryId,
                                        'status' => $status,
                                    ]);

                                    return new \Illuminate\Support\HtmlString(
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

                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->required()
                    ->options(function ($record) {
                        if ($record) {
                            $user = Auth::user(); // Get the logged-in user
                            $currentStatus = $record->status; // Get the current status

                            if ($user->hasRole('Operator')) {
                                if ($currentStatus === 'Assigned') {
                                    return ['Start' => 'Start']; // Only "Start" should be visible
                                } elseif ($currentStatus === 'Start') {
                                    return [
                                        'Hold' => 'Hold',
                                        'Completed' => 'Completed',
                                    ]; // Show "Hold" and "Completed"
                                } elseif ($currentStatus === 'Hold') {
                                    return ['Start' => 'Start']; // Only "Start" should be visible
                                } elseif ($currentStatus === 'Completed') {
                                    return ['Completed' => 'Completed']; // Only "Start" should be visible
                                }
                            }
                        }

                        // Default options for non-operators
                        return [
                            'Assigned' => 'Assigned',
                            'Start' => 'Start',
                            'Hold' => 'Hold',
                            'Completed' => 'Completed',
                        ];
                    })
                    ->rules([
                        function (callable $get) {
                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                // Only validate when trying to transition to 'Start' status
                                if ($value === 'Start') {
                                    $machineId = $get('machine_id');
                                    $factoryId = Auth::user()->factory_id ?? null;
                                    $recordId = $get('id');

                                    if ($machineId && $factoryId) {
                                        $startValidation = WorkOrder::validateStartStatusTransition($machineId, $factoryId, $recordId);

                                        if (! $startValidation['can_start']) {
                                            $fail($startValidation['message']);
                                        }
                                    }
                                }
                            };
                        },
                    ])
                    ->reactive()
                    ->afterStateUpdated(function ($set, $get, $livewire, $record, $state) {
                        $status = $get('status');
                        if ($status !== 'Hold') {
                            $livewire->data['hold_reason_id'] = null;
                            $set('hold_reason_id', null);
                            if ($record) {
                                $record->hold_reason_id = null;
                                $record->save();
                            }
                        }
                        if ($status === 'Start' && $record) {
                            $existingLog = $record->workOrderLogs()->where('status', 'Start')->first();
                            if (! $existingLog) {
                                $record->createWorkOrderLog('Start');
                            }
                        }

                        // Trigger machine status update when status changes
                        if ($state && $get('machine_id')) {
                            self::validateMachineScheduling($get, $set);
                        }
                    }),

                Forms\Components\TextInput::make('material_batch')
                    ->label('Material Batch ID')
                    ->required(fn ($get, $record) => $get('status') === 'Start' && ! $record?->material_batch)
                    ->visible(fn ($get) => in_array($get('status'), ['Start', 'Hold', 'Completed']))
                    ->disabled(fn ($record) => $record && $record->material_batch)
                    ->helperText(
                        fn ($get, $record) => $get('status') === 'Start' && ! $record?->material_batch
                            ? 'Material Batch ID is required when starting the work order'
                            : null
                    ),

                Forms\Components\Select::make('hold_reason_id')
                    ->label('Hold Reason')
                    ->relationship('holdReason', 'description', function ($query) {
                        return $query->where('factory_id', auth()->user()->factory_id);
                    })
                    ->visible(fn ($get) => in_array($get('status'), ['Hold']))
                    ->reactive()
                    ->required(fn ($get) => in_array($get('status'), ['Hold']))
                    ->columnSpanFull(),

                Forms\Components\Section::make('Quantities')
                    ->schema([
                        Forms\Components\Repeater::make('quantities')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('ok_quantity')
                                            ->label('OK Quantity')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->disabled(fn ($record) => $record && $record->exists),
                                        Forms\Components\TextInput::make('scrapped_quantity')
                                            ->label('Scrapped Quantity')
                                            ->numeric()
                                            ->required()
                                            ->default(0)
                                            ->disabled(fn ($record) => $record && $record->exists),
                                        Forms\Components\Select::make('reason_id')
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
                                fn (array $state): ?string => $state['ok_quantity'] > 0 || $state['scrapped_quantity'] > 0
                                    ? "OK: {$state['ok_quantity']}, Scrapped: {$state['scrapped_quantity']}"
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

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('ok_qtys')
                                    ->label('Total OK Quantities')
                                    ->default(0)
                                    ->readonly()
                                    ->visible(fn ($get) => in_array($get('status'), ['Hold', 'Completed'])),

                                Forms\Components\TextInput::make('scrapped_qtys')
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
                Tables\Columns\TextColumn::make('unique_id')
                    ->label('Unique ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('bom.purchaseorder.partnumber.description')
                    ->label('BOM')
                    ->hidden(! $isAdminOrManager)
                    ->toggleable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('bom.purchaseorder.partnumber.partnumber')
                    ->label('Part Number')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('bom.purchaseorder.partnumber.revision')
                    ->label('Revision'),
                Tables\Columns\TextColumn::make('machine.name')
                    ->label('Machine')
                    ->formatStateUsing(fn ($record) => "{$record->machine->assetId}")
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('operator.user.first_name')
                    ->label('Operator')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('qty')
                    ->label('Qty')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Assigned' => 'gray',
                        'Start' => 'warning',
                        'Hold' => 'danger',
                        'Completed' => 'success',
                        'Closed' => 'info',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('start_time')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('end_time')
                    ->date()
                    ->sortable()
                    ->toggleable()->formatStateUsing(function ($state, $record) {
                        // Format the date as usual
                        return \Carbon\Carbon::parse($state)->format('d M Y H:i');
                    })
                    ->extraAttributes(function ($record) {
                        // Check if BOM exists and has a lead_time
                        if ($record->bom && $record->bom->lead_time && $record->end_time) {
                            $plannedEnd = \Carbon\Carbon::parse($record->end_time);
                            $bomLead = \Carbon\Carbon::parse($record->bom->lead_time)->endOfDay();
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
                            $plannedEnd = \Carbon\Carbon::parse($record->end_time);
                            $bomLead = \Carbon\Carbon::parse($record->bom->lead_time)->endOfDay();
                            if ($plannedEnd->greaterThan($bomLead)) {
                                return 'BOM Target Completion Time: '.\Carbon\Carbon::parse($record->bom->lead_time)->format('d M Y');
                            }
                        }

                        return null;
                    }),
                Tables\Columns\TextColumn::make('ok_qtys')
                    ->label('OK Qtys')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('scrapped_qtys')
                    ->label('KO Qtys')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
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
                Tables\Filters\TrashedFilter::make(),

            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                        ->visible(
                            fn ($record) => (auth()->user()->hasRole('Operator') && $record->status !== 'Closed') ||
                                $isAdminOrManager
                        ),
                    ViewAction::make()->hiddenLabel(),
                    Action::make('Alert Manager')
                        ->visible(fn () => Auth::check() && Auth::user()->hasRole('Operator'))
                        ->form([
                            Forms\Components\Textarea::make('comments')
                                ->label('Comments')
                                ->required(),
                            Forms\Components\Select::make('priority')
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
                        ->form([
                            Forms\Components\Textarea::make('comments')
                                ->label('Comments')
                                ->required(),
                            Forms\Components\Select::make('priority')
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
                ]),
            ], position: ActionsPosition::BeforeColumns)
            ->headerActions([])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListWorkOrders::route('/'),
            'create' => Pages\CreateWorkOrder::route('/create'),
            'edit' => Pages\EditWorkOrder::route('/{record}/edit'),
            'view' => Pages\ViewWorkOrder::route('/{record}'),
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
            \App\Filament\Admin\Resources\WorkOrderResource\Widgets\WorkOrderProgress::class,
            \App\Filament\Admin\Resources\WorkOrderResource\Widgets\SimpleWorkOrderGantt::class,
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
                        \Carbon\Carbon::parse($conflict['planned_start'])->format('M d, H:i').
                        ' to '.\Carbon\Carbon::parse($conflict['planned_end'])->format('M d, H:i');
                }

                return 'Machine scheduling conflict detected: '.implode('; ', $conflictMessages);
            }
        } catch (\Exception $e) {
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
                        \Carbon\Carbon::parse($conflict['planned_start'])->format('M d, H:i').
                        ' to '.\Carbon\Carbon::parse($conflict['planned_end'])->format('M d, H:i');
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
                                    \Carbon\Carbon::parse($warning['estimated_completion'])->format('M d, H:i').')')
                                ->warning()
                                ->send();
                        }
                    }
                }
                // Removed the success notification to avoid spam
            }
        } catch (\Exception $e) {
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

        } catch (\Exception $e) {
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
                                \Carbon\Carbon::parse($conflict['planned_start'])->format('M d, H:i').
                                ' to '.\Carbon\Carbon::parse($conflict['planned_end'])->format('M d, H:i');
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
        } catch (\Exception $e) {
            // Handle any validation errors gracefully
            Notification::make()
                ->title('Validation Error')
                ->body('Unable to validate operator scheduling: '.$e->getMessage())
                ->warning()
                ->send();
        }
    }
}
