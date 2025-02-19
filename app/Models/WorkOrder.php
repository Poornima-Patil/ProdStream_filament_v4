<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\WorkOrderLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;




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
            'machine_id' => 'integer' // Ensures it's treated as an integer
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
        return $this->hasMany(ScrappedQuantity::class);
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

                    // Assumes the WorkOrder model has a `scrappedQuantities` relationship
                    $scrappedQuantity = $this->scrappedQuantities->first(); // Retrieves the first item in the collection

                    // Extract the reason_id (scrapped_reason_id)
                    $scrappedReasonId = $scrappedQuantity ? $scrappedQuantity->reason_id : null;
        $log = WorkOrderLog::create([
            'work_order_id' => $this->id,
            'status' => $newStatus,
            'changed_at' => now(),
            'user_id' => Auth::id(),
            'ok_qtys' => $this->ok_qtys ?? 0,
            'scrapped_qtys' => $this->scrapped_qtys ?? 0,
            'remaining' => $this->qty - (($this->ok_qtys ?? 0) + ($this->scrapped_qtys ?? 0)),
            'scrapped_reason_id' => $scrappedReasonId,
            'hold_reason_id' => $this->hold_reason_id
        ]);
        $log->save();
    }
   
    protected static function booted()
    {
        static::created(function ($workOrder) {
            DB::afterCommit(function () use ($workOrder) {
                $workOrder->createWorkOrderLog('Assigned');
            });
        });
    
        static::updated(function ($workOrder) {
            if ($workOrder->isDirty('status')) {
                DB::afterCommit(function () use ($workOrder) {
                    $workOrder->createWorkOrderLog($workOrder->status);
                });
            }
        });
    }

    // Define the relationship with InfoMessage
    public function infoMessages()
    {
        return $this->hasMany(InfoMessage::class);
    }

}
