<?php

namespace App\Filament\Pages\Tenancy;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;

class EditFactoryProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Factory profile';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name'),
                TextInput::make('slug'),
                FileUpload::make('template_path')
                    ->label('Excel Template')
                    ->directory('excel-templates') // saved to: storage/app/public/excel-templates
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->preserveFilenames()
                    ->downloadable()
                    ->openable()
                    ->hint('Upload an .xlsx file with Sheet1 as pivot and Sheet2 as data source')
                    ->columnSpan('full'),
                // ...
            ]);
    }
}
