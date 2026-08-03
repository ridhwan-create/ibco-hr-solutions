<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreSystemUserRequest;
use App\Http\Requests\UpdateSystemUserRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\EmployeeUserLink;
use App\Models\OfficeLocation;
use App\Models\User;
use App\Models\UserRoleAssignment;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $users = User::query()
            ->with([
                'roleAssignments',
                'employeeLink' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with([
                        'officeLocation:id,name',
                        'employeeRecord:id,directory_id,employee_number,name',
                    ]),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();
        $employeeIds = $users->getCollection()
            ->pluck('employeeLink')
            ->filter(fn (?EmployeeUserLink $link) => $link?->employee_source !== 'local')
            ->pluck('employee_id')
            ->filter()
            ->unique()
            ->values();
        $employees = $employeeIds->isEmpty()
            ? collect()
            : DB::connection('ibco')
                ->table('maklumatpekerja')
                ->whereIn('id', $employeeIds)
                ->get(['id', 'employeeID', 'nama'])
                ->keyBy(fn ($employee) => (string) $employee->id);

        $users->through(function (User $user) use ($employees, $request) {
            $link = $user->employeeLink;
            $employee = $link?->employee_source === 'local'
                ? $link->employeeRecord
                : ($link ? ($employees[(string) $link->employee_id] ?? null) : null);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->resolvedRole()->value,
                'roles' => $user->roleValues(),
                'role_labels' => collect($user->resolvedRoles())
                    ->map(fn (UserRole $role) => $role->label())
                    ->all(),
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'is_current_user' => $user->is($request->user()),
                'employee' => $employee
                    ? [
                        'id' => (int) ($employee->directory_id ?? $employee->id),
                        'employee_id' => $employee->employee_number ?? $employee->employeeID,
                        'name' => $employee->name ?? $employee->nama,
                        'office' => $link?->officeLocation?->name,
                    ]
                    : null,
            ];
        });

        $roleCounts = collect(UserRole::cases())
            ->mapWithKeys(fn (UserRole $role) => [
                $role->value => UserRoleAssignment::query()
                    ->where('role', $role->value)
                    ->count(),
            ]);

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
            ],
            'roleCounts' => $roleCounts,
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('users/create', [
            'options' => $this->formOptions(),
            'defaultRoles' => [],
        ]);
    }

    public function store(StoreSystemUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $roles = $this->rolesFromValidated($validated);
        $this->guardRoleCombination($roles);
        $this->validateEmployeeAssignment($validated);

        $user = DB::transaction(function () use ($request, $validated, $roles) {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => $this->primaryRole($roles),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles($roles);

            $this->syncEmployeeLink($request, $user, $validated);

            AuditLogger::record(
                $request,
                'user.created',
                'users',
                $user->getKey(),
                newValues: $this->userAuditValues($user),
            );

            return $user;
        });

        return redirect()
            ->route('users.index')
            ->with('toast', [
                'type' => 'success',
                'message' => "Pengguna {$user->name} berjaya didaftarkan.",
            ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $link = EmployeeUserLink::query()
            ->where('user_id', $user->getKey())
            ->where('is_active', true)
            ->first();

        return Inertia::render('users/edit', [
            'managedUser' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->resolvedRole()->value,
                'roles' => $user->roleValues(),
                'is_current_user' => $user->is($request->user()),
                'employee_id' => $link?->employee_id,
                'office_location_id' => $link?->office_location_id,
            ],
            'options' => $this->formOptions($user),
        ]);
    }

    public function update(
        UpdateSystemUserRequest $request,
        User $user,
    ): RedirectResponse {
        $validated = $request->validated();
        $roles = $this->rolesFromValidated($validated);

        $this->guardRoleCombination($roles);
        $this->guardRoleChange($request, $user, $roles);
        $this->validateEmployeeAssignment($validated, $user);

        DB::transaction(function () use ($request, $user, $validated, $roles) {
            $before = $this->userAuditValues($user);
            $attributes = [
                'name' => $validated['name'],
                'email' => $validated['email'],
            ];

            if (! empty($validated['password'])) {
                $attributes['password'] = $validated['password'];
            }

            $user->fill($attributes);

            if ($user->isDirty('email')) {
                $user->email_verified_at = now();
            }

            $user->save();
            $user->syncRoles($roles);
            $this->syncEmployeeLink($request, $user, $validated);

            AuditLogger::record(
                $request,
                'user.updated',
                'users',
                $user->getKey(),
                oldValues: $before,
                newValues: $this->userAuditValues($user),
            );
        });

        return redirect()
            ->route('users.index')
            ->with('toast', [
                'type' => 'success',
                'message' => "Maklumat pengguna {$user->name} berjaya dikemas kini.",
            ]);
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $roles = $this->rolesFromValidated($validated);

        $this->guardRoleCombination($roles);
        $this->guardRoleChange($request, $user, $roles);

        if (
            in_array(UserRole::Employee, $roles, true)
            && ! EmployeeUserLink::query()
                ->where('user_id', $user->getKey())
                ->where('is_active', true)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'roles' => 'Gunakan butang Edit untuk memilih pekerja dan lokasi sebelum menambah role Employee.',
            ]);
        }

        DB::transaction(function () use ($request, $user, $roles) {
            $before = $this->userAuditValues($user);
            $user->syncRoles($roles);

            if (! in_array(UserRole::Employee, $roles, true)) {
                $this->syncEmployeeLink($request, $user, [
                    'roles' => collect($roles)
                        ->map(fn (UserRole $role) => $role->value)
                        ->all(),
                ]);
            }

            AuditLogger::record(
                $request,
                'user.updated',
                'users',
                $user->getKey(),
                oldValues: $before,
                newValues: $this->userAuditValues($user),
            );
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Role {$user->name} berjaya dikemas kini.",
        ]);

        return back();
    }

    private function guardRoleChange(
        Request $request,
        User $user,
        array $roles,
    ): void {
        $keepsSuperAdmin = in_array(UserRole::SuperAdmin, $roles, true);

        if (
            $user->is($request->user())
            && $user->hasRole(UserRole::SuperAdmin)
            && ! $keepsSuperAdmin
        ) {
            throw ValidationException::withMessages([
                'roles' => 'Role Super Admin tidak boleh dibuang daripada akaun anda sendiri.',
            ]);
        }

        if (
            $user->hasRole(UserRole::SuperAdmin)
            && ! $keepsSuperAdmin
            && UserRoleAssignment::query()
                ->where('role', UserRole::SuperAdmin->value)
                ->count() <= 1
        ) {
            throw ValidationException::withMessages([
                'roles' => 'Sekurang-kurangnya satu akaun Super Admin mesti dikekalkan.',
            ]);
        }
    }

    /**
     * @param  array<int, UserRole>  $roles
     */
    private function guardRoleCombination(array $roles): void
    {
        if (! in_array(UserRole::HrManager, $roles, true)) {
            return;
        }

        $conflicts = collect($roles)->filter(fn (UserRole $role) => in_array(
            $role,
            [
                UserRole::SuperAdmin,
                UserRole::HrAdmin,
                UserRole::Supervisor,
            ],
            true,
        ));

        if ($conflicts->isNotEmpty()) {
            throw ValidationException::withMessages([
                'roles' => 'Role Pengurus HR tidak boleh digabungkan dengan Super Admin, HR Admin atau Penyelia. Gunakan akaun berasingan untuk mengekalkan pengasingan tugas.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validateEmployeeAssignment(
        array $validated,
        ?User $user = null,
    ): void {
        if (! in_array(
            UserRole::Employee,
            $this->rolesFromValidated($validated),
            true,
        )) {
            return;
        }

        $employee = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('id', $validated['employee_id'])
            ->where('rcd_enable', 1)
            ->first(['id']);

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee_id' => 'Rekod pekerja aktif tidak ditemui dalam db_spp.',
            ]);
        }

        $duplicate = EmployeeUserLink::query()
            ->where('employee_id', $validated['employee_id'])
            ->where('is_active', true)
            ->when(
                $user,
                fn ($query) => $query->where('user_id', '!=', $user->getKey()),
            )
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'employee_id' => 'Pekerja ini sudah didaftarkan sebagai pengguna sistem.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncEmployeeLink(
        Request $request,
        User $user,
        array $validated,
    ): void {
        if (! in_array(
            UserRole::Employee,
            $this->rolesFromValidated($validated),
            true,
        )) {
            $link = EmployeeUserLink::query()
                ->where('user_id', $user->getKey())
                ->where('is_active', true)
                ->first();

            if (! $link) {
                return;
            }

            $before = $this->linkAuditValues($link);
            $link->update([
                'is_active' => false,
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);

            AuditLogger::record(
                $request,
                'employee_link.deactivated',
                'employee_user_links',
                $link->getKey(),
                oldValues: $before,
                newValues: $this->linkAuditValues($link),
            );

            return;
        }

        $link = EmployeeUserLink::query()->firstOrNew([
            'user_id' => $user->getKey(),
        ]);
        $before = $link->exists ? $this->linkAuditValues($link) : [];

        $link->fill([
            'employee_id' => $validated['employee_id'],
            'office_location_id' => $validated['office_location_id'],
            'is_active' => true,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        if (! $link->exists) {
            $link->created_by = $request->user()->getAuthIdentifier();
        }

        if ($link->exists && ! $link->isDirty()) {
            return;
        }

        $link->save();

        AuditLogger::record(
            $request,
            $before === [] ? 'employee_link.created' : 'employee_link.updated',
            'employee_user_links',
            $link->getKey(),
            oldValues: $before,
            newValues: $this->linkAuditValues($link),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?User $user = null): array
    {
        $unavailableEmployeeIds = EmployeeUserLink::query()
            ->where('is_active', true)
            ->when(
                $user,
                fn ($query) => $query->where('user_id', '!=', $user->getKey()),
            )
            ->pluck('employee_id');

        $employees = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('rcd_enable', 1)
            ->when(
                $unavailableEmployeeIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('id', $unavailableEmployeeIds),
            )
            ->orderBy('nama')
            ->get(['id', 'employeeID', 'nama', 'email'])
            ->map(fn ($employee) => [
                'id' => (int) $employee->id,
                'employee_id' => $employee->employeeID,
                'name' => $employee->nama,
                'email' => $employee->email,
            ]);

        return [
            'roles' => $this->roleOptions(),
            'employees' => $employees,
            'offices' => OfficeLocation::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'radius_meters'])
                ->map(fn (OfficeLocation $office) => [
                    'id' => $office->getKey(),
                    'name' => $office->name,
                    'radius_meters' => $office->radius_meters,
                ]),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function roleOptions(): array
    {
        return collect(UserRole::cases())
            ->map(fn (UserRole $role) => [
                'value' => $role->value,
                'label' => $role->label(),
                'description' => $role->description(),
                'permissions' => $role->permissions(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function userAuditValues(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'primary_role' => $user->resolvedRole()->value,
            'roles' => $user->roleValues(),
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, UserRole>
     */
    private function rolesFromValidated(array $validated): array
    {
        return collect($validated['roles'] ?? [])
            ->map(fn ($role) => UserRole::from((string) $role))
            ->unique(fn (UserRole $role) => $role->value)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, UserRole>  $roles
     */
    private function primaryRole(array $roles): UserRole
    {
        return collect($roles)
            ->sortByDesc(fn (UserRole $role) => $role->priority())
            ->first() ?? UserRole::Viewer;
    }

    /**
     * @return array<string, mixed>
     */
    private function linkAuditValues(EmployeeUserLink $link): array
    {
        return [
            'user_id' => $link->user_id,
            'employee_id' => $link->employee_id,
            'office_location_id' => $link->office_location_id,
            'is_active' => $link->is_active,
        ];
    }
}
