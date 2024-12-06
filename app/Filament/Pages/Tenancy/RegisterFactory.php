<?php
namespace App\Filament\Pages\Tenancy;

use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;

use Filament\Forms\Form;
use Filament\Pages\Tenancy\RegisterTenant;
use Illuminate\Database\Eloquent\Model;
use App\Models\Factory;
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