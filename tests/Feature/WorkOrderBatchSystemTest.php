<?php

namespace Tests\Feature;

use App\Models\Bom;
use App\Models\Factory;
use App\Models\Machine;
use App\Models\Operator;
use App\Models\User;
use App\Models\WorkOrderBatch;
use App\Models\WorkOrderBatchKey;
use App\Models\WorkOrderDependency;
use App\Models\WorkOrderGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderBatchSystemTest extends TestCase
{
    use RefreshDatabase;

    protected $factory;

    protected $workOrderGroup;

    protected $rootWorkOrder;

    protected $dependentWorkOrder;

    protected $operator;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create factory record directly
        $this->factory = \App\Models\Factory::create([
            'name' => 'Test Factory',
            'address' => 'Test Address',
            'city' => 'Test City',
            'state' => 'Test State',
            'country' => 'Test Country',
            'zip_code' => '12345',
        ]);

        // Create user
        $this->user = User::factory()->create(['factory_id' => $this->factory->id]);

        // Create operator record directly
        $this->operator = \App\Models\Operator::create([
            'factory_id' => $this->factory->id,
            'user_id' => $this->user->id,
            'operator_proficiency_id' => 1,
        ]);

        // Create work order group
        $this->workOrderGroup = WorkOrderGroup::factory()->create([
            'factory_id' => $this->factory->id,
            'status' => 'active',
        ]);

        // Create machine and BOM for work orders
        $machine = \App\Models\Machine::create([
            'name' => 'Test Machine',
            'assetId' => 'MACH001',
            'factory_id' => $this->factory->id,
            'machine_group_id' => 1,
        ]);

        $bom = \App\Models\Bom::create([
            'unique_id' => 'BOM001',
            'machine_group_id' => 1,
            'operator_proficiency_id' => 1,
            'purchase_order_id' => 1,
        ]);

        // Create root work order directly
        $this->rootWorkOrder = \App\Models\WorkOrder::create([
            'unique_id' => 'WO001',
            'bom_id' => $bom->id,
            'qty' => 100,
            'machine_id' => $machine->id,
            'operator_id' => $this->operator->id,
            'start_time' => now(),
            'end_time' => now()->addHours(2),
            'status' => 'Assigned',
            'factory_id' => $this->factory->id,
            'work_order_group_id' => $this->workOrderGroup->id,
            'is_dependency_root' => true,
        ]);

        // Create dependent work order directly
        $this->dependentWorkOrder = \App\Models\WorkOrder::create([
            'unique_id' => 'WO002',
            'bom_id' => $bom->id,
            'qty' => 100,
            'machine_id' => $machine->id,
            'operator_id' => $this->operator->id,
            'start_time' => now()->addHours(3),
            'end_time' => now()->addHours(5),
            'status' => 'Waiting',
            'factory_id' => $this->factory->id,
            'work_order_group_id' => $this->workOrderGroup->id,
            'is_dependency_root' => false,
        ]);

        // Create dependency
        WorkOrderDependency::create([
            'work_order_group_id' => $this->workOrderGroup->id,
            'predecessor_work_order_id' => $this->rootWorkOrder->id,
            'successor_work_order_id' => $this->dependentWorkOrder->id,
            'dependency_type' => 'quantity_based',
            'required_quantity' => 25,
            'is_satisfied' => false,
        ]);
    }

    public function test_root_work_order_uses_batch_system(): void
    {
        expect($this->rootWorkOrder->usesBatchSystem())->toBeTrue();
        expect($this->rootWorkOrder->is_dependency_root)->toBeTrue();
    }

    public function test_dependent_work_order_uses_batch_system(): void
    {
        expect($this->dependentWorkOrder->usesBatchSystem())->toBeTrue();
        expect($this->dependentWorkOrder->is_dependency_root)->toBeFalse();
    }

    public function test_root_work_order_can_start_new_batch_without_keys(): void
    {
        expect($this->rootWorkOrder->canStartNewBatch())->toBeTrue();
        expect($this->rootWorkOrder->hasRequiredKeys())->toBeTrue(); // Root doesn't need keys
    }

    public function test_dependent_work_order_cannot_start_batch_without_keys(): void
    {
        expect($this->dependentWorkOrder->canStartNewBatch())->toBeFalse();
        expect($this->dependentWorkOrder->hasRequiredKeys())->toBeFalse();
    }

    public function test_root_work_order_can_create_and_start_batch(): void
    {
        $batch = $this->rootWorkOrder->createBatch(25);

        expect($batch)->toBeInstanceOf(WorkOrderBatch::class);
        expect($batch->batch_number)->toBe(1);
        expect($batch->planned_quantity)->toBe(25);
        expect($batch->status)->toBe('planned');

        $started = $batch->startBatch();

        expect($started)->toBeTrue();
        expect($batch->fresh()->status)->toBe('in_progress');
        expect($batch->fresh()->started_at)->not->toBeNull();
    }

    public function test_root_work_order_batch_completion_generates_key(): void
    {
        $batch = $this->rootWorkOrder->createBatch(25);
        $batch->startBatch();

        $completed = $batch->completeBatch(25);

        expect($completed)->toBeTrue();
        expect($batch->fresh()->status)->toBe('completed');
        expect($batch->fresh()->actual_quantity)->toBe(25);
        expect($batch->fresh()->completed_at)->not->toBeNull();

        // Check that a key was generated
        $key = $batch->fresh()->batchKey;
        expect($key)->toBeInstanceOf(WorkOrderBatchKey::class);
        expect($key->quantity_produced)->toBe(25);
        expect($key->is_consumed)->toBeFalse();
    }

    public function test_dependent_work_order_has_keys_after_root_completes_batch(): void
    {
        // Root completes a batch
        $batch = $this->rootWorkOrder->createBatch(25);
        $batch->startBatch();
        $batch->completeBatch(25);

        // Dependent should now have required keys
        $this->dependentWorkOrder->refresh();
        expect($this->dependentWorkOrder->hasRequiredKeys())->toBeTrue();
        expect($this->dependentWorkOrder->canStartNewBatch())->toBeTrue();
    }

    public function test_dependent_work_order_can_start_batch_with_keys(): void
    {
        // Root completes a batch to generate keys
        $rootBatch = $this->rootWorkOrder->createBatch(25);
        $rootBatch->startBatch();
        $rootBatch->completeBatch(25);

        $rootKey = $rootBatch->fresh()->batchKey;

        // Dependent can now start batch
        $dependentBatch = $this->dependentWorkOrder->createBatch(25, [
            [
                'work_order_id' => $this->rootWorkOrder->id,
                'dependency_type' => 'quantity_based',
                'quantity_needed' => 1,
                'work_order_name' => $this->rootWorkOrder->unique_id,
            ],
        ]);

        expect($dependentBatch)->toBeInstanceOf(WorkOrderBatch::class);

        $started = $dependentBatch->startBatch([$rootKey->id]);

        expect($started)->toBeTrue();
        expect($dependentBatch->fresh()->status)->toBe('in_progress');

        // Check that key was consumed
        expect($rootKey->fresh()->is_consumed)->toBeTrue();
        expect($rootKey->fresh()->consumed_by_work_order_id)->toBe($this->dependentWorkOrder->id);
    }

    public function test_operator_status_options_for_grouped_work_orders(): void
    {
        // Root work order without batch should require batch start
        $options = $this->rootWorkOrder->getOperatorStatusOptions();
        expect($options)->toBe(['Assigned' => 'Assigned (Start new batch first)']);

        // After starting batch, should allow Start
        $batch = $this->rootWorkOrder->createBatch(25);
        $batch->startBatch();

        $options = $this->rootWorkOrder->fresh()->getOperatorStatusOptions();
        expect($options)->toBe(['Start' => 'Start']);

        // Dependent work order without keys should show waiting
        $options = $this->dependentWorkOrder->getOperatorStatusOptions();
        expect($options)->toBe(['Waiting' => 'Waiting (Dependencies not satisfied)']);
    }

    public function test_operator_cannot_change_status_without_active_batch(): void
    {
        $canChange = $this->rootWorkOrder->canOperatorChangeStatus('Start');

        expect($canChange['can_change'])->toBeFalse();
        expect($canChange['reason'])->toBe('No active batch in progress');
        expect($canChange['required_action'])->toBe('start_new_batch');
    }

    public function test_operator_can_change_status_with_active_batch(): void
    {
        $batch = $this->rootWorkOrder->createBatch(25);
        $batch->startBatch();

        $canChange = $this->rootWorkOrder->fresh()->canOperatorChangeStatus('Start');

        expect($canChange['can_change'])->toBeTrue();
        expect($canChange['reason'])->toBeNull();
        expect($canChange['required_action'])->toBeNull();
    }

    public function test_get_required_keys_info_for_dependent_work_order(): void
    {
        $keysInfo = $this->dependentWorkOrder->getRequiredKeysInfo();

        expect($keysInfo)->toHaveCount(1);
        expect($keysInfo[0]['predecessor_name'])->toBe($this->rootWorkOrder->unique_id);
        expect($keysInfo[0]['is_satisfied'])->toBeFalse();
        expect($keysInfo[0]['available_keys_count'])->toBe(0);

        // After root generates key
        $batch = $this->rootWorkOrder->createBatch(25);
        $batch->startBatch();
        $batch->completeBatch(25);

        $keysInfo = $this->dependentWorkOrder->fresh()->getRequiredKeysInfo();
        expect($keysInfo[0]['is_satisfied'])->toBeTrue();
        expect($keysInfo[0]['available_keys_count'])->toBe(1);
    }

    public function test_multiple_batches_and_key_consumption(): void
    {
        // Root creates multiple batches
        $batch1 = $this->rootWorkOrder->createBatch(25);
        $batch1->startBatch();
        $batch1->completeBatch(25);

        $batch2 = $this->rootWorkOrder->createBatch(25);
        $batch2->startBatch();
        $batch2->completeBatch(25);

        // Should have 2 available keys
        $availableKeys = $this->rootWorkOrder->getAvailableKeys();
        expect($availableKeys)->toHaveCount(2);

        // Dependent consumes one key
        $dependentBatch1 = $this->dependentWorkOrder->createBatch(25);
        $dependentBatch1->startBatch([$availableKeys->first()->id]);

        // Should have 1 available key left
        $availableKeys = $this->rootWorkOrder->fresh()->getAvailableKeys();
        expect($availableKeys)->toHaveCount(1);

        // Dependent can start another batch with remaining key
        $dependentBatch2 = $this->dependentWorkOrder->createBatch(25);
        $started = $dependentBatch2->startBatch([$availableKeys->first()->id]);

        expect($started)->toBeTrue();

        // Should have no available keys left
        $availableKeys = $this->rootWorkOrder->fresh()->getAvailableKeys();
        expect($availableKeys)->toHaveCount(0);
    }

    public function test_batch_progress_tracking(): void
    {
        // Create and complete some batches
        $batch1 = $this->rootWorkOrder->createBatch(25);
        $batch1->startBatch();
        $batch1->completeBatch(25);

        $batch2 = $this->rootWorkOrder->createBatch(30);
        $batch2->startBatch();
        $batch2->completeBatch(30);

        $progress = $this->rootWorkOrder->fresh()->getBatchProgress();

        expect($progress['total_planned'])->toBe(55);
        expect($progress['total_completed'])->toBe(55);
        expect($progress['percentage'])->toBe(100.0);
        expect($progress['batches_completed'])->toBe(2);
        expect($progress['batches_total'])->toBe(2);
    }

    public function test_cannot_start_batch_while_one_is_in_progress(): void
    {
        $batch1 = $this->rootWorkOrder->createBatch(25);
        $batch1->startBatch();

        // Should not be able to start another batch
        expect($this->rootWorkOrder->fresh()->canStartNewBatch())->toBeFalse();

        $batch2 = $this->rootWorkOrder->createBatch(25);
        $started = $batch2->startBatch();

        expect($started)->toBeFalse();
        expect($batch2->fresh()->status)->toBe('planned');
    }
}
