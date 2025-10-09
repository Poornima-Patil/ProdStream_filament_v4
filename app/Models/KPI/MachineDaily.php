<?php

namespace App\Models\KPI;

use App\Models\Factory;
use App\Models\Machine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MachineDaily extends Model
{
    protected $table = 'kpi_machine_daily';

    protected $fillable = [
        'factory_id',
        'machine_id',
        'summary_date',
        'utilization_rate',
        'uptime_hours',
        'downtime_hours',
        'planned_downtime_hours',
        'unplanned_downtime_hours',
        'units_produced',
        'work_orders_completed',
        'average_cycle_time',
        'quality_rate',
        'scrap_rate',
        'first_pass_yield',
        'machine_performance_index',
        'machine_reliability_score',
        'availability_rate',
        'mtbf',
        'mttr',
        'failure_count',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'summary_date' => 'date',
            'utilization_rate' => 'decimal:2',
            'uptime_hours' => 'decimal:2',
            'downtime_hours' => 'decimal:2',
            'planned_downtime_hours' => 'decimal:2',
            'unplanned_downtime_hours' => 'decimal:2',
            'average_cycle_time' => 'decimal:2',
            'quality_rate' => 'decimal:2',
            'scrap_rate' => 'decimal:2',
            'first_pass_yield' => 'decimal:2',
            'machine_performance_index' => 'decimal:2',
            'machine_reliability_score' => 'decimal:2',
            'availability_rate' => 'decimal:2',
            'mtbf' => 'decimal:2',
            'mttr' => 'decimal:2',
            'calculated_at' => 'datetime',
        ];
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
