<?php

namespace App\Models\KPI;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MachineStatusDaily extends Model
{
    use HasFactory;

    protected $table = 'kpi_machine_status_daily';

    protected $fillable = [
        'factory_id',
        'summary_date',
        'running_count',
        'setup_count',
        'hold_count',
        'scheduled_count',
        'idle_count',
        'total_machines',
        'calculated_at',
    ];

    protected $casts = [
        'summary_date' => 'date',
        'calculated_at' => 'datetime',
    ];
}
