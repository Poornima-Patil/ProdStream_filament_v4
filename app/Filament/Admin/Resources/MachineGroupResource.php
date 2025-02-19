<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\MachineGroupResource\Pages;
use App\Models\MachineGroup;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class MachineGroupResource extends Resource
{
    protected static ?string $model = MachineGroup::class;

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

       protected static ?string $navigationIcon = 'heroicon-o-cog';


    protected static ?string $navigationGroup = 'Admin Operations';

    // Add this for custom labels and navigation
    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('group_name')
                    ->label('Group Name')
                    ->required(),
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->required(),
                 
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group_name')
                    ->label('Group Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Description')
                    ->limit(50),
                   
               
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMachineGroups::route('/'),
            'create' => Pages\CreateMachineGroup::route('/create'),
            'edit' => Pages\EditMachineGroup::route('/{record}/edit'),
        ];
    }
}
