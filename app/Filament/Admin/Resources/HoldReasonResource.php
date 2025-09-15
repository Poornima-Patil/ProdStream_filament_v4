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
use App\Filament\Admin\Resources\HoldReasonResource\Pages\ListHoldReasons;
use App\Filament\Admin\Resources\HoldReasonResource\Pages\CreateHoldReason;
use App\Filament\Admin\Resources\HoldReasonResource\Pages\EditHoldReason;
use App\Filament\Admin\Resources\HoldReasonResource\Pages\ViewHoldReasons;
use App\Filament\Admin\Resources\HoldReasonResource\Pages;
use App\Models\HoldReason;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;


class HoldReasonResource extends Resource
{
    protected static ?string $model = HoldReason::class;

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
            'index' => ListHoldReasons::route('/'),
            'create' => CreateHoldReason::route('/create'),
            'edit' => EditHoldReason::route('/{record}/edit'),
            'view' => ViewHoldReasons::route('/{record}'),
        ];
    }
}
