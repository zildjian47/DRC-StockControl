<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_PPIC           = 'PPIC';
    public const ROLE_STAFF_MATERIAL = 'STAFF_MATERIAL';
    public const ROLE_STAFF_FG       = 'STAFF_FG';

    public const ROLES = [
        self::ROLE_PPIC,
        self::ROLE_STAFF_MATERIAL,
        self::ROLE_STAFF_FG,
    ];

    public const STAFF_ROLES = [
        self::ROLE_STAFF_MATERIAL,
        self::ROLE_STAFF_FG,
    ];

    public const STATUS_PENDING  = 'PENDING';
    public const STATUS_ACTIVE   = 'ACTIVE';
    public const STATUS_INACTIVE = 'INACTIVE';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'status',
        'requested_role',
        'approved_by',
        'approved_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'approved_at'       => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function approvedBy()
    {
        return $this->belongsTo(self::class, 'approved_by');
    }

    public function isPpic(): bool
    {
        return $this->role === self::ROLE_PPIC;
    }

    public function isStaffMaterial(): bool
    {
        return $this->role === self::ROLE_STAFF_MATERIAL;
    }

    public function isStaffFg(): bool
    {
        return $this->role === self::ROLE_STAFF_FG;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, self::STAFF_ROLES, true);
    }

    public function isActive(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function homeRouteName(): string
    {
        return $this->isPpic() ? 'dashboard' : 'scanner.portal';
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_PPIC           => 'PPIC',
            self::ROLE_STAFF_MATERIAL => 'Staff Material',
            self::ROLE_STAFF_FG       => 'Staff FG',
            default                   => (string) $this->role,
        };
    }
}