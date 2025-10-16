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
use App\Filament\Admin\Resources\ScrappedReasonResource\Pages\ListScrappedReasons;
use App\Filament\Admin\Resources\ScrappedReasonResource\Pages\CreateScrappedReason;
use App\Filament\Admin\Resources\ScrappedReasonResource\Pages\EditScrappedReason;
use App\Filament\Admin\Resources\ScrappedReasonResource\Pages\ViewScrappedReason;
use App\Filament\Admin\Resources\ScrappedReasonResource\Pages;
use App\Models\ScrappedReason;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ScrappedReasonResource extends Resource
{
    protected static ?string $model = ScrappedReason::class;

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-trash';

    protected static string | \UnitEnum | null $navigationGroup = 'Admin Operations';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('description')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('description')
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
            'index' => ListScrappedReasons::route('/'),
            'create' => CreateScrappedReason::route('/create'),
            'edit' => EditScrappedReason::route('/{record}/edit'),
            'view' => ViewScrappedReason::route('/{record}/'),

        ];
    }
}
