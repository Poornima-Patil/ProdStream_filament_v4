<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Factory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperatorProficiency extends Model
{
    use HasFactory,SoftDeletes;
    protected $fillable = [
        'proficiency',
        'description',
        'factory_id'
    ];

    public function operators()
    {
        return $this->hasMany(Operator::class);
    }

    public function boms()
    {
        return $this->hasMany(Bom::class); // Adjusted to use 'Bom' instead of 'BOM'
    }
    public function factory(): BelongsTo
    {
        return $this->belongsTo(Factory::class);
    }
}
