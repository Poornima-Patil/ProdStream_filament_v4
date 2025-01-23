<?php

namespace App\Filament\Admin\Resources;

use Illuminate\Support\Facades\Auth;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Storage;
use App\Filament\Admin\Resources\BomResource\Pages;
use App\Filament\Admin\Resources\BomResource\RelationManagers;
use App\Models\Bom;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Facades\Filament;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;


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
                        return [$partNumber->id => $partNumber->partnumber . ' - ' . $partNumber->revision];
                    });
                })
                ->required()
                ->searchable()
                ->reactive()
                ->preload(),
               
                Forms\Components\Select::make('purchase_order_id')
                ->label('Customer Information')
                ->options(function (callable $get) {
                    $factoryId = Auth::user()->factory_id; // Get the factory ID of the logged-in user
                    $partNumberId = $get('part_number_id'); // Get the selected PartNumber ID
            
                    if (!$partNumberId) {
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
                ->directory(function() {
                    $tenant = Filament::getTenant();
                    return 'tenants/' . $tenant->id;
                 })
                ->disk('public')
                ->preserveFilenames(),
                
                
                Forms\Components\FileUpload::make('process_flowchart')
                ->label(label: 'Process Flowchart')
                ->directory(function() {
                    $tenant = Filament::getTenant();
                    return 'tenants/' . $tenant->id;
                 })
                ->disk('public')
                ->preserveFilenames(),
                Forms\Components\Select::make('machine_id')
                ->label('Machine')
                ->options(function () {
                    $factoryId = Auth::user()->factory_id;
                    return \App\Models\Machine::where('status', '1')
                        ->where('factory_id', $factoryId)   
                    ->pluck('name', 'id');
                }),
               
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
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('unique_id')->label('Unique ID'),
               Tables\Columns\TextColumn::make('purchaseorder.partnumber.description')->label('Description'),
               Tables\Columns\TextColumn::make('purchaseorder.partnumber.partnumber')->label('PartNumber'),
               Tables\Columns\TextColumn::make('purchaseorder.partnumber.revision')->label('Revision'),
            Tables\Columns\TextColumn::make('requirement_pkg')
->visibleOn('Edit'),
            Tables\Columns\TextColumn::make('process_flowchart')
->visibleOn('Edit'),

                Tables\Columns\TextColumn::make('machine.name'),
                Tables\Columns\TextColumn::make('operatorproficiency.proficiency'),
                Tables\Columns\TextColumn::make('lead_time')->label('Target Completion Time'),
                Tables\Columns\IconColumn::make('status')
                ->boolean(),
                
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make()
                ->hiddenLabel()
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

            
            // Section 2: Remaining fields
            Section::make('Documents')
                ->collapsible()
                ->schema([
                   TextEntry::make('requirement_pkg')
				->formatStateUsing(fn() => "Download Requirement Pkg")
                                // URL to be used for the download (link), and the second parameter is for the new tab
                                ->url(fn($record) => Storage::url($record->requirement_pkg), true)
                                // This will make the link look like a "badge" (blue)
                                ->badge()
                                ->color(Color::Blue),
 		TextEntry::make('process_flowchart')
				->formatStateUsing(fn() => "Download Process Flowchart")
                                // URL to be used for the download (link), and the second parameter is for the new tab
                                ->url(fn($record) => Storage::url($record->process_flowchart), true)
                                // This will make the link look like a "badge" (blue)
                                ->badge()
                                ->color(Color::Blue),


                ])->columns(),
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
