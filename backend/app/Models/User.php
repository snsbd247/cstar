<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'phone', 'password', 'user_type', 'status', 'must_change_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected string $guard_name = 'web';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'status' => UserStatus::class,
            'user_type' => UserType::class,
        ];
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withPivot('is_primary');
    }

    /** Staff profile when this login belongs to a trainer. */
    public function trainer(): HasOne
    {
        return $this->hasOne(Trainer::class);
    }

    /** Staff profile when this login belongs to a therapist. */
    public function therapist(): HasOne
    {
        return $this->hasOne(Therapist::class);
    }

    /** Guardian record when this is a parent-portal login. */
    public function guardian(): HasOne
    {
        return $this->hasOne(Guardian::class);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(Role::SuperAdmin->value);
    }

    /** Highest-privilege role the user holds; decides which app they land in. */
    public function primaryRole(): ?Role
    {
        $names = $this->getRoleNames();

        foreach (Role::byPriority() as $role) {
            if ($names->contains($role->value)) {
                return $role;
            }
        }

        return null;
    }

    public function homePath(): string
    {
        return $this->primaryRole()?->homePath() ?? '/login';
    }

    /**
     * Branch ids this user may see. null means "all branches" (Super Admin).
     *
     * @return list<int>|null
     */
    public function accessibleBranchIds(): ?array
    {
        if ($this->isSuperAdmin()) {
            return null;
        }

        return $this->branches->pluck('id')->all();
    }

    public function canAccessBranch(int $branchId): bool
    {
        $ids = $this->accessibleBranchIds();

        return $ids === null || in_array($branchId, $ids, true);
    }

    /** Fields never written to the audit log. */
    public function auditExclude(): array
    {
        return ['password', 'remember_token', 'last_login_at', 'last_login_ip'];
    }
}
