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

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'account_status',
    'activation_date',
    'must_change_password',
    'credentials_issued_at',
])]
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

    public function employeeRecord(): HasOne
    {
        return $this->hasOne(EmployeeRecord::class);
    }

    public function activateIfDue(): bool
    {
        if (
            $this->account_status !== 'pending_activation'
            || ! $this->activation_date
            || $this->activation_date->isFuture()
        ) {
            return $this->account_status === 'active';
        }

        $this->forceFill(['account_status' => 'active'])->save();
        $this->employeeRecord?->activate();

        return true;
    }

    public function roleAssignments(): HasMany
    {
        return $this->hasMany(UserRoleAssignment::class);
    }

    public function rosterEntries(): HasMany
    {
        return $this->hasMany(RosterEntry::class);
    }

    public function rosterNotifications(): HasMany
    {
        return $this->hasMany(RosterNotification::class);
    }

    public function performanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'employee_user_id');
    }

    public function supervisedPerformanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'supervisor_user_id');
    }

    public function performanceNotifications(): HasMany
    {
        return $this->hasMany(PerformanceNotification::class);
    }

    public function recruitmentNotifications(): HasMany
    {
        return $this->hasMany(RecruitmentNotification::class);
    }

    public function onboardingCases(): HasMany
    {
        return $this->hasMany(OnboardingCase::class, 'employee_user_id');
    }

    public function trainingRequests(): HasMany
    {
        return $this->hasMany(TrainingRequest::class, 'employee_user_id');
    }

    public function trainingNotifications(): HasMany
    {
        return $this->hasMany(TrainingNotification::class);
    }

    public function hrDocuments(): HasMany
    {
        return $this->hasMany(HrDocument::class, 'employee_user_id');
    }

    public function hrDocumentNotifications(): HasMany
    {
        return $this->hasMany(HrDocumentNotification::class);
    }

    public function disciplineComplaints(): HasMany
    {
        return $this->hasMany(DisciplineCase::class, 'complainant_user_id');
    }

    public function disciplineCasesAsSubject(): HasMany
    {
        return $this->hasMany(DisciplineCase::class, 'subject_user_id');
    }

    public function disciplineNotifications(): HasMany
    {
        return $this->hasMany(DisciplineNotification::class);
    }

    public function separationCases(): HasMany
    {
        return $this->hasMany(SeparationCase::class, 'employee_user_id');
    }

    public function assignedClearanceTasks(): HasMany
    {
        return $this->hasMany(ClearanceTask::class, 'assigned_user_id');
    }

    public function separationNotifications(): HasMany
    {
        return $this->hasMany(SeparationNotification::class);
    }

    public function competencies(): HasMany
    {
        return $this->hasMany(EmployeeCompetency::class, 'employee_user_id');
    }

    public function developmentPlans(): HasMany
    {
        return $this->hasMany(DevelopmentPlan::class, 'employee_user_id');
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
            'activation_date' => 'date',
            'must_change_password' => 'boolean',
            'credentials_issued_at' => 'datetime',
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
