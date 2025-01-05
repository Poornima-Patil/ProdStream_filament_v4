<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\WorkOrderResource\Pages;
use App\Filament\Admin\Resources\WorkOrderResource\RelationManagers;
use App\Models\Operator;
use App\Models\User;
use App\Models\WorkOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

use Illuminate\Support\Facades\Auth;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;

class WorkOrderResource extends Resource
{
    protected static ?string $model = Workorder::class;
    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard';
    protected static ?string $navigationGroup = 'Process Operations';

    
    public static function form(Form $form): Form
    {

        $user = Auth::user();
        $isAdminOrManager = $user && $user->can(abilities: 'Edit Bom');        //dd($isAdminOrManager);
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
                                $partNumber->id => $partNumber->partnumber . ' - ' . $partNumber->revision
                            ];
                        });
                })
                ->required()
                ->searchable()
                ->reactive(),
                Forms\Components\Select::make('bom_id')
                ->label('BOM')
                ->options(function (callable $get) {
                    $partNumberId = $get('part_number_id'); // Get the selected Part Number ID
                    if (!$partNumberId) {
                        return []; // No Part Number selected, return empty options
                    }
                    // Query BOMs through the Purchase Order link and include Part Number description
                    return \App\Models\Bom::whereHas('purchaseOrder', function ($query) use ($partNumberId) {
                        $query->where('part_number_id', $partNumberId);
                    })->get()->mapWithKeys(function ($bom) {
                        $partNumberDescription = $bom->purchaseOrder->partNumber->description ?? ''; // Assuming PartNumber has a 'description' field
                        return [
                            $bom->id => "BOM ID: {$bom->id} - Part Description: {$partNumberDescription}"
                        ];
                    });
                })
                ->required()
                ->reactive()
                ->searchable()
                    ->disabled(!$isAdminOrManager),
                Forms\Components\TextInput::make('qty')
                    ->label('Quantity')
                    ->required()
                    ->disabled(!$isAdminOrManager),
                Forms\Components\Select::make('machine_id')
                    ->label('Machine')
                    ->relationship('machine', 'name', function ($query) {
                        $factoryId = Auth::user()->factory_id; // Adjust based on how you get factory_id
                        $query->where('factory_id', $factoryId);
                    })
                    ->required()
                    ->disabled(!$isAdminOrManager),
                    Forms\Components\Select::make('operator_id')
    ->label('Operator')
    ->disabled(!$isAdminOrManager)
    ->options(function (callable $get) {
        $bomId = $get('bom_id'); // Get selected BOM ID
        
        if (!$bomId) {
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
                    ->label('Start Time'),
                Forms\Components\DateTimePicker::make('end_time')
                    ->label('End Time'),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'Assigned' => 'Assigned',
                        'Start' => 'Start',
                        'Hold' => 'Hold',
                        'Completed' => 'Completed'
                    ])
                    ->reactive(),
                    Forms\Components\TextInput::make('ok_qtys')
                    ->label('Ok Qtys')
                    ->default(0)
                    ->visible(fn ($get) => in_array($get('status'), ['Hold', 'Completed'])),
                
                Forms\Components\TextInput::make('scrapped_qtys')
                    ->label('Scrapped Qtys')
                    ->default(0)
                    ->visible(fn ($get) => in_array($get('status'), ['Hold', 'Completed'])),
                
                Forms\Components\Select::make('scrapped_reason_id')
                    ->label('Why Scrapped')
                    ->relationship('scrappedReason', 'description', function ($query) {
                        $factoryId = Auth::user()->factory_id; // Apply factory_id filter
                        $query->where('factory_id', $factoryId);
                    })
                    ->required()
                    ->visible(fn ($get) => in_array($get('status'), ['Hold', 'Completed'])),

            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $isAdminOrManager = $user && $user->can(abilities: 'Edit Bom');       
        return $table
            ->Columns ([
                 Tables\Columns\TextColumn::make('bom.purchaseorder.partnumber.description')->label('BOM')
                ->hidden(!$isAdminOrManager),
                Tables\Columns\TextColumn::make('bom.purchaseorder.partnumber.partnumber')->label('Part Number'),
                Tables\Columns\TextColumn::make('bom.purchaseorder.partnumber.revision')->label('Revision'),
                Tables\Columns\TextColumn::make('machine.name')->label('Machine'),
                Tables\Columns\TextColumn::make('operator.user.first_name')->label('Operator')
                ->hidden(!$isAdminOrManager),
                Tables\Columns\TextColumn::make('qty'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('start_time'),
                Tables\Columns\TextColumn::make('end_time'),
                Tables\Columns\TextColumn::make('ok_qtys'),
                Tables\Columns\TextColumn::make('scrapped_qtys'),
                Tables\Columns\TextColumn::make('scrappedReason.description')->label('Scrapped Reason'),
                
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
                        ->hidden(!$isAdminOrManager),
                    TextEntry::make('qty')->label('Quantity'),
                    TextEntry::make('machine.name')->label('Machine'),
                    TextEntry::make('operator.user.first_name')
                        ->label('Operator')
                        ->hidden(!$isAdminOrManager),
                ])->columns(),
            
            // Section 2: Remaining fields
            Section::make('Details')
                ->collapsible()
                ->schema([
                    TextEntry::make('bom.purchaseorder.partnumber.partnumber')->label('Part Number'),
                    TextEntry::make('bom.purchaseorder.partnumber.revision')->label('Revision'),
                    TextEntry::make('status')->label('Status'),
                    TextEntry::make('start_time')->label('Start Time'),
                    TextEntry::make('end_time')->label('End Time'),
                    TextEntry::make('ok_qtys')->label('OK Quantities'),
                    TextEntry::make('scrapped_qtys')->label('Scrapped Quantities'),
                    TextEntry::make('scrappedReason.description')->label('Scrapped Reason'),
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
