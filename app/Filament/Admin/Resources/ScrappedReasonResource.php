<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\ScrappedReasonResource\Pages;
use App\Filament\Admin\Resources\ScrappedReasonResource\RelationManagers;
use App\Models\ScrappedReason;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ScrappedReasonResource extends Resource
{
    protected static ?string $model = ScrappedReason::class;
    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    protected static ?string $navigationIcon = 'heroicon-o-trash';

    protected static ?string $navigationGroup = 'Admin Operations';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                ->required(),
                Forms\Components\TextInput::make('description')
                ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code'),
                Tables\Columns\TextColumn::make('description'),

            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make()
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
            'index' => Pages\ListScrappedReasons::route('/'),
            'create' => Pages\CreateScrappedReason::route('/create'),
            'edit' => Pages\EditScrappedReason::route('/{record}/edit'),
            'view' => Pages\ViewScrappedReason::route('/{record}/'),

        ];
    }
}
