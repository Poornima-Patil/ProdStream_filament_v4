<?php

namespace App\Filament\Admin\Resources\WorkOrderResource\Pages;

use App\Filament\Admin\Resources\WorkOrderResource;
use App\Models\InfoMessage;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWorkOrder extends EditRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('alertManagerForStartTimeIssue')
                ->label('Request Start Time Approval')
                ->visible(function () {
                    $record = $this->getRecord();

                    // Only show if operator role
                    if (! auth()->user()->hasRole('Operator')) {
                        return false;
                    }

                    // Only show if status is not Start and no existing Start logs
                    if ($record->status === 'Start') {
                        return false;
                    }

                    $existingStartLog = $record->workOrderLogs()->where('status', 'Start')->exists();
                    if ($existingStartLog) {
                        return false;
                    }

                    // Only show if start_time exists
                    if (! $record->start_time) {
                        return false;
                    }

                    // Use the model's helper method to check if operator can start
                    $validation = $record->canOperatorStartNow();

                    // Show button if operator cannot start (either early or late)
                    return ! $validation['can_start'];
                })
                ->schema([
                    Textarea::make('comments')
                        ->label('Comments')
                        ->default(function () {
                            $record = $this->getRecord();
                            $validation = $record->canOperatorStartNow();
                            $plannedStartTime = $validation['planned_start'];
                            $maxAllowedTime = $validation['max_allowed'];

                            // Check which condition applies
                            if ($validation['reason'] === 'early_start') {
                                return 'Operator '.auth()->user()->first_name.' '.auth()->user()->last_name.' wants to start Work Order '.$record->unique_id.' today. But the work order is planned on '.$plannedStartTime->format('d-m-Y H:i').'. Would you like to change?';
                            } else {
                                // Late start (exceeded time limit)
                                return 'The Operator '.auth()->user()->first_name.' '.auth()->user()->last_name.' has exceeded the time limit '.$maxAllowedTime->format('d-m-Y H:i').' for the Work Order '.$record->unique_id.'. Would you like to change the planned start time?';
                            }
                        })
                        ->required(),
                    Select::make('priority')
                        ->label('Priority')
                        ->options([
                            'High' => 'High',
                            'Medium' => 'Medium',
                            'Low' => 'Low',
                        ])
                        ->default('High')
                        ->required(),
                ])
                ->modalHeading('Send Alert to Manager')
                ->action(function (array $data) {
                    $record = $this->getRecord();

                    InfoMessage::create([
                        'work_order_id' => $record->id,
                        'user_id' => auth()->id(),
                        'message' => $data['comments'],
                        'priority' => $data['priority'],
                    ]);

                    Notification::make()
                        ->title('Manager Alerted')
                        ->body('Your request has been sent to the manager.')
                        ->success()
                        ->send();
                })
                ->button()
                ->color('warning'),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Convert start_time to configured timezone for display
        if (isset($data['start_time']) && $data['start_time']) {
            $startDateTime = Carbon::parse($data['start_time'])->setTimezone(config('app.timezone'));
            $data['start_time'] = $startDateTime;
        }

        // Convert end_time to configured timezone for display
        if (isset($data['end_time']) && $data['end_time']) {
            $endDateTime = Carbon::parse($data['end_time'])->setTimezone(config('app.timezone'));
            $data['end_time'] = $endDateTime;
        }
        // Set default delay_time if null
        if (! isset($data['delay_time']) || $data['delay_time'] === null) {
            $data['delay_time'] = '00:00';
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        $quantity = $this->data['qty'] ?? 0; // Total quantity (expected)
        $okQtys = $this->data['ok_qtys'] ?? 0;   // OK quantities
        $scrappedQtys = $this->data['scrapped_qtys'] ?? 0; // Scrapped quantities

        if ($this->data['status'] === 'Hold' && ($okQtys + $scrappedQtys) > $quantity) {
            Notification::make()
                ->title('Validation Error')
                ->body('The sum of OK quantities and scrapped quantities must not exceed the total quantity when the status is "Hold".')
                ->danger() // Marks the notification as an error
                ->send();
            $this->halt(); // Stop the save process
        }

        if ($this->data['status'] === 'Completed' && ($okQtys + $scrappedQtys) !== $quantity) {
            Notification::make()
                ->title('Validation Error')
                ->body('The sum of OK quantities and scrapped quantities must exactly match the total quantity when the status is "Completed".')
                ->danger()
                ->send();

            $this->halt(); // Stop the save process

        }

        // Check if operator is trying to start before/after allowed time (only on first start)
        $record = $this->getRecord();

        if (isset($this->data['status']) && $this->data['status'] === 'Start' && auth()->user()?->hasRole('Operator')) {
            $existingStartLog = $record->workOrderLogs()->where('status', 'Start')->exists();

            if (! $existingStartLog) {
                // Use the model's helper method to validate start time
                $validation = $record->canOperatorStartNow();

                if (! $validation['can_start']) {
                    Notification::make()
                        ->title($validation['reason'] === 'early_start' ? 'Cannot Start Before Planned Time' : 'Time Limit Exceeded')
                        ->body($validation['message'])
                        ->danger()
                        ->persistent()
                        ->send();

                    $this->halt(); // Stop the save process
                }
            }
        }

    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
