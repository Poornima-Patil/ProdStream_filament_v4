<?php

namespace Tests\Feature;

use App\Models\Factory;
use App\Models\Machine;
use App\Models\WorkOrder;
use App\Models\WorkOrderLog;
use App\Services\KPI\OperationalKPIService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupTimeKPITest extends TestCase
{
    use RefreshDatabase;

    protected Factory $factory;

    protected OperationalKPIService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = Factory::create([
            'name' => 'Test Factory',
            'address' => 'Test Address',
            'city' => 'Test City',
            'state' => 'Test State',
            'country' => 'Test Country',
            'zip_code' => '12345',
        ]);

        $this->service = new OperationalKPIService($this->factory);
    }

    it('can calculate setup time analytics for yesterday', function () {
        // Create test machines
        $machine1 = Machine::create([
            'factory_id' => $this->factory->id,
            'name' => 'Machine-001',
            'assetId' => 'M001',
        ]);

        $machine2 = Machine::create([
            'factory_id' => $this->factory->id,
            'name' => 'Machine-002',
            'assetId' => 'M002',
        ]);

        // Create work orders for yesterday
        $yesterday = Carbon::yesterday();
        $dayStart = $yesterday->copy()->startOfDay();

        // WO1: 30 minute setup (Assigned 06:00, Start 06:30)
        $wo1 = WorkOrder::create([
            'factory_id' => $this->factory->id,
            'machine_id' => $machine1->id,
            'start_time' => $dayStart->copy()->addHours(6),
            'status' => 'Start',
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo1->id,
            'status' => 'Assigned',
            'changed_at' => $dayStart->copy()->addHours(6),
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo1->id,
            'status' => 'Start',
            'changed_at' => $dayStart->copy()->addHours(6)->addMinutes(30),
        ]);

        // WO2: 45 minute setup (Assigned 08:00, Start 08:45)
        $wo2 = WorkOrder::create([
            'factory_id' => $this->factory->id,
            'machine_id' => $machine1->id,
            'start_time' => $dayStart->copy()->addHours(8),
            'status' => 'Start',
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo2->id,
            'status' => 'Assigned',
            'changed_at' => $dayStart->copy()->addHours(8),
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo2->id,
            'status' => 'Start',
            'changed_at' => $dayStart->copy()->addHours(8)->addMinutes(45),
        ]);

        // WO3: 20 minute setup on Machine-002 (Assigned 10:00, Start 10:20)
        $wo3 = WorkOrder::create([
            'factory_id' => $this->factory->id,
            'machine_id' => $machine2->id,
            'start_time' => $dayStart->copy()->addHours(10),
            'status' => 'Start',
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo3->id,
            'status' => 'Assigned',
            'changed_at' => $dayStart->copy()->addHours(10),
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo3->id,
            'status' => 'Start',
            'changed_at' => $dayStart->copy()->addHours(10)->addMinutes(20),
        ]);

        // Get analytics
        $analytics = $this->service->getSetupTimeAnalytics([
            'time_period' => 'yesterday',
        ]);

        // Verify structure
        expect($analytics)->toHaveKey('primary_period');
        expect($analytics['primary_period'])->toHaveKey('summary');
        expect($analytics['primary_period'])->toHaveKey('daily_breakdown');
        expect($analytics['primary_period'])->toHaveKey('machine_breakdown');

        // Verify summary calculations
        $summary = $analytics['primary_period']['summary'];
        expect($summary['total_setups'])->toBe(3);
        expect($summary['total_setup_minutes'])->toBe(95); // 30 + 45 + 20
        expect($summary['total_setup_time'])->toBe(round(95 / 60, 2)); // ~1.58 hours
        expect($summary['avg_setup_duration'])->toBe(round(95 / 3, 2)); // ~31.67 minutes
        expect($summary['machines_with_setups'])->toBe(2);
        expect($summary['days_analyzed'])->toBe(1);

        // Verify daily breakdown
        $dailyBreakdown = $analytics['primary_period']['daily_breakdown'];
        expect($dailyBreakdown)->toHaveCount(1);
        expect($dailyBreakdown[0]['total_setups'])->toBe(3);
        expect($dailyBreakdown[0]['total_setup_time'])->toBe(95);
        expect($dailyBreakdown[0]['avg_setup_time'])->toBe(round(95 / 3, 2));

        // Verify machine breakdown
        $machineBreakdown = $analytics['primary_period']['machine_breakdown'];
        expect($machineBreakdown)->toHaveCount(2);

        // Machine-001 should be first (75 minutes total)
        expect($machineBreakdown[0]['machine_id'])->toBe($machine1->id);
        expect($machineBreakdown[0]['total_setup_time'])->toBe(75); // 30 + 45
        expect($machineBreakdown[0]['total_setups'])->toBe(2);
        expect($machineBreakdown[0]['avg_setup_time'])->toBe(round(75 / 2, 2)); // 37.5

        // Machine-002 should be second (20 minutes total)
        expect($machineBreakdown[1]['machine_id'])->toBe($machine2->id);
        expect($machineBreakdown[1]['total_setup_time'])->toBe(20);
        expect($machineBreakdown[1]['total_setups'])->toBe(1);
        expect($machineBreakdown[1]['avg_setup_time'])->toBe(20);
    });

    it('can filter setup time by machine', function () {
        // Create machines
        $machine1 = Machine::create([
            'factory_id' => $this->factory->id,
            'name' => 'Machine-001',
            'assetId' => 'M001',
        ]);

        $machine2 = Machine::create([
            'factory_id' => $this->factory->id,
            'name' => 'Machine-002',
            'assetId' => 'M002',
        ]);

        $yesterday = Carbon::yesterday();
        $dayStart = $yesterday->copy()->startOfDay();

        // Create WO on Machine-001
        $wo1 = WorkOrder::create([
            'factory_id' => $this->factory->id,
            'machine_id' => $machine1->id,
            'start_time' => $dayStart->copy()->addHours(6),
            'status' => 'Start',
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo1->id,
            'status' => 'Assigned',
            'changed_at' => $dayStart->copy()->addHours(6),
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo1->id,
            'status' => 'Start',
            'changed_at' => $dayStart->copy()->addHours(6)->addMinutes(30),
        ]);

        // Create WO on Machine-002
        $wo2 = WorkOrder::create([
            'factory_id' => $this->factory->id,
            'machine_id' => $machine2->id,
            'start_time' => $dayStart->copy()->addHours(8),
            'status' => 'Start',
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo2->id,
            'status' => 'Assigned',
            'changed_at' => $dayStart->copy()->addHours(8),
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo2->id,
            'status' => 'Start',
            'changed_at' => $dayStart->copy()->addHours(8)->addMinutes(20),
        ]);

        // Filter by Machine-001
        $analytics = $this->service->getSetupTimeAnalytics([
            'time_period' => 'yesterday',
            'machine_id' => $machine1->id,
        ]);

        $summary = $analytics['primary_period']['summary'];
        expect($summary['total_setups'])->toBe(1);
        expect($summary['total_setup_minutes'])->toBe(30);
        expect($summary['machines_with_setups'])->toBe(1);
    });

    it('can calculate setup time with comparison', function () {
        // Create machine
        $machine = Machine::create([
            'factory_id' => $this->factory->id,
            'name' => 'Machine-001',
            'assetId' => 'M001',
        ]);

        $yesterday = Carbon::yesterday();
        $dayStart = $yesterday->copy()->startOfDay();

        // Yesterday: 30 minute setup
        $wo1 = WorkOrder::create([
            'factory_id' => $this->factory->id,
            'machine_id' => $machine->id,
            'start_time' => $dayStart->copy()->addHours(6),
            'status' => 'Start',
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo1->id,
            'status' => 'Assigned',
            'changed_at' => $dayStart->copy()->addHours(6),
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo1->id,
            'status' => 'Start',
            'changed_at' => $dayStart->copy()->addHours(6)->addMinutes(30),
        ]);

        // Day before yesterday: 40 minute setup
        $twoDaysAgo = $yesterday->copy()->subDay();
        $oldDayStart = $twoDaysAgo->copy()->startOfDay();

        $wo2 = WorkOrder::create([
            'factory_id' => $this->factory->id,
            'machine_id' => $machine->id,
            'start_time' => $oldDayStart->copy()->addHours(6),
            'status' => 'Start',
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo2->id,
            'status' => 'Assigned',
            'changed_at' => $oldDayStart->copy()->addHours(6),
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo2->id,
            'status' => 'Start',
            'changed_at' => $oldDayStart->copy()->addHours(6)->addMinutes(40),
        ]);

        // Get analytics with comparison
        $analytics = $this->service->getSetupTimeAnalytics([
            'time_period' => 'yesterday',
            'enable_comparison' => true,
            'comparison_type' => 'previous_period',
        ]);

        // Verify both periods exist
        expect($analytics)->toHaveKey('primary_period');
        expect($analytics)->toHaveKey('comparison_period');
        expect($analytics)->toHaveKey('comparison_analysis');

        // Verify comparison analysis
        $comparison = $analytics['comparison_analysis'];
        expect($comparison)->toHaveKey('total_setup_time');
        expect($comparison)->toHaveKey('avg_setup_duration');

        // Current: 30 minutes, Previous: 40 minutes
        expect($comparison['avg_setup_duration']['current'])->toBe(30);
        expect($comparison['avg_setup_duration']['previous'])->toBe(40);
        expect($comparison['avg_setup_duration']['difference'])->toBe(-10);
        expect($comparison['avg_setup_duration']['status'])->toBe('improved'); // Lower is better
    });

    it('returns empty data when no setups found', function () {
        // Don't create any work orders

        $analytics = $this->service->getSetupTimeAnalytics([
            'time_period' => 'yesterday',
        ]);

        $summary = $analytics['primary_period']['summary'];
        expect($summary['total_setups'])->toBe(0);
        expect($summary['total_setup_time'])->toBe(0);
        expect($summary['avg_setup_duration'])->toBe(0);
        expect($summary['machines_with_setups'])->toBe(0);
    });

    it('ignores wo without start log', function () {
        // Create machine
        $machine = Machine::create([
            'factory_id' => $this->factory->id,
            'name' => 'Machine-001',
            'assetId' => 'M001',
        ]);

        $yesterday = Carbon::yesterday();
        $dayStart = $yesterday->copy()->startOfDay();

        // Create WO with Assigned log but no Start log
        $wo = WorkOrder::create([
            'factory_id' => $this->factory->id,
            'machine_id' => $machine->id,
            'start_time' => $dayStart->copy()->addHours(6),
            'status' => 'Assigned', // Still in Assigned state
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo->id,
            'status' => 'Assigned',
            'changed_at' => $dayStart->copy()->addHours(6),
        ]);

        // No Start log created

        $analytics = $this->service->getSetupTimeAnalytics([
            'time_period' => 'yesterday',
        ]);

        $summary = $analytics['primary_period']['summary'];
        expect($summary['total_setups'])->toBe(0);
        expect($summary['total_setup_time'])->toBe(0);
    });

    it('calculates setup percentage of available time', function () {
        // Create machine
        $machine = Machine::create([
            'factory_id' => $this->factory->id,
            'name' => 'Machine-001',
            'assetId' => 'M001',
        ]);

        $yesterday = Carbon::yesterday();
        $dayStart = $yesterday->copy()->startOfDay();

        // Create setup of 120 minutes (2 hours)
        $wo = WorkOrder::create([
            'factory_id' => $this->factory->id,
            'machine_id' => $machine->id,
            'start_time' => $dayStart->copy()->addHours(6),
            'status' => 'Start',
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo->id,
            'status' => 'Assigned',
            'changed_at' => $dayStart->copy()->addHours(6),
        ]);

        WorkOrderLog::create([
            'work_order_id' => $wo->id,
            'status' => 'Start',
            'changed_at' => $dayStart->copy()->addHours(6)->addMinutes(120),
        ]);

        $analytics = $this->service->getSetupTimeAnalytics([
            'time_period' => 'yesterday',
        ]);

        $summary = $analytics['primary_period']['summary'];
        // 2 hours / 8 hour shift = 25%
        expect($summary['avg_setup_percentage'])->toBe(25.0);
    });
}
