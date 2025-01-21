<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PurchaseorderResource\Pages;
use App\Filament\Admin\Resources\PurchaseorderResource\RelationManagers;
use App\Models\PurchaseOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class PurchaseorderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'Process Operations';
    protected static ?string $tenantOwnershipRelationshipName = 'factory';


    public static function getLabel(): string
    {
        return 'Customer Order Lines'; // This will be displayed in the left panel
    }
    public static function form(Form $form): Form
    {
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

            Forms\Components\TextInput::make('supplierInfo'),
           /* Forms\Components\TextInput::make('description')
            ->disabled()
          
            ->afterStateHydrated(function ($state, callable $set,$get ) {
                $partNumber = $get('part_number_id');
                if($partNumber)
                  $set('description', $partNumber->description);
                else 
                    $set('description', 'No description available');
                })->reactive(),
           */
            Forms\Components\TextInput::make('QTY')
                ->required(),
            Forms\Components\Select::make('Unit Of Measurement')
                ->options([
                    'Kgs' => 'Kgs',
                    'Numbers' => 'Numbers',
                ])
                ->required(),
            Forms\Components\TextInput::make('price'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('partnumber.partnumber'),
                Tables\Columns\TextColumn::make('partnumber.revision')->label('Revision'),
                 Tables\Columns\TextColumn::make('QTY'),
                 Tables\Columns\TextColumn::make('Unit Of Measurement')
                 ->label('UM'),
                 Tables\Columns\TextColumn::make('supplierInfo'),
                 Tables\Columns\TextColumn::make('price'),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                ->label('Edit PO'),
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
            'index' => Pages\ListPurchaseorders::route('/'),
            'create' => Pages\CreatePurchaseorder::route('/create'),
            'edit' => Pages\EditPurchaseorder::route('/{record}/edit'),
            'view' => Pages\ViewPurchaseorder::route('/{record}/'),

        ];
    }
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
