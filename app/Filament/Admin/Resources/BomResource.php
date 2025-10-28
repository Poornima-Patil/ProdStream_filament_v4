<?php

namespace App\Filament\Admin\Resources;

use App\Enums\BomStatus;
use App\Filament\Admin\Resources\BomResource\Pages\CreateBom;
use App\Filament\Admin\Resources\BomResource\Pages\EditBom;
use App\Filament\Admin\Resources\BomResource\Pages\ListBoms;
use App\Filament\Admin\Resources\BomResource\Pages\ViewBom;
use App\Models\Bom;
use App\Models\MachineGroup;
use App\Models\OperatorProficiency;
use App\Models\PartNumber;
use App\Models\PurchaseOrder;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class BomResource extends Resource
{
    protected static ?string $model = Bom::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Process Operations';

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([

                Select::make('part_number_id')
                    ->label('Partnumber')
                    ->options(function () {
                        $factoryId = Auth::user()->factory_id; // Get the factory ID of the logged-in user

                        return PartNumber::where('factory_id', $factoryId)
                            ->get()
                            ->mapWithKeys(function ($partNumber) {
                                return [$partNumber->id => $partNumber->partnumber.' - '.$partNumber->revision];
                            });
                    })
                    ->required()
                    ->searchable()
                    ->reactive()
                    ->preload()
                    ->formatStateUsing(function ($record) {
                        if ($record && $record->purchaseOrder) {
                            return $record->purchaseOrder->partnumber->id ?? null;
                        }

                        return null; // Return null if the part number doesn't exist
                    }),

                Select::make('purchase_order_id')
                    ->label('Sales Order line')
                    ->options(function (callable $get) {
                        $factoryId = Auth::user()->factory_id; // Get the factory ID of the logged-in user
                        $partNumberId = $get('part_number_id'); // Get the selected PartNumber ID

                        if (! $partNumberId) {
                            return [];
                        }

                        // Query Purchase Orders based on the selected PartNumber and Factory
                        return PurchaseOrder::where('part_number_id', $partNumberId)
                            ->where('factory_id', $factoryId)
                            ->get()
                            ->mapWithKeys(function ($purchaseOrder) {
                                $description = $purchaseOrder->partNumber->description ?? 'No description available';

                                return [$purchaseOrder->id => "Sales Order ID: {$purchaseOrder->unique_id} - {$description}"];
                            });
                    })
                    ->required()
                    ->reactive(),

                SpatieMediaLibraryFileUpload::make('requirement_pkg')
                    ->multiple()
                    ->preserveFilenames()
                    ->collection('requirement_pkg')
                    ->required(),

                SpatieMediaLibraryFileUpload::make('process_flowchart')
                    ->multiple()
                    ->preserveFilenames()
                    ->collection('process_flowchart')
                    ->required(),

                Select::make('machine_group_id')
                    ->label('Machine Group')
                    ->options(function () {
                        $factoryId = Auth::user()->factory_id;

                        return MachineGroup::where('factory_id', $factoryId)
                            ->pluck('group_name', 'id');
                    })->required(),

                Select::make('operator_proficiency_id')
                    ->label('Proficiency')
                    ->options(function () {
                        $factoryId = Auth::user()->factory_id; // Adjust this based on how you get the factory_id

                        return OperatorProficiency::where('factory_id', $factoryId)
                            ->pluck('proficiency', 'id');
                    })->required(),

                DatePicker::make('lead_time')
                    ->label('Target Completion Time')
                    ->helperText(function (callable $get) {
                        $purchaseOrderId = $get('purchase_order_id');
                        if (! $purchaseOrderId) {
                            return 'Select a Sales Order line to view PO Delivery Date.';
                        }
                        $po = PurchaseOrder::find($purchaseOrderId);
                        if ($po && $po->delivery_target_date) {
                            return 'PO Delivery Date: '.Carbon::parse($po->delivery_target_date)->format('d M Y');
                        }
                    })
                    ->minDate(fn (string $operation): ?Carbon => $operation === 'create' ? now()->startOfDay() : null) // Only allow today or future dates during creation
                    ->required()
                    ->reactive(),

                Select::make('status')
                    ->options(
                        collect(BomStatus::cases())
                            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                            ->toArray()
                    )
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function (callable $get, $record) {
                        if ($get('status') === BomStatus::Complete->value) {
                            self::close_WO($record);
                        }
                    }),
            ]);
    }

    protected static function close_WO(Bom $record)
    {
        // Fetch all work orders associated with the given BOM
        $workOrders = WorkOrder::where('bom_id', $record->id)->get();

        // Update the status of all fetched work orders to 'Closed'
        foreach ($workOrders as $workOrder) {
            $workOrder->update(['status' => 'Closed']);
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('unique_id')
                    ->label('Unique ID')
                    ->searchable()
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('purchaseorder.partnumber.description')
                    ->label('Description')
                    ->searchable()
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('purchaseorder.partnumber.partnumber')
                    ->label('PartNumber')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('purchaseorder.partnumber.revision')
                    ->label('Revision')
                    ->toggleable(),

                TextColumn::make('machineGroup.group_name')
                    ->label('Machine Group')
                    ->toggleable(),

                TextColumn::make('operatorproficiency.proficiency')
                    ->label('Operator Proficiency')
                    ->toggleable(),

                TextColumn::make('lead_time')
                    ->label('Target Completion Time')
                    ->formatStateUsing(function ($state) {
                        return $state ? Carbon::parse($state)->format('d M Y') : '-';
                    })->extraAttributes(function ($record) {
                        // Check if BOM and PurchaseOrder exist and have the relevant dates
                        if (
                            $record &&
                            $record->lead_time &&
                            $record->purchaseOrder &&
                            $record->purchaseOrder->delivery_target_date
                        ) {
                            $leadTime = Carbon::parse($record->lead_time);
                            $deliveryTarget = Carbon::parse($record->purchaseOrder->delivery_target_date)->endOfDay();
                            if ($leadTime->greaterThan($deliveryTarget)) {
                                return [
                                    'style' => 'background-color: #FCA5A5; cursor: pointer;',
                                ];
                            }
                        }

                        return [];
                    })
                    ->tooltip(function ($record) {
                        if (
                            $record &&
                            $record->purchaseOrder &&
                            $record->purchaseOrder->delivery_target_date
                        ) {
                            return 'Sales Order Line Target Completion Date: '.
                                Carbon::parse($record->purchaseOrder->delivery_target_date)->format('d M Y');
                        }

                        return null;
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        1 => 'Active',
                        0 => 'Inactive',
                        2 => 'Complete',
                        default => 'Unknown',
                    })
                    ->toggleable(),
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

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBoms::route('/'),
            'create' => CreateBom::route('/create'),
            'edit' => EditBom::route(path: '/{record}/edit'),
            'view' => ViewBom::route(path: '/{record}/'),

        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->orderByDesc('created_at'); // Ensures latest records appear first
    }
}
