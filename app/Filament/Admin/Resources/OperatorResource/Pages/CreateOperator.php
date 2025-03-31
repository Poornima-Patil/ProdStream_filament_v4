<?php

namespace App\Filament\Admin\Resources\OperatorResource\Pages;

use App\Filament\Admin\Resources\OperatorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOperator extends CreateRecord
{
    protected static string $resource = OperatorResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
