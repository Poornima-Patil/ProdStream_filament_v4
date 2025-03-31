<?php

namespace App\Filament\Admin\Resources\OperatorProficiencyResource\Pages;

use App\Filament\Admin\Resources\OperatorProficiencyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOperatorProficiency extends CreateRecord
{
    protected static string $resource = OperatorProficiencyResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
