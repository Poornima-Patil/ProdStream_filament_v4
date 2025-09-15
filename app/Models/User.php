<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasName, HasTenants
{
    use HasFactory, HasRoles,Notifiable,SoftDeletes;

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
        'factory_id',
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

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getFilamentName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function hasRole($roles, string $guard = null): bool
    {
        $factoryId = $this->factory_id ?? Filament::getTenant()?->id;

        if (!$factoryId) {
            return parent::hasRole($roles, $guard);
        }

        if (is_string($roles)) {
            return $this->roles()
                ->where('name', $roles)
                ->where('factory_id', $factoryId)
                ->where('guard_name', $guard ?? config('auth.defaults.guard'))
                ->exists();
        }

        if (is_array($roles)) {
            foreach ($roles as $role) {
                if ($this->hasRole($role, $guard)) {
                    return true;
                }
            }
            return false;
        }

        return parent::hasRole($roles, $guard);
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $factoryId = $this->factory_id ?? Filament::getTenant()?->id;

        if (!$factoryId) {
            return parent::hasPermissionTo($permission, $guardName);
        }

        $permissionName = is_string($permission) ? $permission : $permission->name;
        $guard = $guardName ?? config('auth.defaults.guard');

        // Check direct permissions
        $hasDirectPermission = $this->permissions()
            ->where('name', $permissionName)
            ->where('guard_name', $guard)
            ->where('factory_id', $factoryId)
            ->exists();

        if ($hasDirectPermission) {
            return true;
        }

        // Check permissions via roles
        return $this->roles()
            ->where('factory_id', $factoryId)
            ->where('guard_name', $guard)
            ->whereHas('permissions', function ($query) use ($permissionName, $guard, $factoryId) {
                $query->where('name', $permissionName)
                      ->where('guard_name', $guard)
                      ->where('factory_id', $factoryId);
            })
            ->exists();
    }
}
