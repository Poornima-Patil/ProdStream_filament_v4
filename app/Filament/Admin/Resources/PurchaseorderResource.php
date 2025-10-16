<?php

namespace App\Filament\Admin\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use App\Models\PartNumber;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Admin\Resources\PurchaseorderResource\Pages\ListPurchaseorders;
use App\Filament\Admin\Resources\PurchaseorderResource\Pages\CreatePurchaseorder;
use App\Filament\Admin\Resources\PurchaseorderResource\Pages\EditPurchaseorder;
use App\Filament\Admin\Resources\PurchaseorderResource\Pages\ViewPurchaseorder;
use App\Filament\Admin\Resources\PurchaseorderResource\Pages;
use App\Models\CustomerInformation;
use App\Models\PurchaseOrder;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PurchaseorderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string | \UnitEnum | null $navigationGroup = 'Process Operations';

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    public static function getLabel(): string
    {
        return 'Sales Order lines'; // This will be displayed in the left panel
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('part_number_id')
                    ->label('Partnumber')
                    ->options(function () {
                        $factoryId = Auth::user()->factory_id; // Get the factory ID of the logged-in user

                        // Query the PartNumber model and include the partnumber and revision
                        return PartNumber::where('factory_id', $factoryId)
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
                Select::make('cust_id')
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
                TextInput::make('QTY')
                    ->required(),
                DatePicker::make('delivery_target_date')
                    ->required()
                    ->label('Delivery Target Date')
                    ->minDate(fn (string $operation): ?Carbon => $operation === 'create' ? now()->startOfDay() : null)
                    ->hint('Select the delivery target date')
                    ->displayFormat('Y-m-d'), // You can adjust the date format

                Select::make('Unit Of Measurement')
                    ->options([
                        'Kgs' => 'Kgs',
                        'Numbers' => 'Numbers',
                    ])
                    ->required(),

                TextInput::make('price'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('unique_id')->label('Unique ID')
                    ->searchable(),
                TextColumn::make('customer.customer_id')->label('Customer ID')
                    ->searchable(),
                TextColumn::make('partnumber.partnumber')
                    ->searchable(),
                TextColumn::make('partnumber.revision')->label('Revision'),
                TextColumn::make('QTY'),
                TextColumn::make('Unit Of Measurement')
                    ->label('UM'),
                TextColumn::make('delivery_target_date') // The column for Delivery Target Date
                    ->label('Delivery Target Date')
                    ->date(), // Only show the date part

                TextColumn::make('price'),

                TextColumn::make('progress')
                    ->label('Progress')
                    ->formatStateUsing(function ($state) {
                        $progressPercent = $state ?? 0;

                        // Return the progress bar as an HTML string
                        return '
                            <div class="h-4 rounded-full text-xs text-center leading-4" style="background-color: #10B981; width: '.$progressPercent.'%;">
                                '.$progressPercent.'%
                            </div>
                        ';
                    })
                    ->html(), // Enable HTML rendering for this column
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->label('Edit')->size('sm'),
                    ViewAction::make()->label('View')->size('sm'),
                ])->size('sm')->tooltip('Action')->dropdownPlacement('right')
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
            'index' => ListPurchaseorders::route('/'),
            'create' => CreatePurchaseorder::route('/create'),
            'edit' => EditPurchaseorder::route('/{record}/edit'),
            'view' => ViewPurchaseorder::route('/{record}/'),

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
