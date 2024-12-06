<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Factory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = [
        'name',
        'description',
        'factory_id'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
    public function machines()
    {
        return $this->hasMany(Machine::class);
    }
    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
