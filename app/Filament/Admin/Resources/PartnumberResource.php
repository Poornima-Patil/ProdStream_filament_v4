<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PartnumberResource\Pages;
use App\Filament\Admin\Resources\PartnumberResource\RelationManagers;
use App\Models\Partnumber;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PartnumberResource extends Resource
{
    protected static ?string $model = Partnumber::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Admin Operations';
    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    public static function form(Form $form): Form
    {
        return $form
        ->schema([
            Forms\Components\TextInput::make('partnumber')
                ->required()
                ->label('Part Number'),
            Forms\Components\TextInput::make('revision')
                ->default(0)
                ->required()
                ->label('Revision'),
            Forms\Components\TextInput::make('description')
                ->required()
                ->label('Description'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('partnumber'),
                Tables\Columns\TextColumn::make('revision'),
                Tables\Columns\TextColumn::make('description'),
               
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                ->label('Edit Partnumber'),
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
            'index' => Pages\ListPartnumbers::route('/'),
            'create' => Pages\CreatePartnumber::route('/create'),
            'edit' => Pages\EditPartnumber::route('/{record}/edit'),
            'view' => Pages\ViewPartnumber::route('/{record}/'),

        ];
    }

    private function validateUniqueCombination($partnumber, $revision, callable $get)
    {
        if (PartNumber::where('partnumber', $partnumber)->where('revision', $revision)->exists()) {
            dd($partnumber);
            $get('unique_error', 'The combination of part number and revision must be unique.');
        } else {
            dd($partnumber);
            $get('unique_error', null);
        }
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
