<?php



namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkOrderLog extends Model
{
    use HasFactory;

    // Define the table name if it differs from the default
    protected $table = 'work_order_logs';

    // Define the fillable fields for mass assignment
    protected $fillable = [
        'work_order_id',
        'status',
        'changed_at',
        'user_id',
        'ok_qtys',
        'scrapped_qtys',
        'remaining',
        'scrapped_reason_id',
        'hold_reason_id'
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];


    // Define the relationship to the WorkOrder model
    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    // Define the relationship to the User model
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scrappedReason()
    {
        return $this->belongsTo(ScrappedReason::class);
    }

    public function holdReason()
    {
        return $this->belongsTo(holdReason::class);
    }
}
