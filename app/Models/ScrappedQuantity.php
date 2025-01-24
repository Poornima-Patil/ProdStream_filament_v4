<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScrappedQuantity extends Model
{
    use HasFactory,SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'work_order_id',
        'quantity',
        'reason_id',
    ];

    /**
     * Get the work order associated with this scrapped quantity.
     */
    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Get the scrap reason associated with this scrapped quantity.
     */
    public function reason()
    {
        return $this->belongsTo(ScrappedReason::class, 'reason_id');
    }
}
