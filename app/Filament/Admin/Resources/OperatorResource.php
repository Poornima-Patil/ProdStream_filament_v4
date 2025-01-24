<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\OperatorResource\Pages;
use App\Models\Operator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class OperatorResource extends Resource
{
    protected static ?string $model = Operator::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Admin Operations';

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
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
                Forms\Components\Select::make('operator_proficiency_id')
                    ->label('Proficiency')
                    ->options(function () {
                        $factoryId = Auth::user()->factory_id; // Adjust based on how you get factory_id

                        return \App\Models\OperatorProficiency::where('factory_id', $factoryId)
                            ->pluck('proficiency', 'id');
                    })
                    ->required(),
                Forms\Components\Select::make('shift_id')
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
                Tables\Columns\TextColumn::make('user.first_name')->searchable(),
                Tables\Columns\TextColumn::make('operator_proficiency.proficiency')
                    ->searchable(),
                Tables\Columns\TextColumn::make('shift.name')
                    ->searchable(),

            ])
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
            'index' => Pages\ListOperators::route('/'),
            'create' => Pages\CreateOperator::route('/create'),
            'edit' => Pages\EditOperator::route('/{record}/edit'),
            'view' => Pages\ViewOperator::route('/{record}/'),

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
