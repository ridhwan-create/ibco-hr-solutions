<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected static function booted(): void
    {
        static::created(function (User $user) {
            if (! Schema::hasTable('user_roles')) {
                return;
            }

            $user->roleAssignments()->firstOrCreate([
                'role' => $user->legacyRole()->value,
            ]);
        });
    }

    /**
     * @return array<int, UserRole>
     */
    public function resolvedRoles(): array
    {
        if (
            $this->exists
            && (
                $this->relationLoaded('roleAssignments')
                || Schema::hasTable('user_roles')
            )
        ) {
            $this->loadMissing('roleAssignments');

            $assignedRoles = $this->roleAssignments
                ->map(fn (UserRoleAssignment $assignment) => $assignment->role)
                ->filter(fn ($role) => $role instanceof UserRole)
                ->unique(fn (UserRole $role) => $role->value)
                ->sortByDesc(fn (UserRole $role) => $role->priority())
                ->values()
                ->all();

            if ($assignedRoles !== []) {
                return $assignedRoles;
            }
        }

        return [$this->legacyRole()];
    }

    /**
     * @return array<int, string>
     */
    public function roleValues(): array
    {
        return array_map(
            fn (UserRole $role) => $role->value,
            $this->resolvedRoles(),
        );
    }

    public function resolvedRole(): UserRole
    {
        return $this->resolvedRoles()[0];
    }

    public function hasRole(UserRole|string $role): bool
    {
        $role = $role instanceof UserRole ? $role : UserRole::tryFrom($role);

        return $role !== null
            && in_array($role, $this->resolvedRoles(), true);
    }

    public function hasOnlyRole(UserRole $role): bool
    {
        $roles = $this->resolvedRoles();

        return count($roles) === 1 && $roles[0] === $role;
    }

    /**
     * @param  array<int, UserRole|string>  $roles
     */
    public function syncRoles(array $roles): self
    {
        $resolvedRoles = collect($roles)
            ->map(fn ($role) => $role instanceof UserRole
                ? $role
                : UserRole::tryFrom((string) $role))
            ->filter()
            ->unique(fn (UserRole $role) => $role->value)
            ->sortByDesc(fn (UserRole $role) => $role->priority())
            ->values();

        if ($resolvedRoles->isEmpty()) {
            $resolvedRoles = collect([UserRole::Viewer]);
        }

        $values = $resolvedRoles
            ->map(fn (UserRole $role) => $role->value)
            ->all();

        $this->roleAssignments()
            ->whereNotIn('role', $values)
            ->delete();

        foreach ($values as $value) {
            $this->roleAssignments()->firstOrCreate(['role' => $value]);
        }

        $primaryRole = $resolvedRoles->first();

        if ($this->legacyRole() !== $primaryRole) {
            $this->forceFill(['role' => $primaryRole])->saveQuietly();
        }

        $this->unsetRelation('roleAssignments');

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return collect($this->resolvedRoles())
            ->flatMap(fn (UserRole $role) => $role->permissions())
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function employeeLink(): HasOne
    {
        return $this->hasOne(EmployeeUserLink::class);
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
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
            'role' => UserRole::class,
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    private function legacyRole(): UserRole
    {
        return $this->role instanceof UserRole
            ? $this->role
            : UserRole::tryFrom((string) $this->role) ?? UserRole::Viewer;
    }
}
