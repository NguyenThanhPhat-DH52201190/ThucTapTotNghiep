<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'ppic_team'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public const ROLE_USER = 'user';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_WAREHOUSE = 'warehouse';
    public const ROLE_PPIC = 'ppic';
    public const ROLE_IE = 'ie';
    public const ROLE_PROD = 'prod';
    public const ROLE_ACCOUNTANT = 'accountant';
    public const ROLE_DEVELOPMENT = 'development';
    public const ROLE_QA_QC = 'qa_qc';
    public const PPIC_TEAM_TRACK = 'track';
    public const PPIC_TEAM_CREATE = 'create';
    public const PPIC_TEAM_BOTH = 'both';

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

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
}
