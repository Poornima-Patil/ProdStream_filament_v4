<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ShiftResource\Pages;
use App\Filament\Admin\Resources\ShiftResource\RelationManagers;
use App\Models\Shift;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ShiftResource extends Resource
{
    protected static ?string $model = Shift::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Admin Operations';
    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name'),
                Forms\Components\TimePicker::make('start_time')
                ->required()
                ->withoutSeconds()
                ->label('Start Time'),

            Forms\Components\TimePicker::make('end_time')
                ->required()
                ->withoutSeconds()
                ->label('End Time'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('start_time')
                ->label('Start Time')
                ->dateTime('H:i'), // Format to display in 24-hour format,

            Tables\Columns\TextColumn::make('end_time')
                ->label('End Time')
                ->dateTime('H:i'), // Format to display in 24-hour format
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                ->label('Edit Shift'),
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
            'index' => Pages\ListShifts::route('/'),
            'create' => Pages\CreateShift::route('/create'),
            'edit' => Pages\EditShift::route('/{record}/edit'),
            'view'=> Pages\ViewShift::route('/{record}/'),

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
