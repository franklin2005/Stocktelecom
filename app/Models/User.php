<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * atributos asignables masivamente.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'tech_code',
    ];

    /**
     * atributos ocultos.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
    * atributos casteados
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * buscar administradores.
     */
    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('role', 'admin');
    }

    /**
     * buscar super administradores.
     */
    public function scopeSuperAdmins(Builder $query): Builder
    {
        return $query->where('role', 'super_admin');
    }

    /**
     * buscar tecnicos.
     */
    public function scopeTechnicians(Builder $query): Builder
    {
        return $query->where('role', 'technician');
    }

    /**
     * Buscar personal de logistica.
     */
    public function scopeLogistics(Builder $query): Builder
    {
        return $query->where('role', 'logistics');
    }

    /**
     * relacion con la ubicacion de stock asociada al usuario.
     */
    public function stockLocation()
    {
        return $this->hasOne(StockLocation::class, 'ref_id')
            ->where('location_type', 'user');
    }

    /**
     * ordenes de trabajo asignadas al usuario.
     */
    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class, 'technician_id');
    }

    /**
     * Transferencias iniciadas por el usuario.
     */
    public function initiatedTransfers()
    {
        return $this->hasMany(Transfer::class, 'initiator_user_id');
    }
}




