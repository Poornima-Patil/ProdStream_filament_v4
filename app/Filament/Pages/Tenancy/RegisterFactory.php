<?php

namespace App\Filament\Pages\Tenancy;

use Filament\Schemas\Schema;
use App\Models\Factory;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\FileUpload;


class RegisterFactory extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register Factory';
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

    protected function handleRegistration(array $data): Factory
    {
        $factory = Factory::create($data);

        $factory->users()->attach(Auth::id());

        return $factory;
    }
}
