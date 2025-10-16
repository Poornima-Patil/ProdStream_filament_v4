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
use App\Filament\Admin\Resources\PartnumberResource\Pages\ListPartnumbers;
use App\Filament\Admin\Resources\PartnumberResource\Pages\CreatePartnumber;
use App\Filament\Admin\Resources\PartnumberResource\Pages\EditPartnumber;
use App\Filament\Admin\Resources\PartnumberResource\Pages\ViewPartnumber;
use App\Filament\Admin\Resources\PartnumberResource\Pages;
use App\Models\PartNumber;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rule;

class PartnumberResource extends Resource
{
    protected static ?string $model = PartNumber::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-tag';

    protected static string | \UnitEnum | null $navigationGroup = 'Process Operations';

    protected static ?string $tenantOwnershipRelationshipName = 'factory';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('partnumber')
                ->required()
                ->label('Part Number')
                ->rules([
                    'required',
                    Rule::unique('part_numbers', 'partnumber')
                        ->where(function ($query) {
                            $query->where('revision', request()->input('revision'))
                                  ->where('factory_id', auth()->user()->factory_id);
                        })
                        ->ignore(request()->route('record')), // Exclude current record during editing
                ])->reactive(),

            TextInput::make('revision')
                ->default(0)
                ->required()
                ->label('Revision')
                ->rules([
                    'required',
                    Rule::unique('part_numbers', 'revision')
                        ->where(function ($query) {
                            $query->where('partnumber', request()->input('partnumber'))
                                  ->where('factory_id', auth()->user()->factory_id);
                        })
                        ->ignore(request()->route('record')), // Exclude current record during editing
                ])->reactive(),

            TextInput::make('description')
                ->required()
                ->label('Description'), // This will now update correctly without unnecessary checks

            TextInput::make('cycle_time')
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
                TextColumn::make('partnumber')
                    ->searchable(),
                TextColumn::make('revision'),
                TextColumn::make('description'),
                TextColumn::make('cycle_time')
                    ->label('Cycle Time (MM:SS)')
                    ->formatStateUsing(function ($state) {
                        return self::convertSecondsToTime($state); // Convert seconds to MM:SS for display
                    }),

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
            'index' => ListPartnumbers::route('/'),
            'create' => CreatePartnumber::route('/create'),
            'edit' => EditPartnumber::route('/{record}/edit'),
            'view' => ViewPartnumber::route('/{record}/'),

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
        if (! $time) {
            return null;
        }

        [$minutes, $seconds] = explode(':', $time);

        return ((int) $minutes * 60) + (int) $seconds;
    }

    public static function convertSecondsToTime($seconds): ?string
    {
        if (! $seconds) {
            return '00:00';
        }

        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
