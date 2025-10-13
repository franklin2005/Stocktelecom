<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'tech_code',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
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

    /**
     * Scope to only admin users.
     */
    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('role', 'admin');
    }

    /**
     * Scope to only super administrator users.
     */
    public function scopeSuperAdmins(Builder $query): Builder
    {
        return $query->where('role', 'super_admin');
    }

    /**
     * Scope to only technician users.
     */
    public function scopeTechnicians(Builder $query): Builder
    {
        return $query->where('role', 'technician');
    }

    /**
     * Scope to only logistics users.
     */
    public function scopeLogistics(Builder $query): Builder
    {
        return $query->where('role', 'logistics');
    }

    /**
     * User stock location relation.
     */
    public function stockLocation()
    {
        return $this->hasOne(StockLocation::class, 'ref_id')
            ->where('location_type', 'user');
    }

    /**
     * Work orders assigned to the user.
     */
    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class, 'technician_id');
    }

    /**
     * Transfers initiated by the user.
     */
    public function initiatedTransfers()
    {
        return $this->hasMany(Transfer::class, 'initiator_user_id');
    }
}
