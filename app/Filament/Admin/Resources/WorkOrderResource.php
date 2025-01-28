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
                    ->disabled(! $isAdminOrManager)
                    ->afterStateUpdated(function(callable $get, callable $set) {
                        $bomId = $get('bom_id');

                        $bom = \App\Models\Bom::find($bomId);
                        $set('machine_id', $bom->machine_id);
                       
                    }),

                    Forms\Components\TextInput::make('machine_id')
                    ->readonly(),
                    
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
                    ->label('Start Time')
                    ->required(fn (callable $get) => in_array($get('status'), ['Hold', 'Completed'])),
                Forms\Components\DateTimePicker::make('end_time')
                    ->label('End Time')
                    ->required(fn (callable $get) => in_array($get('status'), ['Hold', 'Completed'])),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(function () {
                        // Check if the logged-in user's role is 'Operator'
                        $user = Auth::user(); // Adjust based on how roles are stored in your app

                        // Define default status options
                        $options = [
                            'Assigned' => 'Assigned',
                            'Start' => 'Start',
                            'Hold' => 'Hold',
                            'Completed' => 'Completed',
                        ];

                        // If the user is an Operator, exclude 'Assigned' from the options
                        if ($user->hasRole('Operator')) {
                            unset($options['Assigned']);
                        }

                        return $options;
                    })
                    ->reactive(),
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
                    ->searchable(),
                Tables\Columns\TextColumn::make('qty'),
                Tables\Columns\TextColumn::make('status'),
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
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make()
                    ->hiddenLabel(),
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
                        TextEntry::make('machine.name')->label('Machine'),
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
                        TextEntry::make('start_time')->label('Start Time'),
                        TextEntry::make('end_time')->label('End Time'),
                        TextEntry::make('ok_qtys')->label('OK Quantities'),
                        TextEntry::make('scrapped_qtys')->label('Scrapped Quantities'),
                        TextEntry::make('scrappedReason.description')->label('Scrapped Reason'),
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
}
