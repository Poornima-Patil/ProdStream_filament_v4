<?php

namespace App\Filament\Admin\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Admin\Resources\OperatorProficiencyResource\Pages\ListOperatorProficiencies;
use App\Filament\Admin\Resources\OperatorProficiencyResource\Pages\CreateOperatorProficiency;
use App\Filament\Admin\Resources\OperatorProficiencyResource\Pages\EditOperatorProficiency;
use App\Filament\Admin\Resources\OperatorProficiencyResource\Pages\ViewOperatorProficiency;
use App\Filament\Admin\Resources\OperatorProficiencyResource\Pages;
use App\Models\OperatorProficiency;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
class OperatorProficiencyResource extends Resource
{
    protected static ?string $model = OperatorProficiency::class;

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-star';

    protected static string | \UnitEnum | null $navigationGroup = 'Admin Operations';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('proficiency')
                    ->label('Proficiency')
                    ->required(),
                TextInput::make('description')
                    ->label('Description')
                    ->required(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([

                TextColumn::make('proficiency')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description'),

            ])
            ->filters([
                TrashedFilter::make(),
            ])
             ->recordActions([
                ActionGroup::make([
                    EditAction::make()->label('Edit'),
                    ViewAction::make()->label('View'),
                ])
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
            'index' => ListOperatorProficiencies::route('/'),
            'create' => CreateOperatorProficiency::route('/create'),
            'edit' => EditOperatorProficiency::route('/{record}/edit'),
            'view' => ViewOperatorProficiency::route('/{record}/'),

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
