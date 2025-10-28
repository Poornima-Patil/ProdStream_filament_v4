<?php

namespace App\Livewire;

use App\Models\WorkOrder;
use App\Models\WorkOrderBatchKey;
use Illuminate\Support\Collection;
use Livewire\Component;

class BatchStartModal extends Component
{
    public WorkOrder $workOrder;

    public int $plannedQuantity = 25;

    public array $selectedKeys = [];

    public array $requiredKeys = [];

    public bool $showModal = false;

    protected $rules = [
        'plannedQuantity' => 'required|integer|min:1',
        'selectedKeys' => 'array',
    ];

    public function mount(WorkOrder $workOrder)
    {
        $this->workOrder = $workOrder;
        $this->calculateRequiredKeys();
    }

    public function calculateRequiredKeys()
    {
        if (! $this->workOrder->usesBatchSystem()) {
            $this->requiredKeys = [];

            return;
        }

        // Get dependencies for this work order
        $dependencies = $this->workOrder->getIncomingDependencies()->with('predecessor')->get();

        $this->requiredKeys = $dependencies->map(function ($dependency) {
            return [
                'work_order_id' => $dependency->predecessor_work_order_id,
                'work_order_name' => $dependency->predecessor->unique_id,
                'quantity_needed' => 1, // For now, assume 1 key per dependency
                'dependency_type' => $dependency->dependency_type,
            ];
        })->toArray();
    }

    public function getAvailableKeysProperty(): Collection
    {
        if (! $this->workOrder->usesBatchSystem()) {
            return collect();
        }

        $groupId = $this->workOrder->work_order_group_id;

        return WorkOrderBatchKey::availableInGroup($groupId)
            ->with('workOrder')
            ->get()
            ->groupBy('work_order_id');
    }

    public function openModal()
    {
        $this->showModal = true;
        $this->calculateRequiredKeys();
        $this->selectedKeys = [];
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->selectedKeys = [];
    }

    public function toggleKey($keyId)
    {
        if (in_array($keyId, $this->selectedKeys)) {
            $this->selectedKeys = array_diff($this->selectedKeys, [$keyId]);
        } else {
            $this->selectedKeys[] = $keyId;
        }
    }

    public function validateKeySelection()
    {
        $errors = [];

        // Check if we have enough keys selected for each required work order
        foreach ($this->requiredKeys as $requirement) {
            $selectedForWorkOrder = collect($this->selectedKeys)->filter(function ($keyId) use ($requirement) {
                $key = WorkOrderBatchKey::find($keyId);

                return $key && $key->work_order_id == $requirement['work_order_id'];
            })->count();

            if ($selectedForWorkOrder < $requirement['quantity_needed']) {
                $errors[] = "Need {$requirement['quantity_needed']} key(s) from {$requirement['work_order_name']}, but only {$selectedForWorkOrder} selected.";
            }
        }

        return $errors;
    }

    public function startBatch()
    {
        $this->validate();

        // Validate key selection
        $keyErrors = $this->validateKeySelection();
        if (! empty($keyErrors)) {
            foreach ($keyErrors as $error) {
                $this->addError('selectedKeys', $error);
            }

            return;
        }

        try {
            // Create the batch
            $batch = $this->workOrder->createBatch($this->plannedQuantity, $this->requiredKeys);

            if ($batch) {
                // Start the batch with selected keys
                $started = $batch->startBatch($this->selectedKeys);

                if ($started) {
                    // Update work order status to 'Start'
                    $this->workOrder->update([
                        'status' => 'Start',
                        'material_batch' => $batch->batchKey?->key_code ?? "BATCH-{$batch->batch_number}-".now()->format('Ymd'),
                    ]);

                    // Log key consumption in Work Order Group logs if keys were consumed
                    if (! empty($this->selectedKeys)) {
                        \App\Models\WorkOrderGroupLog::logKeyConsumption(
                            $this->workOrder,
                            $this->selectedKeys,
                            $batch->batch_number
                        );
                    }

                    // Create work order log
                    $existingLog = $this->workOrder->workOrderLogs()->where('status', 'Start')->first();
                    if (! $existingLog) {
                        $this->workOrder->createWorkOrderLog('Start');
                    }

                    $this->dispatch('batch-started', [
                        'message' => "Work Order {$this->workOrder->unique_id} started successfully with batch {$batch->batch_number}",
                        'batch_id' => $batch->id,
                        'status_updated' => true,
                    ]);

                    $this->closeModal();

                    // Refresh the page to show updated status
                    $this->redirect(request()->header('Referer'));
                } else {
                    $this->addError('selectedKeys', 'Failed to start batch. Keys may have been consumed by another process.');
                }
            } else {
                $this->addError('plannedQuantity', 'Failed to create batch.');
            }
        } catch (\Exception $e) {
            $this->addError('selectedKeys', 'Error starting batch: '.$e->getMessage());
        }
    }

    public function canStartWithoutKeys()
    {
        return empty($this->requiredKeys) || $this->workOrder->is_dependency_root;
    }

    public function render()
    {
        return view('livewire.batch-start-modal', [
            'availableKeys' => $this->availableKeys,
            'canStartWithoutKeys' => $this->canStartWithoutKeys(),
        ]);
    }
}
