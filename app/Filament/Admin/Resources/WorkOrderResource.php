<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WorkOrderResource\Pages;
use App\Models\Operator;
use App\Models\User;
use App\Models\WorkOrder;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\Machine;
use Filament\Tables\Filters\SelectFilter;
use App\Models\InfoMessage;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\CreateAction;
use Filament\Forms\Components\Select;
use App\Models\WorkOrderLog;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\ActionGroup;
use Filament\Forms\Components\Grid;

class WorkOrderResource extends Resource
{
    protected static ?string $model = Workorder::class;

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard';

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
                    ->required()
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
                        if (! $bom || !$bom->machine_group_id) {
                            return []; // BOM not found or has no associated machine group
                        }
                    
                        // Fetch all active machines in the machine group
                        return \App\Models\Machine::where('machine_group_id', $bom->machine_group_id)
                        ->active()
                        ->get()
                        ->mapWithKeys(fn ($machine) => [
                            (int) $machine->id => "Asset ID: {$machine->assetId} - Name: {$machine->name}"
                        ])
                        ->toArray();
                    })
                    ->reactive()
                    ->required()
                    ->disabled(!$isAdminOrManager),
                    
                    
    Forms\Components\TextInput::make('qty')
    ->label('Quantity')
    ->required()
    ->disabled(! $isAdminOrManager)
    ->reactive()
    ->afterStateUpdated(function (callable $get, callable $set) {
        $partNumberId = $get('part_number_id'); // Get selected Part Number ID
        $qty = (int) $get('qty'); // Get entered quantity

        if (!$partNumberId || !$qty) {
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

        if (!$cycleTimeInSeconds) {
            $set('time_to_complete', '00:00:00'); // Default if no cycle time is set
            return;
        }

        // Calculate total time
        $totalSeconds = $cycleTimeInSeconds * $qty;
        $set('time_to_complete', self::convertSecondsToTime($totalSeconds));
    }),

                   
                Forms\Components\Select::make('operator_id')
                    ->label('Operator')
                    ->disabled(!$isAdminOrManager)
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
                            ->with('user') // Get the associated user (operator)
                            ->get()
                            ->mapWithKeys(function ($operator) {
                                return [$operator->id => $operator->user->first_name . ' ' . $operator->user->last_name];
                            });
                    })
                    ->searchable()
                    ->required(),

                    

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

                        if (!$partNumberId || !$qty) {
                            $set('time_to_complete', '00:00:00');
                            return;
                        }

                        // Get the cycle time from the part number
                        $cycleTimeInSeconds = \App\Models\PartNumber::where('id', $partNumberId)->value('cycle_time');

                        if (!$cycleTimeInSeconds) {
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
                    ->required(),
                Forms\Components\DateTimePicker::make('end_time')
                    ->label('Planned End Time')
                    ->disabled(! $isAdminOrManager)
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->required()
                    ->options(function ($record) {
                        if($record) {
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
                            }  elseif ($currentStatus === 'Completed') {
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
                    ->reactive()
                    ->afterStateUpdated(function ($set, $get, $livewire,$record) {
                        $status = $get('status');
                        if ($status !== 'Hold') {
                            $livewire->data['hold_reason_id'] = NULL;
                            $set('hold_reason_id', NULL);  
                            if($record) {
                           $record->hold_reason_id = NULL;
                           $record->save();
                            }
                        }
                    }),
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
                                                ->relationship('reason', 'description')
                                                ->visible(fn ($get) => $get('scrapped_quantity') > 0)
                                                ->required(fn ($get) => $get('scrapped_quantity') > 0)
                                                ->disabled(fn ($record) => $record && $record->exists),
                                        ])
                                ])
                                ->columns(1)
                                ->defaultItems(1)
                                ->reorderable()
                                ->collapsible()
                                ->itemLabel(fn (array $state): ?string => 
                                    $state['ok_quantity'] > 0 || $state['scrapped_quantity'] > 0 
                                        ? "OK: {$state['ok_quantity']}, Scrapped: {$state['scrapped_quantity']}" 
                                        : null
                                )
                                ->live()
                                ->afterStateUpdated(function ($livewire, $state, $set, $get) {
                                    // Calculate totals from all quantities
                                    $totalOk = 0;
                                    $totalScrapped = 0;
                                    
                                    foreach ($state as $item) {
                                        $totalOk += (int)($item['ok_quantity'] ?? 0);
                                        $totalScrapped += (int)($item['scrapped_quantity'] ?? 0);
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
                                    
                                    if (!$latestLog) {
                                        // If no log exists, create one
                                        $latestLog = $workOrder->createWorkOrderLog($workOrder->status);
                                    }
                                    
                                    // Add the work_order_log_id to the state
                                    return array_merge($state, ['work_order_log_id' => $latestLog->id]);
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
                Tables\Columns\TextColumn::make('unique_id')->label('Unique ID')->searchable(),
                Tables\Columns\TextColumn::make('bom.purchaseorder.partnumber.description')->label('BOM')
                    ->hidden(! $isAdminOrManager),
                Tables\Columns\TextColumn::make('bom.purchaseorder.partnumber.partnumber')->label('Part Number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('bom.purchaseorder.partnumber.revision')->label('Revision'),
                Tables\Columns\TextColumn::make('machine.name')->label('Machine')
                ->formatStateUsing(fn ($record) => "Asset ID: {$record->machine->assetId} - Name: {$record->machine->name}"),
               
                Tables\Columns\TextColumn::make('operator.user.first_name')->label('Operator')
                    ->hidden(! $isAdminOrManager)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('qty'),
                
                Tables\Columns\TextColumn::make('status')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('start_time'),
                Tables\Columns\TextColumn::make('end_time'),
                Tables\Columns\TextColumn::make('ok_qtys')
                    ->label('OK Quantities')
                    ->sortable(),
                Tables\Columns\TextColumn::make('scrapped_qtys')
                    ->label('Scrapped Quantities')
                    ->sortable(),
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
            ->defaultGroup('status')
            ->groups([
                Tables\Grouping\Group::make('status')
                    ->collapsible(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                SelectFilter::make('status')
                    ->options([
                        'Assigned' => 'Assigned',
                        'Start' => 'Start',
                        'Hold' => 'Hold',
                        'Completed' => 'Completed',
                    ])
                    ->attribute('status'),
            ])
            ->actions([
                ActionGroup::make([
                    EditAction::make()
                    ->visible(fn ($record) => 
                    (auth()->user()->hasRole('Operator') && $record->status !== 'Closed') ||
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
                            self::sendAlert($data,$record);
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
                            self::sendAlert($data,$record);
                        })
                        ->button(),
                ])
            ])
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
            'index' => Pages\ListWorkorders::route('/'),
            'create' => Pages\CreateWorkorder::route('/create'),
            'edit' => Pages\EditWorkorder::route('/{record}/edit'),
            'view' => Pages\ViewWorkorder::route('/{record}/'),
        ];
    }

    public static function infoList(InfoList $infoList): InfoList
    {
        $user = Auth::user();
        $isAdminOrManager = $user && in_array($user->role, ['manager', 'admin']);

        return $infoList
            ->schema([
                // Section 1: BOM, Quantity, Machines, Operator
                Section::make('General Information')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('bom.description')
                            ->label('BOM')
                            ->hidden(! $isAdminOrManager),
                        TextEntry::make('qty')->label('Quantity'),
                      
                        TextEntry::make('machine.name')->label('Machine')
                        ->formatStateUsing(function ($record) {
                            // Ensure the machine relationship is loaded and exists
                            if ($record && $record->machine) {
                                $machine = $record->machine; // Access the machine relationship
                                return "{$machine->assetId} - {$machine->name}"; // Display asset_id and name
                            }
                            return 'No Machine'; // Default value if no machine found
                        }),
                        TextEntry::make('operator.user.first_name')
                            ->label('Operator')
                            ->hidden(! $isAdminOrManager),
                    ])->columns(2),

                // Section 2: Remaining fields
                Section::make('Details')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('unique_id')->label('Unique ID'),
                        TextEntry::make('bom.purchaseorder.partnumber.partnumber')->label('Part Number'),
                        TextEntry::make('bom.purchaseorder.partnumber.revision')->label('Revision'),
                        TextEntry::make('status')->label('Status'),
                        TextEntry::make('start_time')->label('Planned Start Time'),
                        TextEntry::make('end_time')->label('Planned End Time'),
                        TextEntry::make('total_ok_quantity')->label('OK Quantities'),
                        TextEntry::make('total_scrapped_quantity')->label('Scrapped Quantities'),
                        TextEntry::make('scrappedReason.description')->label('Scrapped Reason'),
                        TextEntry::make('material_batch')->label('Material Batch ID'),
                    ])->columns(2),

                Section::make('Quantities Details')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('quantities_table')
                            ->label('Quantities')
                            ->state(function ($record) {
                                $record->load('quantities.reason');

                                $data = $record->quantities->map(function ($item) {
                                    return [
                                        'quantity' => $item->quantity,
                                        'type' => ucfirst($item->type),
                                        'reason_description' => $item->type === 'scrapped' ? ($item->reason->description ?? '') : '',
                                    ];
                                });

                                // Render the data as an HTML table
                                $html = '<table class="table-auto w-full text-left border border-gray-300">';
                                $html .= '<thead><tr><th class="border px-2 py-1">Type</th><th class="border px-2 py-1">Quantity</th><th class="border px-2 py-1">Reason</th></tr></thead>';
                                $html .= '<tbody>';
                                foreach ($data as $row) {
                                    $html .= '<tr>';
                                    $html .= '<td class="border px-2 py-1">'.e($row['type']).'</td>';
                                    $html .= '<td class="border px-2 py-1">'.e($row['quantity']).'</td>';
                                    $html .= '<td class="border px-2 py-1">'.e($row['reason_description']).'</td>';
                                    $html .= '</tr>';
                                }
                                $html .= '</tbody></table>';

                                return $html;
                            })
                            ->html(),
                    ])
                    ->columns(1),

                    Section::make('Documents')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('requirement_pkg')
                        ->state(function ($record) {
                            // Access the related BOM
                            $bom = $record->bom;
                            if (!$bom) {
                                return 'No BOM associated'; // Fallback if no BOM is linked
                            }
            
                            // Fetch media from the BOM's 'requirement_pkg' collection
                            $mediaItems = $bom->getMedia('requirement_pkg');
                            if ($mediaItems->isEmpty()) {
                                return 'No files uploaded'; // Fallback if no files exist
                            }
            
                            return $mediaItems->map(function ($media) {
                                // Use target="_blank" to open in a new tab
                                return "<a href='{$media->getUrl()}' target='_blank' class='block text-blue-500 underline'>{$media->file_name}</a>"; 
                            })->implode('<br>'); // Concatenate links with line breaks
                        })
                        ->html(), // Enable HTML rendering
            
                    TextEntry::make('process_flowchart')
                        ->state(function ($record) {
                            // Access the related BOM
                            $bom = $record->bom;
                            if (!$bom) {
                                return 'No BOM associated'; // Fallback if no BOM is linked
                            }
            
                            // Fetch media from the BOM's 'process_flowchart' collection
                            $mediaItems = $bom->getMedia('process_flowchart');
                            if ($mediaItems->isEmpty()) {
                                return 'No files uploaded'; // Fallback if no files exist
                            }
            
                            return $mediaItems->map(function ($media) {
                                // Use target="_blank" to open in a new tab
                                return "<a href='{$media->getUrl()}' target='_blank' class='block text-blue-500 underline'>{$media->file_name}</a>"; 
                            })->implode('<br>'); // Concatenate links with line breaks
                        })
                        ->html(),
                    ])->columns(1),

                
                        
                    Section::make('Work Order Logs')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('work_order_logs_table')
                            ->label('Work Order Logs')
                            ->state(function ($record) {
                                // Load necessary relationships
                                $record->load(['workOrderLogs.user', 'quantities']);
                                $quantities = $record->quantities;
                                $quantitiesIndex = 0;

                                // Create the HTML table
                                $html = '<table class="table-auto w-full text-left border border-gray-300">';
                                $html .= '<thead class="bg-gray-200"><tr>';
                                $html .= '<th class="border px-2 py-1">Status</th>';
                                $html .= '<th class="border px-2 py-1">User</th>';
                                $html .= '<th class="border px-2 py-1">Timestamp</th>';
                                $html .= '<th class="border px-2 py-1">Quantities</th>';
                                $html .= '<th class="border px-2 py-1">Documents</th>';
                                $html .= '</tr></thead><tbody>';

                                foreach ($record->workOrderLogs as $log) {
                                    $status = $log->status;
                                    $user = $log->user ? $log->user->first_name . ' ' . $log->user->last_name : 'N/A';
                                    $timestamp = $log->created_at->format('Y-m-d H:i:s');
                                    $quantityInfo = '';
                                    $documents = '';

                                    if ($status === 'Hold') {
                                        if (isset($quantities[$quantitiesIndex])) {
                                            $quantity = $quantities[$quantitiesIndex];
                                            $quantityInfo = "OK: {$quantity->ok_quantity}, Scrapped: {$quantity->scrapped_quantity}";
                                            if ($quantity->scrapped_quantity > 0) {
                                                $quantityInfo .= " (Reason: {$quantity->reason->description})";
                                            }

                                            // Get QR code and PDF links
                                            $qrCodeMedia = $quantity->getMedia('qr_code')->first();

                                            if ($qrCodeMedia) {
                                                $documents .= "<a href='{$qrCodeMedia->getUrl()}' download='qr_code.png' class='text-blue-500 underline'>Download QR Code</a>";
                                            }

                                            $quantitiesIndex++;
                                        }
                                    }

                                    $html .= '<tr>';
                                    $html .= '<td class="border px-2 py-1">' . e($status) . '</td>';
                                    $html .= '<td class="border px-2 py-1">' . e($user) . '</td>';
                                    $html .= '<td class="border px-2 py-1">' . e($timestamp) . '</td>';
                                    $html .= '<td class="border px-2 py-1">' . e($quantityInfo) . '</td>';
                                    $html .= '<td class="border px-2 py-1">' . $documents . '</td>';
                                    $html .= '</tr>';
                                }

                                $html .= '</tbody></table>';
                                return $html;
                            })
                            ->html(),
                    ])
                    ->columns(1),
                

                            Section::make('Work Order Info Messages')
    ->collapsible()
    ->schema([
        TextEntry::make('info_messages_table')
            ->label('Info Messages')
            ->state(function ($record) {
                // Ensure info messages are loaded with user details
                $record->load('infoMessages.user');

                $messages = $record->infoMessages->map(function ($message) {
                    return [
                        'user' => $message->user->getFilamentname() ?? 'N/A',
                        'message' => $message->message,
                        'priority' => ucfirst($message->priority), // Capitalize first letter
                        'sent_at' => $message->created_at->format('Y-m-d H:i:s'),
                    ];
                });

                // Create the HTML table
                $html = '<table class="table-auto w-full text-left border border-gray-300">';
                $html .= '<thead class="bg-gray-200"><tr>';
                $html .= '<th class="border px-2 py-1">User</th>';
                $html .= '<th class="border px-2 py-1">Message</th>';
                $html .= '<th class="border px-2 py-1">Priority</th>';
                $html .= '<th class="border px-2 py-1">Sent At</th>';
                $html .= '</tr></thead><tbody>';

                foreach ($messages as $message) {
                    $html .= '<tr>';
                    $html .= '<td class="border px-2 py-1">' . e($message['user']) . '</td>';
                    $html .= '<td class="border px-2 py-1">' . e($message['message']) . '</td>';
                    $html .= '<td class="border px-2 py-1 font-bold text-' . ($message['priority'] === 'High' ? 'red-500' : ($message['priority'] === 'Medium' ? 'yellow-500' : 'green-500')) . '">' . e($message['priority']) . '</td>';
                    $html .= '<td class="border px-2 py-1">' . e($message['sent_at']) . '</td>';
                    $html .= '</tr>';
                }

                $html .= '</tbody></table>';
                return $html;
            })
            ->html(), // Enable raw HTML rendering
    ])
    ->columns(1),

                    ]);
            

            
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
    if (!$seconds) return '00:00:00';

    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $remainingSeconds = $seconds % 60;

    return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
}


}
