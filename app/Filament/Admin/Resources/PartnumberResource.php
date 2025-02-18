<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PartnumberResource\Pages;
use App\Models\PartNumber;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rule;

class PartnumberResource extends Resource
{
    protected static ?string $model = PartNumber::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup =  'Process Operations';

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('partnumber')
                ->required()
                ->label('Part Number')
                ->rules([
                    'required',
                    Rule::unique('part_numbers', 'partnumber')
                        ->where(function ($query) {
                            $query->where('revision', request()->input('revision'));
                        })
                        ->ignore(request()->route('record')), // Exclude current record during editing
                ])->reactive(),

            Forms\Components\TextInput::make('revision')
                ->default(0)
                ->required()
                ->label('Revision')
                ->rules([
                    'required',
                    Rule::unique('part_numbers', 'revision')
                        ->where(function ($query) {
                            $query->where('partnumber', request()->input('partnumber'));
                        })
                        ->ignore(request()->route('record')), // Exclude current record during editing
                ])->reactive(),

            Forms\Components\TextInput::make('description')
                ->required()
                ->label('Description'), // This will now update correctly without unnecessary checks


                Forms\Components\TextInput::make('cycle_time')
    ->label('Cycle Time (MM:SS)') // Display label
    ->required()
    ->mask('99:99') // Mask to ensure correct input format
    ->placeholder('MM:SS') // Placeholder example
    ->live() // Updates value dynamically
    ->dehydrateStateUsing(fn ($state) => self::convertTimeToSeconds($state)) // Convert MM:SS to seconds before saving
    ->formatStateUsing(fn ($state) => self::convertSecondsToTime($state)), // Convert seconds to MM:SS when displaying

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('partnumber')
                    ->searchable(),
                Tables\Columns\TextColumn::make('revision'),
                Tables\Columns\TextColumn::make('description'),
                Tables\Columns\TextColumn::make('cycle_time')
    ->label('Cycle Time (MM:SS)')
    ->formatStateUsing(function ($state) {
        return self::convertSecondsToTime($state); // Convert seconds to MM:SS for display
    }),

            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Edit Partnumber'),
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
            'index' => Pages\ListPartnumbers::route('/'),
            'create' => Pages\CreatePartnumber::route('/create'),
            'edit' => Pages\EditPartnumber::route('/{record}/edit'),
            'view' => Pages\ViewPartnumber::route('/{record}/'),

        ];
    }

    /* private function validateUniqueCombination($partnumber, $revision, callable $get)
     {
         if (PartNumber::where('partnumber', $partnumber)->where('revision', $revision)->exists()) {
             dd($partnumber);
             $get('unique_error', 'The combination of part number and revision must be unique.');
         } else {
             dd($partnumber);
             $get('unique_error', null);
         }
     }*/

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function convertTimeToSeconds($time): ?int
{
    if (!$time) return null;

    [$minutes, $seconds] = explode(':', $time);
    return ((int)$minutes * 60) + (int)$seconds;
}

public static function convertSecondsToTime($seconds): ?string
{
    if (!$seconds) return '00:00';

    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    
    return sprintf('%02d:%02d', $minutes, $seconds);
}

}
