<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\ImportEmployeeUsersRequest;
use App\Models\EmployeeUserLink;
use App\Models\OfficeLocation;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeUserImportController extends Controller
{
    public function create(Request $request): Response
    {
        $candidates = $this->importCandidates();

        return Inertia::render('users/import-employees', [
            'employees' => $candidates,
            'offices' => OfficeLocation::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'radius_meters'])
                ->map(fn (OfficeLocation $office) => [
                    'id' => $office->getKey(),
                    'name' => $office->name,
                    'radius_meters' => $office->radius_meters,
                ]),
            'statistics' => [
                'active_employees' => DB::connection('ibco')
                    ->table('maklumatpekerja')
                    ->where('rcd_enable', 1)
                    ->count(),
                'already_registered' => EmployeeUserLink::query()
                    ->where('is_active', true)
                    ->count(),
                'ready_to_import' => $candidates
                    ->where('can_import', true)
                    ->count(),
                'requires_attention' => $candidates
                    ->where('can_import', false)
                    ->count(),
            ],
            'importResult' => $request->session()->get('employee_user_import_result'),
        ]);
    }

    public function store(
        ImportEmployeeUsersRequest $request,
    ): RedirectResponse {
        $validated = $request->validated();
        $selectedIds = collect($validated['employee_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $candidateMap = $this->importCandidates($selectedIds)
            ->keyBy('id');
        $invalidSelections = $selectedIds
            ->filter(function (int $employeeId) use ($candidateMap) {
                $candidate = $candidateMap->get($employeeId);

                return ! $candidate || ! $candidate['can_import'];
            });

        if ($invalidSelections->isNotEmpty()) {
            throw ValidationException::withMessages([
                'employee_ids' => 'Sebahagian pekerja tidak lagi layak diimport. Muat semula halaman dan semak status pekerja.',
            ]);
        }

        $office = OfficeLocation::query()
            ->whereKey($validated['office_location_id'])
            ->where('is_active', true)
            ->first();

        if (! $office) {
            throw ValidationException::withMessages([
                'office_location_id' => 'Lokasi pejabat yang dipilih tidak lagi aktif.',
            ]);
        }

        $result = DB::transaction(function () use (
            $request,
            $candidateMap,
            $selectedIds,
            $office,
        ) {
            $created = [];
            $linked = [];

            foreach ($selectedIds as $employeeId) {
                $candidate = $candidateMap->get($employeeId);
                $email = $candidate['email'];
                $user = User::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->lockForUpdate()
                    ->first();
                $temporaryPassword = null;
                $wasCreated = false;

                if (! $user) {
                    $temporaryPassword = Str::password(16);
                    $user = User::query()->create([
                        'name' => $candidate['name'],
                        'email' => $email,
                        'password' => $temporaryPassword,
                        'role' => UserRole::Employee,
                    ]);
                    $user->forceFill(['email_verified_at' => now()])->save();
                    $user->syncRoles([UserRole::Employee]);
                    $wasCreated = true;
                } else {
                    $activeLink = EmployeeUserLink::query()
                        ->where('user_id', $user->getKey())
                        ->where('is_active', true)
                        ->lockForUpdate()
                        ->first();

                    if (
                        $activeLink
                        && $activeLink->employee_id !== $employeeId
                    ) {
                        throw ValidationException::withMessages([
                            'employee_ids' => "Akaun {$email} baru sahaja dipautkan kepada pekerja lain.",
                        ]);
                    }

                    $beforeRoles = $user->roleValues();
                    $user->syncRoles([
                        ...$user->resolvedRoles(),
                        UserRole::Employee,
                    ]);

                    if ($beforeRoles !== $user->roleValues()) {
                        AuditLogger::record(
                            $request,
                            'user.updated',
                            'users',
                            $user->getKey(),
                            oldValues: ['roles' => $beforeRoles],
                            newValues: ['roles' => $user->roleValues()],
                        );
                    }
                }

                $employeeAlreadyLinked = EmployeeUserLink::query()
                    ->where('employee_id', $employeeId)
                    ->where('is_active', true)
                    ->where('user_id', '!=', $user->getKey())
                    ->lockForUpdate()
                    ->exists();

                if ($employeeAlreadyLinked) {
                    throw ValidationException::withMessages([
                        'employee_ids' => "{$candidate['name']} baru sahaja didaftarkan oleh pengguna lain.",
                    ]);
                }

                $link = EmployeeUserLink::query()->firstOrNew([
                    'user_id' => $user->getKey(),
                ]);
                $beforeLink = $link->exists
                    ? $this->linkAuditValues($link)
                    : [];

                $link->fill([
                    'employee_id' => $employeeId,
                    'office_location_id' => $office->getKey(),
                    'is_active' => true,
                    'updated_by' => $request->user()->getAuthIdentifier(),
                ]);

                if (! $link->exists) {
                    $link->created_by = $request->user()->getAuthIdentifier();
                }

                $link->save();

                if ($wasCreated) {
                    AuditLogger::record(
                        $request,
                        'user.created',
                        'users',
                        $user->getKey(),
                        newValues: [
                            'name' => $user->name,
                            'email' => $user->email,
                            'roles' => $user->roleValues(),
                            'source' => 'employee_import',
                        ],
                    );
                }

                AuditLogger::record(
                    $request,
                    $beforeLink === []
                        ? 'employee_link.created'
                        : 'employee_link.updated',
                    'employee_user_links',
                    $link->getKey(),
                    oldValues: $beforeLink,
                    newValues: $this->linkAuditValues($link),
                );

                if ($wasCreated) {
                    $created[] = [
                        'employee_id' => $candidate['employee_id'],
                        'name' => $user->name,
                        'email' => $user->email,
                        'temporary_password' => $temporaryPassword,
                    ];
                } else {
                    $linked[] = [
                        'employee_id' => $candidate['employee_id'],
                        'name' => $user->name,
                        'email' => $user->email,
                    ];
                }
            }

            return [
                'created_count' => count($created),
                'linked_count' => count($linked),
                'office_name' => $office->name,
                'credentials' => $created,
                'linked_accounts' => $linked,
            ];
        });

        return redirect()
            ->route('users.import.create')
            ->with('employee_user_import_result', $result)
            ->with('toast', [
                'type' => 'success',
                'message' => sprintf(
                    '%d akaun baharu dicipta dan %d akaun sedia ada dipautkan.',
                    $result['created_count'],
                    $result['linked_count'],
                ),
            ]);
    }

    /**
     * @param  Collection<int, int>|null  $onlyIds
     * @return Collection<int, array<string, mixed>>
     */
    private function importCandidates(?Collection $onlyIds = null): Collection
    {
        $linkedEmployeeIds = EmployeeUserLink::query()
            ->where('is_active', true)
            ->pluck('employee_id');
        $employees = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('rcd_enable', 1)
            ->when(
                $linkedEmployeeIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn(
                    'id',
                    $linkedEmployeeIds,
                ),
            )
            ->orderBy('nama')
            ->get(['id', 'employeeID', 'nama', 'email']);
        $normalizedEmails = $employees
            ->map(fn ($employee) => $this->normalizeEmail($employee->email))
            ->filter();
        $duplicateEmails = $normalizedEmails
            ->countBy()
            ->filter(fn (int $count) => $count > 1)
            ->keys();
        $existingUsers = User::query()
            ->with([
                'roleAssignments',
                'employeeLink' => fn ($query) => $query
                    ->where('is_active', true),
            ])
            ->whereIn(
                DB::raw('LOWER(email)'),
                $normalizedEmails->unique()->values(),
            )
            ->get()
            ->keyBy(fn (User $user) => $this->normalizeEmail($user->email));

        $candidates = $employees
            ->map(function ($employee) use (
                $duplicateEmails,
                $existingUsers,
            ) {
                $email = $this->normalizeEmail($employee->email);
                $emailValid = $email !== null
                    && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
                $duplicateEmail = $email !== null
                    && $duplicateEmails->contains($email);
                $existingUser = $email
                    ? $existingUsers->get($email)
                    : null;
                $accountConflict = $existingUser?->employeeLink !== null
                    && $existingUser->employeeLink->employee_id
                        !== (int) $employee->id;
                $status = 'new_account';

                if (! $emailValid) {
                    $status = 'invalid_email';
                } elseif ($duplicateEmail) {
                    $status = 'duplicate_email';
                } elseif ($accountConflict) {
                    $status = 'account_linked_elsewhere';
                } elseif ($existingUser) {
                    $status = 'existing_account';
                }

                return [
                    'id' => (int) $employee->id,
                    'employee_id' => $employee->employeeID,
                    'name' => trim((string) $employee->nama)
                        ?: ($employee->employeeID ?: "Pekerja #{$employee->id}"),
                    'email' => $email,
                    'status' => $status,
                    'can_import' => $emailValid
                        && ! $duplicateEmail
                        && ! $accountConflict,
                    'existing_user' => $existingUser
                        ? [
                            'id' => $existingUser->getKey(),
                            'name' => $existingUser->name,
                            'roles' => $existingUser->roleValues(),
                        ]
                        : null,
                ];
            })
            ->values();

        if (! $onlyIds) {
            return $candidates;
        }

        return $candidates
            ->whereIn('id', $onlyIds)
            ->values();
    }

    private function normalizeEmail(?string $email): ?string
    {
        $normalized = Str::lower(trim((string) $email));

        return $normalized !== '' ? $normalized : null;
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
