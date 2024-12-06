<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Pages;

use App\Filament\Admin\Resources\WorkOrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateWorkOrder extends CreateRecord
{
    protected static string $resource = WorkOrderResource::class;
}
