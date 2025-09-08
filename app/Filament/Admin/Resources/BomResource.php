<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BomResource\Pages;
use App\Models\Bom;
use App\Models\WorkOrder;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use App\Enums\BomStatus;


class BomResource extends Resource
{
    protected static ?string $model = Bom::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Process Operations';

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Select::make('part_number_id')
                    ->label('Partnumber')
                    ->options(function () {
                        $factoryId = Auth::user()->factory_id; // Get the factory ID of the logged-in user

                        return \App\Models\PartNumber::where('factory_id', $factoryId)
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

                Forms\Components\Select::make('purchase_order_id')
                    ->label('Sales Order line')
                    ->options(function (callable $get) {
                        $factoryId = Auth::user()->factory_id; // Get the factory ID of the logged-in user
                        $partNumberId = $get('part_number_id'); // Get the selected PartNumber ID

                        if (! $partNumberId) {
                            return [];
                        }

                        // Query Purchase Orders based on the selected PartNumber and Factory
                        return \App\Models\PurchaseOrder::where('part_number_id', $partNumberId)
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

                Forms\Components\Select::make('machine_group_id')
                    ->label('Machine Group')
                    ->options(function () {
                        $factoryId = Auth::user()->factory_id;

                        return \App\Models\MachineGroup::where('factory_id', $factoryId)
                            ->pluck('group_name', 'id');
                    })->required(),

                Forms\Components\Select::make('operator_proficiency_id')
                    ->label('Proficiency')
                    ->options(function () {
                        $factoryId = Auth::user()->factory_id; // Adjust this based on how you get the factory_id

                        return \App\Models\OperatorProficiency::where('factory_id', $factoryId)
                            ->pluck('proficiency', 'id');
                    })->required(),

Forms\Components\DatePicker::make('lead_time')
    ->label('Target Completion Time')
    ->helperText(function (callable $get) {
        $purchaseOrderId = $get('purchase_order_id');
        if (!$purchaseOrderId) {
            return 'Select a Sales Order line to view PO Delivery Date.';
        }
        $po = \App\Models\PurchaseOrder::find($purchaseOrderId);
        if ($po && $po->delivery_target_date) {
            return 'PO Delivery Date: ' . \Carbon\Carbon::parse($po->delivery_target_date)->format('d M Y');
        }
    })
     ->minDate(now()->startOfDay()) // <-- Only allow today or future dates
    ->required()
    ->reactive(),

Forms\Components\Select::make('status')
    ->options(
        collect(BomStatus::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
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
                Tables\Columns\TextColumn::make('unique_id')
                    ->label('Unique ID')
                    ->searchable()
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('purchaseorder.partnumber.description')
                    ->label('Description')
                    ->searchable()
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('purchaseorder.partnumber.partnumber')
                    ->label('PartNumber')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('purchaseorder.partnumber.revision')
                    ->label('Revision')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('machineGroup.group_name')
                    ->label('Machine Group')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('operatorproficiency.proficiency')
                    ->label('Operator Proficiency')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('lead_time')
    ->label('Target Completion Time')
    ->formatStateUsing(function ($state) {
        return $state ? \Carbon\Carbon::parse($state)->format('d M Y') : '-';
    })  ->extraAttributes(function ($record) {
        // Check if BOM and PurchaseOrder exist and have the relevant dates
        if (
            $record &&
            $record->lead_time &&
            $record->purchaseOrder &&
            $record->purchaseOrder->delivery_target_date
        ) {
            $leadTime = \Carbon\Carbon::parse($record->lead_time);
            $deliveryTarget = \Carbon\Carbon::parse($record->purchaseOrder->delivery_target_date)->endOfDay();
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
            return 'Sales Order Line Target Completion Date: ' .
                \Carbon\Carbon::parse($record->purchaseOrder->delivery_target_date)->format('d M Y');
        }
        return null;
    }),


                Tables\Columns\TextColumn::make('status')
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
            Tables\Filters\TrashedFilter::make(),
        ])

            ->actions([
            ActionGroup::make([
                    EditAction::make()->label('Edit'),
                    ViewAction::make()->label('View'),
            ]),
        ], position: ActionsPosition::BeforeColumns)
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
            'index' => Pages\ListBoms::route('/'),
            'create' => Pages\CreateBom::route('/create'),
            'edit' => Pages\EditBom::route(path: '/{record}/edit'),
            'view' => Pages\ViewBom::route(path: '/{record}/'),

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
