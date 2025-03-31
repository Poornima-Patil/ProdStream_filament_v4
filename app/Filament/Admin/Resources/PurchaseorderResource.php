<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PurchaseorderResource\Pages;
use App\Models\CustomerInformation;
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
        return 'Sales Order lines'; // This will be displayed in the left panel
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
                                    $partNumber->id => $partNumber->partnumber.' - '.$partNumber->revision,
                                ];
                            });
                    })
                    ->required()
                    ->preload()
                    ->searchable()
                    ->reactive(),
                Forms\Components\Select::make('cust_id')
                    ->label('Customer')
                    ->options(function () {
                        // Get the factory_id of the currently logged-in user
                        $userFactoryId = auth()->user()->factory_id;

                        // Get the customers whose factory_id matches the logged-in user's factory_id
                        return CustomerInformation::where('factory_id', $userFactoryId)
                            ->pluck('name', 'id') // Pluck the name and id of the customers
                            ->mapWithKeys(function ($name, $id) {
                                $customer = CustomerInformation::find($id);

                                return [$id => "{$customer->customer_id} - {$name}"]; // Format as unique_id - name
                            })
                            ->toArray();
                    })
                    ->required(),
                Forms\Components\TextInput::make('QTY')
                    ->required(),
                Forms\Components\DatePicker::make('delivery_target_date')
                    ->required() // Make it required if needed
                    ->label('Delivery Target Date')
                    ->nullable() // Make it nullable if you want to allow empty values
                    ->hint('Select the delivery target date')
                    ->displayFormat('Y-m-d'), // You can adjust the date format

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
                Tables\Columns\TextColumn::make('unique_id')->label('Unique ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer.customer_id')->label('Customer ID')
                    ->searchable(),
                Tables\Columns\TextColumn::make('partnumber.partnumber')
                    ->searchable(),
                Tables\Columns\TextColumn::make('partnumber.revision')->label('Revision'),
                Tables\Columns\TextColumn::make('QTY'),
                Tables\Columns\TextColumn::make('Unit Of Measurement')
                    ->label('UM'),
                Tables\Columns\TextColumn::make('delivery_target_date') // The column for Delivery Target Date
                    ->label('Delivery Target Date')
                    ->date(), // Only show the date part

                Tables\Columns\TextColumn::make('price'),

            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit Sales Order Line'),
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
            ])->orderByDesc('created_at'); // Ensures latest records appear first;
    }
}
