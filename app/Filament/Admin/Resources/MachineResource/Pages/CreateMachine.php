<?php

namespace App\Filament\Admin\Resources\MachineResource\Pages;

use App\Filament\Admin\Resources\MachineResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMachine extends CreateRecord
{
    protected static string $resource = MachineResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
