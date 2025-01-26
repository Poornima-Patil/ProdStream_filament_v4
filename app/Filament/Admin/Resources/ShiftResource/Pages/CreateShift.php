<?php

namespace App\Filament\Admin\Resources\ShiftResource\Pages;

use App\Filament\Admin\Resources\ShiftResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShift extends CreateRecord
{
    protected static string $resource = ShiftResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
