<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\BomResource\Pages;
use App\Models\Bom;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Infolists\Components\Actions\Action;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Html;


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

                                return [$purchaseOrder->id => "Purchase Order ID: {$purchaseOrder->id} - {$description}"];
                            });
                    })
                    ->required()
                    ->reactive(),
                Forms\Components\FileUpload::make('requirement_pkg')
                    ->label(label: 'Requirement Package')
                    ->directory(function () {
                        $tenant = Filament::getTenant();

                        return 'tenants/'.$tenant->id;
                    })
                    ->disk('public')
                    ->multiple()
                    ->preserveFilenames(),

                Forms\Components\FileUpload::make('process_flowchart')
                    ->label(label: 'Process Flowchart')
                    ->directory(function () {
                        $tenant = Filament::getTenant();

                        return 'tenants/'.$tenant->id;
                    })
                    ->disk('public')
                    ->preserveFilenames()
                    ->multiple(),
                
                /*SpatieMediaLibraryFileUpload::make('requirement_pkg')
                    ->multiple()
                    ->preserveFilenames()
                    ->collection('requirement_pkg'),

                    SpatieMediaLibraryFileUpload::make('process_flowchart')
                    ->multiple()
                    ->preserveFilenames()*/

                Forms\Components\Select::make('machine_id')
                    ->label('Machine')
                    ->options(function () {
                        $factoryId = Auth::user()->factory_id;

                        return \App\Models\Machine::where('status', '1')
                            ->where('factory_id', $factoryId)
                            ->pluck('name', 'id');
                })->required(),

                Forms\Components\Select::make('operator_proficiency_id')
                    ->label('Proficiency')
                    ->options(function () {
                        $factoryId = Auth::user()->factory_id; // Adjust this based on how you get the factory_id

                        return \App\Models\OperatorProficiency::where('factory_id', $factoryId)
                            ->pluck('proficiency', 'id');
                    }),

                Forms\Components\DatePicker::make('lead_time')
                    ->label('Target Completion Time'),
                Forms\Components\Select::make('status')->options([
                    '1' => 'Active',
                    '0' => 'InActive',
                ])->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table

        
            ->columns([
                Tables\Columns\TextColumn::make('unique_id')->label('Unique ID')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('purchaseorder.partnumber.description')->label('Description')->searchable()->wrap(),
                Tables\Columns\TextColumn::make('purchaseorder.partnumber.partnumber')->label('PartNumber')
                    ->searchable(),
                Tables\Columns\TextColumn::make('purchaseorder.partnumber.revision')->label('Revision'),
                Tables\Columns\TextColumn::make('requirement_pkg')
                    ->visibleOn('Edit'),
                Tables\Columns\TextColumn::make('process_flowchart')
                    ->visibleOn('Edit'),

                Tables\Columns\TextColumn::make('machine.name'),
                Tables\Columns\TextColumn::make('operatorproficiency.proficiency'),
                Tables\Columns\TextColumn::make('lead_time')->label('Target Completion Time')->wrap(),
                Tables\Columns\IconColumn::make('status')
                    ->boolean(),

            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                Tables\Actions\EditAction::make()
                    ->label('Edit BOM'),
                Tables\Actions\ViewAction::make()
                    ->hiddenLabel(),
                ])->label('Actions')
                ->button(),
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

    public static function infoList(InfoList $infoList): InfoList
    {
        return $infoList
            ->schema([

                Section::make('Purchase Order Infomation')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('unique_id')
                            ->label('Unique ID'),
                        TextEntry::make('purchaseorder.description')
                            ->label('Purchase Order'),
                        TextEntry::make('purchaseorder.partnumber.partnumber')->label('Part Number'),
                        TextEntry::make('purchaseorder.partnumber.revision')->label('Revision'),
                        TextEntry::make('machine.name')
                            ->label('Machine'),
                        TextEntry::make('purchaseorder.partnumber.revision')->label('Revision'),

                    ])->columns(),

                Section::make('Operational Information')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('operatorproficiency.proficiency')
                            ->label('Proficiency'),
                        TextEntry::make('lead_time')->label('Target Completiong time'),
                        IconEntry::make('status')->label('Status'),

                    ])->columns(),
                    Section::make('Documents')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('requirement_pkg')
                        ->label('Download Requirement Package')
                        ->state(fn ($record) => 
                            $record->requirement_pkg 
                                ? collect(json_decode($record->requirement_pkg, true)) // Decode JSON string to an array
                                    ->map(fn ($file) => '<a href="' . Storage::url($file) . '" download class="block text-blue-500 underline">' . basename($file) . '</a>') // Display the file name instead of "Download"
                                    ->implode('<br>') // Join links with line breaks
                                : 'No files uploaded' // Fallback if no files exist
                        )
                        ->html(), // Enables HTML rendering
                    
                    TextEntry::make('process_flowchart')
                        ->label('Download Process Flowchart')
                        ->state(fn ($record) => 
                            $record->process_flowchart 
                                ? collect(json_decode($record->process_flowchart, true)) // Decode JSON string to an array
                                    ->map(fn ($file) => '<a href="' . Storage::url($file) . '" download class="block text-blue-500 underline">' . basename($file) . '</a>') // Display the file name instead of "Download"
                                    ->implode('<br>') // Join links with line breaks
                                : 'No files uploaded' // Fallback if no files exist
                        )
                        ->html(),
                    ])
                    ->columns(),

                    
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
