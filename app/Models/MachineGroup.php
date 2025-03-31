<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MachineGroup extends Model
{
    use HasFactory, SoftDeletes;

    // Optionally specify the table name if it's not the plural form of the model
    protected $table = 'machine_groups';

    // Specify the attributes that are mass assignable
    protected $fillable = [
        'group_name',
        'description',
        'factory_id',
    ];

    // Specify the attributes that are not mass assignable (if needed)
    // protected $guarded = [];

    // Optionally, specify the date format for the softDeletes column
    protected $dates = ['deleted_at'];

    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }

    public function machines()
    {
        return $this->hasMany(Machine::class);
    }

    public function boms()
    {
        return $this->hasMany(Bom::class); // Adjusted to use 'Bom' instead of 'BOM'
    }
}
