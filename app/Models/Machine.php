<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = [
        'name',
        'assetId',
        'status',
        'department_id',
        'factory_id'
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function boms()
    {
        return $this->hasMany(Bom::class); // Adjusted to use 'Bom' instead of 'BOM'
    }

    public function workOrders() {
        return $this->hasMany(WorkOrder::class);
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('status', 1); // Assuming 'status' is 1 for Active
    }
    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
