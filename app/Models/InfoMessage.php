<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InfoMessage extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'work_order_id',
        'user_id',
        'message',
        'priority',
    ];

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    // Define the relationship with User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
