<?php

namespace App\Console\Commands;

use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestIndexPerformance extends Command
{
    protected $signature = 'test:index-performance {factory_id=1}';

    protected $description = 'Test database query performance with current indexes';

    public function handle(): int
    {
        $factoryId = (int) $this->argument('factory_id');

        $this->info("Testing Query Performance for Factory ID: {$factoryId}");
        $this->newLine();

        // Get database statistics
        $this->displayDatabaseStats($factoryId);
        $this->newLine();

        // Test basic queries
        $this->testBasicQueries($factoryId);
        $this->newLine();

        // Test KPI queries
        $this->testKpiQueries($factoryId);
        $this->newLine();

        // Test dashboard scenario
        $this->testDashboardScenario($factoryId);
        $this->newLine();

        // Test repeated queries (simulating real usage)
        $this->testRepeatedQueries($factoryId);

        return 0;
    }

    private function displayDatabaseStats(int $factoryId): void
    {
        $this->info('=== Database Statistics ===');

        $totalWorkOrders = WorkOrder::count();
        $factoryWorkOrders = WorkOrder::where('factory_id', $factoryId)->count();

        $statusCounts = WorkOrder::where('factory_id', $factoryId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Work Orders (All Factories)', $totalWorkOrders],
                ['Work Orders in Factory', $factoryWorkOrders],
                ['Assigned', $statusCounts->get('Assigned', 0)],
                ['Start', $statusCounts->get('Start', 0)],
                ['Hold', $statusCounts->get('Hold', 0)],
                ['Completed', $statusCounts->get('Completed', 0)],
                ['Waiting', $statusCounts->get('Waiting', 0)],
            ]
        );
    }

    private function testBasicQueries(int $factoryId): void
    {
        $this->info('=== Testing Basic Query Performance ===');

        $results = [];

        // Test 1: Active work orders (uses work_orders_kpi_reporting_idx)
        DB::enableQueryLog();
        $start = microtime(true);
        $activeCount = WorkOrder::where('factory_id', $factoryId)
            ->whereIn('status', ['Assigned', 'Start', 'Hold'])
            ->count();
        $time1 = round((microtime(true) - $start) * 1000, 2);
        $queries1 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $results[] = ['Active Work Orders', $activeCount, $time1.'ms', $queries1];

        // Test 2: Today's work orders (uses work_orders_created_status_idx)
        DB::enableQueryLog();
        $start = microtime(true);
        $todayCount = WorkOrder::where('factory_id', $factoryId)
            ->whereDate('created_at', today())
            ->count();
        $time2 = round((microtime(true) - $start) * 1000, 2);
        $queries2 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $results[] = ['Today\'s Work Orders', $todayCount, $time2.'ms', $queries2];

        // Test 3: Completed this month (uses work_orders_kpi_reporting_idx)
        DB::enableQueryLog();
        $start = microtime(true);
        $completedCount = WorkOrder::where('factory_id', $factoryId)
            ->where('status', 'Completed')
            ->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()])
            ->count();
        $time3 = round((microtime(true) - $start) * 1000, 2);
        $queries3 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $results[] = ['Completed This Month', $completedCount, $time3.'ms', $queries3];

        // Test 4: Machine schedule query (uses work_orders_machine_schedule_idx)
        $machineId = WorkOrder::where('factory_id', $factoryId)->value('machine_id');
        if ($machineId) {
            DB::enableQueryLog();
            $start = microtime(true);
            $machineCount = WorkOrder::where('factory_id', $factoryId)
                ->where('machine_id', $machineId)
                ->where('status', 'Start')
                ->whereNotNull('start_time')
                ->count();
            $time4 = round((microtime(true) - $start) * 1000, 2);
            $queries4 = count(DB::getQueryLog());
            DB::disableQueryLog();

            $results[] = ['Machine Schedule Query', $machineCount, $time4.'ms', $queries4];
        }

        $this->table(
            ['Query Type', 'Result', 'Time', 'Queries'],
            $results
        );
    }

    private function testKpiQueries(int $factoryId): void
    {
        $this->info('=== Testing KPI Query Performance ===');

        $startDate = Carbon::now()->startOfMonth();
        $endDate = Carbon::now();
        $results = [];

        // Test 1: Production Efficiency Calculation
        DB::enableQueryLog();
        $start = microtime(true);

        $query = WorkOrder::where('factory_id', $factoryId)
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalPlanned = $query->clone()->sum('qty');
        $totalActual = $query->clone()->sum('ok_qtys');
        $efficiency = $totalPlanned > 0 ? round(($totalActual / $totalPlanned) * 100, 2) : 0;

        $time1 = round((microtime(true) - $start) * 1000, 2);
        $queries1 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $results[] = ['Production Efficiency', $efficiency.'%', $time1.'ms', $queries1];

        // Test 2: Status Distribution
        DB::enableQueryLog();
        $start = microtime(true);

        $statusCounts = WorkOrder::where('factory_id', $factoryId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        $time2 = round((microtime(true) - $start) * 1000, 2);
        $queries2 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $results[] = ['Status Distribution', $statusCounts->count().' statuses', $time2.'ms', $queries2];

        // Test 3: Machine Utilization
        DB::enableQueryLog();
        $start = microtime(true);

        $machineStats = WorkOrder::where('work_orders.factory_id', $factoryId)
            ->join('machines', 'work_orders.machine_id', '=', 'machines.id')
            ->select([
                'machines.id',
                'machines.name',
                DB::raw('COUNT(*) as total_orders'),
                DB::raw('SUM(CASE WHEN work_orders.status IN ("Start", "Completed") THEN 1 ELSE 0 END) as active_orders'),
            ])
            ->groupBy('machines.id', 'machines.name')
            ->get();

        $time3 = round((microtime(true) - $start) * 1000, 2);
        $queries3 = count(DB::getQueryLog());
        DB::disableQueryLog();

        $results[] = ['Machine Utilization', $machineStats->count().' machines', $time3.'ms', $queries3];

        $this->table(
            ['KPI Metric', 'Result', 'Time', 'Queries'],
            $results
        );
    }

    private function testDashboardScenario(int $factoryId): void
    {
        $this->info('=== Testing Dashboard Load Scenario ===');
        $this->info('Simulating typical dashboard with multiple queries...');

        DB::enableQueryLog();
        $overallStart = microtime(true);

        // Dashboard typically loads multiple metrics at once
        $activeCount = WorkOrder::where('factory_id', $factoryId)
            ->whereIn('status', ['Assigned', 'Start'])
            ->count();

        $completedToday = WorkOrder::where('factory_id', $factoryId)
            ->where('status', 'Completed')
            ->whereDate('updated_at', today())
            ->count();

        $onHoldCount = WorkOrder::where('factory_id', $factoryId)
            ->where('status', 'Hold')
            ->count();

        $waitingCount = WorkOrder::where('factory_id', $factoryId)
            ->where('status', 'Waiting')
            ->count();

        $productionToday = WorkOrder::where('factory_id', $factoryId)
            ->whereDate('updated_at', today())
            ->sum('ok_qtys');

        $statusBreakdown = WorkOrder::where('factory_id', $factoryId)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $overallTime = round((microtime(true) - $overallStart) * 1000, 2);
        $totalQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Active Work Orders', $activeCount],
                ['Completed Today', $completedToday],
                ['On Hold', $onHoldCount],
                ['Waiting', $waitingCount],
                ['Production Today (units)', $productionToday],
            ]
        );

        $this->newLine();
        $this->info('📊 Dashboard Performance:');
        $this->info("   Total Load Time: {$overallTime}ms");
        $this->info("   Total Queries: {$totalQueries}");
        $this->info('   Average per Query: '.round($overallTime / $totalQueries, 2).'ms');
    }

    private function testRepeatedQueries(int $factoryId): void
    {
        $this->info('=== Testing Repeated Query Performance (Cache Simulation) ===');
        $this->info('Running same query 20 times to simulate dashboard auto-refresh...');

        $times = [];
        $iterations = 20;

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);

            WorkOrder::where('factory_id', $factoryId)
                ->whereIn('status', ['Assigned', 'Start'])
                ->count();

            $times[] = (microtime(true) - $start) * 1000;
        }

        $totalTime = array_sum($times);
        $avgTime = $totalTime / $iterations;
        $minTime = min($times);
        $maxTime = max($times);

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Iterations', $iterations],
                ['Total Time', round($totalTime, 2).'ms'],
                ['Average Time', round($avgTime, 2).'ms'],
                ['Min Time', round($minTime, 2).'ms'],
                ['Max Time', round($maxTime, 2).'ms'],
            ]
        );

        $this->newLine();
        $this->info('💡 Analysis:');
        $this->info("   Without cache: {$iterations} queries × ".round($avgTime, 2).'ms = '.round($totalTime, 2).'ms');
        $this->info('   With cache (5-min TTL): ~1 query + 19 cache hits ≈ '.round($avgTime + (19 * 0.5), 2).'ms');
        $this->info('   Potential improvement with cache: '.round((($totalTime - ($avgTime + 9.5)) / $totalTime) * 100, 1).'%');
    }
}
