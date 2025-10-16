<?php

namespace App\Filament\Admin\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use App\Models\OperatorProficiency;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Admin\Resources\OperatorResource\Pages\ListOperators;
use App\Filament\Admin\Resources\OperatorResource\Pages\CreateOperator;
use App\Filament\Admin\Resources\OperatorResource\Pages\EditOperator;
use App\Filament\Admin\Resources\OperatorResource\Pages\ViewOperator;
use App\Filament\Admin\Resources\OperatorResource\Pages;
use App\Models\Operator;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class OperatorResource extends Resource
{
    protected static ?string $model = Operator::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | \UnitEnum | null $navigationGroup = 'Admin Operations';

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Operator')
                    ->relationship('user', 'first_name', function ($query) {
                        $factoryId = Auth::user()->factory_id;

                        $query->select(['id', 'first_name', 'last_name'])
                            ->where('factory_id', $factoryId)
                            ->whereHas('roles', function ($q) {
                                $q->where('name', 'operator');
                            });
                    })
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return $record->first_name.' '.$record->last_name;
                    })
                    ->required(),
                Select::make('operator_proficiency_id')
                    ->label('Proficiency')
                    ->options(function () {
                        $factoryId = Auth::user()->factory_id; // Adjust based on how you get factory_id

                        return OperatorProficiency::where('factory_id', $factoryId)
                            ->pluck('proficiency', 'id');
                    })
                    ->required(),
                Select::make('shift_id')
                    ->label('Shift')
                    ->relationship('shift', 'name', function ($query) {
                        // Example of filtering by a condition, like factory_id
                        $factoryId = Auth::user()->factory_id; // Adjust this based on your application context
                        $query->where('factory_id', $factoryId);
                    })
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.first_name')->searchable(),
                TextColumn::make('operator_proficiency.proficiency')
                    ->searchable(),
                TextColumn::make('shift.name')
                    ->searchable(),

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
            'index' => ListOperators::route('/'),
            'create' => CreateOperator::route('/create'),
            'edit' => EditOperator::route('/{record}/edit'),
            'view' => ViewOperator::route('/{record}/'),

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
