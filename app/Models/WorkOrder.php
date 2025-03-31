<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class WorkOrder extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'bom_id',
        'qty',
        'machine_id',
        'operator_id',
        'start_time',
        'end_time',
        'status',
        'ok_qtys',
        'scrapped_qtys',
        'unique_id',
        'hold_reason_id',
        'material_batch',
        'factory_id',
    ];

    protected $casts = [
        'hold_reason_id' => 'integer',
        'machine_id' => 'integer', // Ensures it's treated as an integer
    ];

    public function bom()
    {
        return $this->belongsTo(Bom::class, 'bom_id');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class, 'operator_id');
    }

    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }

    public function scrappedQuantities()
    {
        return $this->hasMany(WorkOrderQuantity::class)->where('type', 'scrapped');
    }

    public function okQuantities()
    {
        return $this->hasMany(WorkOrderQuantity::class)->where('type', 'ok');
    }

    public function quantities()
    {
        return $this->hasMany(WorkOrderQuantity::class);
    }

    public function holdReason()
    {
        return $this->belongsTo(HoldReason::class, 'hold_reason_id');
    }

    public function workOrderLogs()
    {
        return $this->hasMany(WorkOrderLog::class);
    }

    public function createWorkOrderLog($newStatus)
    {
        // Get the latest quantity entry for this work order
        $latestQuantity = $this->quantities()->latest()->first();

        // Calculate totals from the latest quantity entry
        $okQtys = $latestQuantity ? $latestQuantity->ok_quantity : 0;
        $scrappedQtys = $latestQuantity ? $latestQuantity->scrapped_quantity : 0;
        $scrappedReasonId = $latestQuantity ? $latestQuantity->reason_id : null;

        $log = WorkOrderLog::create([
            'work_order_id' => $this->id,
            'status' => $newStatus,
            'changed_at' => now(),
            'user_id' => Auth::id(),
            'ok_qtys' => $okQtys,
            'scrapped_qtys' => $scrappedQtys,
            'remaining' => $this->qty - ($okQtys + $scrappedQtys),
            'scrapped_reason_id' => $scrappedReasonId,
            'hold_reason_id' => $this->hold_reason_id,
        ]);

        return $log;
    }

    protected static function booted()
    {
        static::created(function ($workOrder) {
            $workOrder->createWorkOrderLog('Assigned');
        });

        static::updated(function ($workOrder) {
            if ($workOrder->isDirty('status')) {
                $workOrder->createWorkOrderLog($workOrder->status);
            }
        });

        // Add observer for WorkOrderQuantity
        static::created(function ($workOrder) {
            // Get the latest work order log
            $latestLog = $workOrder->workOrderLogs()->latest()->first();

            if (! $latestLog) {
                // If no log exists, create one
                $latestLog = $workOrder->createWorkOrderLog($workOrder->status);
            }

            // Update any existing quantities with the work_order_log_id
            $workOrder->quantities()->update(['work_order_log_id' => $latestLog->id]);
        });
    }

    // Define the relationship with InfoMessage
    public function infoMessages()
    {
        return $this->hasMany(InfoMessage::class);
    }

    // Add this new method to handle quantity creation
    public function createQuantity(array $data)
    {
        // Get the latest work order log
        $latestLog = $this->workOrderLogs()->latest()->first();

        if (! $latestLog) {
            // If no log exists, create one
            $latestLog = $this->createWorkOrderLog($this->status);
        }

        // Add the work_order_log_id to the data
        $data['work_order_log_id'] = $latestLog->id;

        // Create the quantity
        return $this->quantities()->create($data);
    }
}
