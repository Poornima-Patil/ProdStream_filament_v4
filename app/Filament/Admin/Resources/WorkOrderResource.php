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

use Filament\Notifications\Notification;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\CreateAction;
use Filament\Forms\Components\Select;
use App\Models\WorkOrderLog;

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

        // Get the associated machines for the selected BOM
        $bom = \App\Models\Bom::find($bomId);
        $machine = $bom->machine; // Assuming the BOM has a 'machines' relationship

        return $machine 
            ? [$machine->id => "Asset ID: {$machine->assetId} - Name: {$machine->name}"] 
            : [];

    })
    ->reactive() // Make this reactive to BOM selection
    ->required(),
                    
                Forms\Components\TextInput::make('qty')
                    ->label('Quantity')
                    ->required()
                    ->disabled(! $isAdminOrManager),

                   
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
                            ->with('user') // Get the associated user (operator)
                            ->get()
                            ->mapWithKeys(function ($operator) {
                                return [$operator->id => $operator->user->first_name];
                            });
                    })
                    ->searchable()
                    ->required(),

                
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
                    ->relationship('holdReason', 'description') // Assumes a relationship with the ScrappedReason model
                    ->visible(fn ($get) => in_array($get('status'), ['Hold']))
                    ->reactive()
                    ->required(fn ($get) => in_array($get('status'), ['Hold'])),


                    Forms\Components\TextInput::make('material_batch')
                    ->label('Material batch ID')
                    // Assumes a relationship with the ScrappedReason model
                    ->visible(fn ($get) => in_array($get('status'), ['Start']))
                    ->reactive()
                    ->required(fn ($get) => in_array($get('status'), ['Start'])),


                Forms\Components\TextInput::make('ok_qtys')
                    ->label('Ok Qtys')
                    ->default(0)
                    ->reactive()
                    ->visible(fn ($get) => in_array($get('status'), ['Hold', 'Completed'])),

                Forms\Components\Repeater::make('scrapped_quantities')
                    ->label('Scrapped Quantities')
                    ->relationship('scrappedQuantities') // Assumes the WorkOrder model has a `scrappedQuantities` relationship
                    ->schema([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->required()
                            ->minValue(1)

                            ->reactive() // Trigger updates when the quantity is changed
                            ->afterStateUpdated(function ($livewire, $record, callable $get, callable $set) {
                                $formState = $livewire->data;
                                $scrappedQuantities = $formState['scrapped_quantities'] ?? [];
                                $total = collect($scrappedQuantities)
                                    ->pluck('quantity') // Extract only the quantity values
                                    ->filter(fn ($value) => is_numeric($value)) // Filter out non-numeric values
                                    ->sum();
                                $livewire->data['scrapped_qtys'] = $total;
                                $set('scrapped_qtys', $total);
                            }),

                        Forms\Components\Select::make('reason_id')
                            ->label('Scrapped Reason')
                            ->relationship('reason', 'description') // Assumes a relationship with the ScrappedReason model
                            ->required()
                            ->reactive(),

                    ])
                    ->defaultItems(0) // Optional: Number of default entries to show
                    ->minItems(0) // Optional: Minimum number of entries
                    ->visible(fn ($get) => in_array($get('status'), ['Hold', 'Completed']))
                    ->columnSpan('full')
                    ->afterStateUpdated(function ($livewire, $state, callable $set) {
                        // Recalculate the total whenever the state of the repeater changes (including deletions)
                        $total = collect($state)
                            ->pluck('quantity') // Extract only the quantity values
                            ->filter(fn ($value) => is_numeric($value)) // Include only numeric values
                            ->sum();
                        $set('scrapped_qtys', $total);
                    }),

                Forms\Components\TextInput::make('scrapped_qtys')
                    ->label('Scrapped Qtys')
                    ->default(0)
                    ->readonly() // Make it non-editable
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
        $isAdminOrManager = $user && $user->can(abilities: 'Edit Bom');

        return $table
            ->Columns([
                Tables\Columns\TextColumn::make('unique_id')->label('Unique ID')->searchable(),
                Tables\Columns\TextColumn::make('bom.purchaseorder.partnumber.description')->label('BOM')
                    ->hidden(! $isAdminOrManager),
                Tables\Columns\TextColumn::make('bom.purchaseorder.partnumber.partnumber')->label('Part Number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('bom.purchaseorder.partnumber.revision')->label('Revision'),
                Tables\Columns\TextColumn::make('machine.name')->label('Machine'),
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
                Tables\Columns\TextColumn::make('ok_qtys'),
                Tables\Columns\TextColumn::make('scrapped_qtys'),

            ]
            )
            ->modifyQueryUsing(function (Builder $query) {
                // Check if the authenticated user has the 'operator' role
                $userId = Auth::id();
                $user = User::find($userId);
                if ($user->hasRole('operator')) {
                    // Retrieve the operator record linked to the user
                    $operator = Operator::where('user_id', Auth::id())->first();

                    // Check if the operator and factory_id are valid
                    if ($operator && $user->factory_id) {
                        // Apply filter to the query to include both operator_id and factory_id
                        return $query->where('operator_id', $operator->id)
                            ->where('factory_id', $user->factory_id);
                    }
                }

                // Return the query unfiltered if the user is not an operator or missing required data
                return $query;
            })
            ->defaultGroup('status')
            ->groups([
                Tables\Grouping\Group::make('status')
                    ->collapsible()
                   
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
                ->attribute('status')
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make()
                    ->hiddenLabel(),
            ])
            ->headerActions([
              
            ])
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
                        TextEntry::make('ok_qtys')->label('OK Quantities'),
                        TextEntry::make('scrapped_qtys')->label('Scrapped Quantities'),
                        TextEntry::make('scrappedReason.description')->label('Scrapped Reason'),
                        TextEntry::make('material_batch')->label('Material Batch ID'),
                    ])->columns(2),
                Section::make('Scrapped Quantities Details')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('scrapped_quantities_table')
                            ->label('Scrapped Quantities')
                            ->state(function ($record) {
                                $record->load('scrappedQuantities.reason');

                                $data = $record->scrappedQuantities->map(function ($item) {
                                    return [
                                        'quantity' => $item->quantity,
                                        'reason_description' => $item->reason->description ?? '',
                                    ];
                                });

                                // Render the data as an HTML table
                                $html = '<table class="table-auto w-full text-left border border-gray-300">';
                                $html .= '<thead><tr><th class="border px-2 py-1">Quantity</th><th class="border px-2 py-1">Reason Description</th></tr></thead>';
                                $html .= '<tbody>';
                                foreach ($data as $row) {
                                    $html .= '<tr>';
                                    $html .= '<td class="border px-2 py-1">'.e($row['quantity']).'</td>';
                                    $html .= '<td class="border px-2 py-1">'.e($row['reason_description']).'</td>';
                                    $html .= '</tr>';
                                }
                                $html .= '</tbody></table>';

                                return $html;
                            })
                            ->html(), // Render raw HTML
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
                                        // Ensure logs are loaded with user details
                                        $record->load('workOrderLogs.user');
            
                                        $logs = $record->workOrderLogs->map(function ($log) {
                                            return [
                                                'status' => $log->status,
                                                'user' => $log->user->getFilamentname() ?? 'N/A',
                                                'changed_at' => $log->changed_at->format('Y-m-d H:i:s'),
                                                'ok_qtys' => $log->ok_qtys,
                                                'scrapped_qtys' => $log->scrapped_qtys,
                                                'remaining' => $log->remaining,
                                                'scrapped_reason' => $log->scrappedReason->description ?? 'NA',
                                                'hold_reason'    => $log->holdReason->description ?? 'NA'
                                            ];
                                        });
            
                                        // Create the HTML table
                                        $html = '<table class="table-auto w-full text-left border border-gray-300">';
                                        $html .= '<thead class="bg-gray-200"><tr>';
                                        $html .= '<th class="border px-2 py-1">Status</th>';
                                        $html .= '<th class="border px-2 py-1">User</th>';
                                        $html .= '<th class="border px-2 py-1">Changed At</th>';
                                        $html .= '<th class="border px-2 py-1">OK Qty</th>';
                                        $html .= '<th class="border px-2 py-1">Scrapped Qty</th>';
                                        $html .= '<th class="border px-2 py-1">Remaining</th>';
                                        $html .= '<th class="border px-2 py-1">Scrapped Reason</th>';
                                        $html .= '<th class="border px-2 py-1">Hold Reason</th>';
                                        $html .= '</tr></thead><tbody>';
            
                                        foreach ($logs as $log) {
                                            $html .= '<tr>';
                                            $html .= '<td class="border px-2 py-1">'.e($log['status']).'</td>';
                                            $html .= '<td class="border px-2 py-1">'.e($log['user']).'</td>';
                                            $html .= '<td class="border px-2 py-1">'.e($log['changed_at']).'</td>';
                                            $html .= '<td class="border px-2 py-1">'.e($log['ok_qtys']).'</td>';
                                            $html .= '<td class="border px-2 py-1">'.e($log['scrapped_qtys']).'</td>';
                                            $html .= '<td class="border px-2 py-1">'.e($log['remaining']).'</td>';
                                            $html .= '<td class="border px-2 py-1">'.e($log['scrapped_reason']).'</td>';
                                            $html .= '<td class="border px-2 py-1">'.e($log['hold_reason']).'</td>';

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
            ])->orderByDesc('created_at');

        $user = Auth::user();

        if (Auth::check() && $user->hasRole('Operator')) {
            $userId = Auth::id(); // Get the logged-in user's ID
            $query->whereHas('operator', function ($operatorQuery) use ($userId) {
                $operatorQuery->where('user_id', $userId);

            });
        }

        return $query;
    }
}
