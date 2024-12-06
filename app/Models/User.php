<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use App\Models\Factory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\HasName;


class User extends Authenticatable implements FilamentUser,HasTenants,HasName
{
   
    use HasFactory, Notifiable,HasRoles,SoftDeletes;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'department_id',
        'emp_id',
        'factory_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function department()
    {
        return $this->belongsTo(Department::class);
    }
    public function operators()
    {
        return $this->hasMany(Operator::class);
    }


    public function getTenants(Panel $panel): Collection
    {
        return $this->factories;
    }
    
    public function factories(): BelongsToMany
    {
        return $this->belongsToMany(Factory::class);
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->factories->contains($tenant);
    }
    public function factory()
    {
        return $this->belongsTo(Factory::class);
    }

    public function canAccessPanel(Panel $panel): bool {
        return true;
    }
   
    public function getFilamentName(): string{
        return "{$this->first_name} {$this->last_name}";
    }
}


