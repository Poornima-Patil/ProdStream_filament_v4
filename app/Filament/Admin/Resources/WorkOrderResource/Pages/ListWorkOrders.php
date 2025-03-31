<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Pages;

use App\Filament\Admin\Resources\WorkOrderResource;
use App\Models\WorkOrderLog;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListWorkOrders extends ListRecords
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->after(function ($livewire, array $data) {
                    $workOrder = $livewire->record;

                    WorkOrderLog::create([
                        'work_order_id' => $workOrder->id,
                        'user_id' => Auth::id(),
                        'comments' => $data['log_comments'] ?? null,
                        'priority' => $data['log_priority'],
                        'status' => $workOrder->status,
                    ]);

                    Notification::make()
                        ->title('Log Added')
                        ->body('Work Order log has been recorded successfully.')
                        ->success()
                        ->send();
                })
                ->form([
                    Textarea::make('log_comments')
                        ->label('Comments')
                        ->nullable(),

                    Select::make('log_priority')
                        ->label('Priority')
                        ->options([
                            'Low' => 'Low',
                            'Medium' => 'Medium',
                            'High' => 'High',
                        ])
                        ->default('Medium')
                        ->required(),
                ]),
        ];
    }
}
