<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;

class Factory extends Model
{
    use HasFactory,HasRoles,SoftDeletes;

    protected $fillable = ['name', 'slug'];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function operators(): HasMany
    {
        return $this->hasMany(Operator::class);
    }

    public function operatorproficiencies(): HasMany
    {
        return $this->hasMany(OperatorProficiency::class);
    }

    public function boms(): HasMany
    {
        return $this->hasMany(Bom::class);
    }

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class);
    }

    public function partnumbers(): HasMany
    {
        return $this->hasMany(PartNumber::class);
    }

    public function purchaseorders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function scrappedreasons(): HasMany
    {
        return $this->hasMany(ScrappedReason::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function workorders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function customerinformation(): HasMany
    {
        return $this->hasMany(CustomerInformation::class);
    }

    public function holdreasons(): HasMany
    {
        return $this->hasMany(HoldReason::class);
    }
    public function machineGroups()
    {
        return $this->hasMany(MachineGroup::class);
    }
}

