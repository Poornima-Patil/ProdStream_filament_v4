<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Factory;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Tenancy\RegisterTenant;
use Illuminate\Support\Facades\Auth;

class RegisterFactory extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register Factory';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name'),
                TextInput::make('slug'),
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
