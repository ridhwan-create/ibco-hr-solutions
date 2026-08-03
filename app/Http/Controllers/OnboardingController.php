<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\RegisterOnboardingEmployeeRequest;
use App\Models\EmployeeRecord;
use App\Models\EmployeeUserLink;
use App\Models\OfficeLocation;
use App\Models\OnboardingCase;
use App\Models\OnboardingTask;
use App\Models\RecruitmentNotification;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OnboardingController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $status = $request->string('status')->trim()->toString();
        $canManage = $request->user()->hasPermission('onboarding.manage');
        $canApprove = $request->user()->hasPermission('onboarding.approve');
        $canOperate = $canManage || $canApprove;
        $casesQuery = OnboardingCase::query()
            ->with([
                'candidate.requisition:id,code,title,department_id,position_name,hiring_manager_user_id',
                'offer:id,recruitment_candidate_id,position_name,department_id,employment_type,salary,start_date,probation_months,status',
                'template:id,name',
                'employeeRecord:id,directory_id,employee_number,user_id,official_email,status,start_date,office_location_id',
                'employeeRecord.officeLocation:id,name',
                'employeeUser:id,name,email',
                'manager:id,name,email',
                'buddy:id,name,email',
                'tasks.assignee:id,name,email',
            ]);
        $this->scopeCasesForUser($casesQuery, $request->user());
        $casesQuery
            ->when($search !== '', fn (Builder $query) => $query->whereHas(
                'candidate',
                fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('candidate_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"),
            ))
            ->when(
                in_array($status, ['pending', 'active', 'completed', 'cancelled'], true),
                fn (Builder $query) => $query->where('status', $status),
            );

        $visibleCaseIds = (clone $casesQuery)
            ->reorder()
            ->pluck('onboarding_cases.id');

        return Inertia::render('Onboarding/Index', [
            'cases' => $casesQuery
                ->latest('start_date')
                ->paginate(12)
                ->withQueryString()
                ->through(fn (OnboardingCase $case) => $this->serializeCase($case)),
            'statistics' => [
                'pending' => OnboardingCase::query()
                    ->whereIn('id', $visibleCaseIds)
                    ->where('status', 'pending')
                    ->count(),
                'active' => OnboardingCase::query()
                    ->whereIn('id', $visibleCaseIds)
                    ->where('status', 'active')
                    ->count(),
                'completed' => OnboardingCase::query()
                    ->whereIn('id', $visibleCaseIds)
                    ->where('status', 'completed')
                    ->count(),
                'overdue_tasks' => OnboardingTask::query()
                    ->whereIn('onboarding_case_id', $visibleCaseIds)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->whereDate('due_date', '<', today())
                    ->count(),
            ],
            'users' => $canOperate
                ? User::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'email'])
                    ->map(fn (User $user) => [
                        'id' => $user->getKey(),
                        'name' => $user->name,
                        'email' => $user->email,
                    ])
                : [],
            'employeeUsers' => $canManage
                ? User::query()
                    ->with('roleAssignments')
                    ->orderBy('name')
                    ->get()
                    ->filter(fn (User $user) => $user->hasRole(UserRole::Employee))
                    ->values()
                    ->map(fn (User $user) => [
                        'id' => $user->getKey(),
                        'name' => $user->name,
                        'email' => $user->email,
                    ])
                : [],
            'officeLocations' => $canOperate
                ? OfficeLocation::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name', 'address'])
                : [],
            'legacyEmployees' => $canManage
                ? DB::connection('ibco')
                    ->table('maklumatpekerja')
                    ->where('rcd_enable', 1)
                    ->orderBy('nama')
                    ->limit(1000)
                    ->get(['id', 'employeeID as employee_number', 'nama as name'])
                : [],
            'filters' => ['search' => $search, 'status' => $status],
            'newEmployeeCredentials' => $request->session()->get('new_employee_credentials'),
            'permissions' => [
                'can_manage' => $canManage,
                'can_approve' => $canApprove,
            ],
        ]);
    }

    public function registerEmployee(
        RegisterOnboardingEmployeeRequest $request,
        OnboardingCase $onboardingCase,
    ): RedirectResponse {
        $validated = $request->validated();
        $onboardingCase->loadMissing(['candidate.requisition', 'offer', 'employeeRecord']);

        if ((int) $onboardingCase->created_by === (int) $request->user()->getAuthIdentifier()) {
            throw ValidationException::withMessages([
                'registration' => 'Pencipta kes onboarding tidak boleh mengesahkan pendaftaran pekerja yang sama.',
            ]);
        }

        $candidate = $onboardingCase->candidate;
        $offer = $onboardingCase->offer;

        if (! $candidate || ! $offer || $offer->status !== 'accepted') {
            throw ValidationException::withMessages([
                'registration' => 'Pendaftaran hanya boleh dibuat selepas tawaran sah diterima.',
            ]);
        }

        if (in_array($onboardingCase->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'registration' => 'Kes onboarding yang selesai atau dibatalkan tidak boleh mendaftarkan pekerja.',
            ]);
        }

        if (
            $onboardingCase->employeeRecord
            || $onboardingCase->employee_user_id
            || $onboardingCase->legacy_employee_id
        ) {
            throw ValidationException::withMessages([
                'registration' => 'Calon ini telah didaftarkan atau dipautkan kepada pekerja.',
            ]);
        }

        $legacyDuplicate = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('rcd_enable', 1)
            ->where(function ($query) use ($validated) {
                $query->whereRaw(
                    "UPPER(REPLACE(REPLACE(nric, '-', ''), ' ', '')) = ?",
                    [$validated['identity_number']],
                )
                    ->orWhere('employeeID', $validated['employee_number'])
                    ->orWhere('email', $validated['official_email']);
            })
            ->first(['id', 'employeeID', 'nama', 'nric', 'email']);

        if ($legacyDuplicate) {
            throw ValidationException::withMessages([
                'registration' => sprintf(
                    'Rekod sepadan ditemui dalam db_spp (%s). Gunakan “Paut Pekerja Sedia Ada” untuk mengelakkan rekod berganda.',
                    $legacyDuplicate->employeeID ?: 'ID '.$legacyDuplicate->id,
                ),
            ]);
        }

        $temporaryPassword = Str::password(16);
        $activatesNow = ! $offer->start_date->isFuture();
        $result = DB::transaction(function () use (
            $request,
            $onboardingCase,
            $validated,
            $temporaryPassword,
            $activatesNow,
        ) {
            $case = OnboardingCase::query()
                ->with(['candidate.requisition', 'offer', 'employeeRecord'])
                ->lockForUpdate()
                ->findOrFail($onboardingCase->getKey());

            if (
                $case->employeeRecord
                || $case->employee_user_id
                || $case->legacy_employee_id
                || $case->offer?->status !== 'accepted'
            ) {
                throw ValidationException::withMessages([
                    'registration' => 'Status calon telah berubah. Muat semula halaman sebelum mencuba semula.',
                ]);
            }

            $candidate = $case->candidate;
            $offer = $case->offer;
            $managerId = $validated['manager_user_id']
                ?? $case->manager_user_id
                ?? $candidate?->requisition?->hiring_manager_user_id;
            $accountStatus = $activatesNow ? 'active' : 'pending_activation';

            $user = User::query()->create([
                'name' => $candidate->name,
                'email' => $validated['official_email'],
                'password' => $temporaryPassword,
                'role' => UserRole::Employee,
                'account_status' => $accountStatus,
                'activation_date' => $offer->start_date,
                'must_change_password' => true,
                'credentials_issued_at' => now(),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([UserRole::Employee]);

            $employee = EmployeeRecord::query()->create([
                'recruitment_candidate_id' => $candidate->getKey(),
                'recruitment_offer_id' => $offer->getKey(),
                'user_id' => $user->getKey(),
                'employee_number' => $validated['employee_number'],
                'name' => $candidate->name,
                'identity_number' => $validated['identity_number'],
                'personal_email' => strtolower($candidate->email),
                'official_email' => $validated['official_email'],
                'phone' => $candidate->phone,
                'department_id' => $offer->department_id,
                'position_name' => $offer->position_name,
                'employment_type' => $offer->employment_type,
                'salary' => $offer->salary,
                'probation_months' => $offer->probation_months,
                'start_date' => $offer->start_date,
                'manager_user_id' => $managerId,
                'office_location_id' => $validated['office_location_id'],
                'status' => $accountStatus,
                'confirmed_by' => $request->user()->getAuthIdentifier(),
                'confirmed_at' => now(),
                'activated_at' => $activatesNow ? now() : null,
            ]);
            $employee->forceFill([
                'directory_id' => EmployeeRecord::DIRECTORY_ID_OFFSET + $employee->getKey(),
            ])->save();

            EmployeeUserLink::query()->create([
                'user_id' => $user->getKey(),
                'employee_id' => $employee->directory_id,
                'employee_source' => 'local',
                'employee_record_id' => $employee->getKey(),
                'office_location_id' => $validated['office_location_id'],
                'is_active' => true,
                'created_by' => $request->user()->getAuthIdentifier(),
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);

            $case->update([
                'employee_record_id' => $employee->getKey(),
                'employee_user_id' => $user->getKey(),
                'manager_user_id' => $managerId,
            ]);
            $case->tasks()
                ->where('assignee_role', 'employee')
                ->whereNull('completed_at')
                ->update(['assignee_user_id' => $user->getKey()]);
            $case->tasks()
                ->where('assignee_role', 'supervisor')
                ->whereNull('completed_at')
                ->update(['assignee_user_id' => $managerId]);
            $candidate->update(['nric' => $validated['identity_number']]);

            RecruitmentNotification::query()->create([
                'user_id' => $user->getKey(),
                'recruitment_candidate_id' => $candidate->getKey(),
                'recruitment_requisition_id' => $candidate->recruitment_requisition_id,
                'type' => 'employee_registered',
                'title' => 'Akaun pekerja disediakan',
                'message' => sprintf(
                    'Akaun pekerja %s telah disediakan dan aktif mulai %s.',
                    $employee->employee_number,
                    $employee->start_date->format('d/m/Y'),
                ),
            ]);
            if ($managerId) {
                RecruitmentNotification::query()->create([
                    'user_id' => $managerId,
                    'recruitment_candidate_id' => $candidate->getKey(),
                    'recruitment_requisition_id' => $candidate->recruitment_requisition_id,
                    'type' => 'new_employee_registered',
                    'title' => 'Pekerja baharu didaftarkan',
                    'message' => sprintf(
                        '%s (%s) telah didaftarkan dan dijadualkan mula pada %s.',
                        $employee->name,
                        $employee->employee_number,
                        $employee->start_date->format('d/m/Y'),
                    ),
                ]);
            }

            AuditLogger::record(
                $request,
                'employee.registered_from_recruitment',
                'employee_records',
                $employee->getKey(),
                newValues: [
                    'candidate_id' => $candidate->getKey(),
                    'offer_id' => $offer->getKey(),
                    'employee_number' => $employee->employee_number,
                    'directory_id' => $employee->directory_id,
                    'user_id' => $user->getKey(),
                    'office_location_id' => $employee->office_location_id,
                    'activation_date' => $employee->start_date->toDateString(),
                    'status' => $employee->status,
                ],
            );
            AuditLogger::record(
                $request,
                'user.created_from_recruitment',
                'users',
                $user->getKey(),
                newValues: [
                    'employee_record_id' => $employee->getKey(),
                    'role' => UserRole::Employee->value,
                    'account_status' => $accountStatus,
                    'activation_date' => $offer->start_date->toDateString(),
                    'must_change_password' => true,
                ],
            );

            return compact('employee', 'user');
        });

        return redirect()
            ->route('onboarding.index')
            ->with('new_employee_credentials', [
                'employee_number' => $result['employee']->employee_number,
                'name' => $result['employee']->name,
                'email' => $result['user']->email,
                'temporary_password' => $temporaryPassword,
                'activation_date' => $result['employee']->start_date->toDateString(),
                'account_status' => $result['user']->account_status,
            ])
            ->with('toast', [
                'type' => 'success',
                'message' => 'Calon berjaya didaftarkan sebagai pekerja tanpa kemasukan semula maklumat.',
            ]);
    }

    public function updateCase(
        Request $request,
        OnboardingCase $onboardingCase,
    ): RedirectResponse {
        $validated = $request->validate([
            'manager_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'buddy_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $old = $onboardingCase->only(array_keys($validated));
        $onboardingCase->update($validated);
        $onboardingCase->tasks()
            ->where('assignee_role', 'supervisor')
            ->whereNull('completed_at')
            ->update(['assignee_user_id' => $validated['manager_user_id'] ?? null]);
        $onboardingCase->employeeRecord?->update([
            'manager_user_id' => $validated['manager_user_id'] ?? null,
        ]);
        AuditLogger::record(
            $request,
            'onboarding.case_updated',
            'onboarding_cases',
            $onboardingCase->getKey(),
            oldValues: $old,
            newValues: $onboardingCase->only(array_keys($validated)),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Maklumat pengurus, buddy dan catatan onboarding dikemas kini.',
        ]);
    }

    public function linkEmployee(
        Request $request,
        OnboardingCase $onboardingCase,
    ): RedirectResponse {
        if ($onboardingCase->employee_record_id) {
            throw ValidationException::withMessages([
                'legacy_employee_id' => 'Calon ini telah didaftarkan sebagai pekerja IBCO dan tidak boleh dipautkan semula.',
            ]);
        }

        $validated = $request->validate([
            'legacy_employee_id' => ['required', 'integer', 'min:1'],
            'employee_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'office_location_id' => [
                'required',
                'integer',
                Rule::exists('office_locations', 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],
        ]);
        $employee = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('id', $validated['legacy_employee_id'])
            ->where('rcd_enable', 1)
            ->first(['id', 'nama', 'employeeID']);

        if (! $employee) {
            throw ValidationException::withMessages([
                'legacy_employee_id' => 'Rekod pekerja aktif tidak ditemui dalam db_spp.',
            ]);
        }

        $employeeConflict = EmployeeUserLink::query()
            ->where('employee_id', $validated['legacy_employee_id'])
            ->where('user_id', '<>', $validated['employee_user_id'])
            ->where('is_active', true)
            ->exists();

        if ($employeeConflict) {
            throw ValidationException::withMessages([
                'legacy_employee_id' => 'Rekod pekerja ini telah dipautkan kepada pengguna aktif lain.',
            ]);
        }

        DB::transaction(function () use ($request, $onboardingCase, $validated) {
            EmployeeUserLink::query()->updateOrCreate(
                ['user_id' => $validated['employee_user_id']],
                [
                    'employee_id' => $validated['legacy_employee_id'],
                    'employee_source' => 'legacy',
                    'employee_record_id' => null,
                    'office_location_id' => $validated['office_location_id'],
                    'is_active' => true,
                    'created_by' => $request->user()->getAuthIdentifier(),
                    'updated_by' => $request->user()->getAuthIdentifier(),
                ],
            );
            $onboardingCase->update([
                'legacy_employee_id' => $validated['legacy_employee_id'],
                'employee_user_id' => $validated['employee_user_id'],
            ]);
            $onboardingCase->tasks()
                ->where('assignee_role', 'employee')
                ->whereNull('completed_at')
                ->update(['assignee_user_id' => $validated['employee_user_id']]);
        });
        AuditLogger::record(
            $request,
            'onboarding.employee_linked',
            'onboarding_cases',
            $onboardingCase->getKey(),
            newValues: [
                'legacy_employee_id' => $validated['legacy_employee_id'],
                'employee_user_id' => $validated['employee_user_id'],
                'office_location_id' => $validated['office_location_id'],
                'employee_name' => $employee->nama,
                'employee_number' => $employee->employeeID,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Calon telah dipautkan kepada rekod pekerja dan akaun Employee.',
        ]);
    }

    public function unlinkEmployee(
        Request $request,
        OnboardingCase $onboardingCase,
    ): RedirectResponse {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'deactivate_employee_link' => ['sometimes', 'boolean'],
            'confirmed' => ['accepted'],
        ], [
            'reason.required' => 'Sila nyatakan sebab pautan pekerja perlu dibatalkan.',
            'reason.min' => 'Sebab pembatalan mestilah sekurang-kurangnya 5 aksara.',
            'confirmed.accepted' => 'Sila sahkan bahawa pautan pekerja yang dipilih adalah salah.',
        ]);

        if ($onboardingCase->employee_record_id) {
            throw ValidationException::withMessages([
                'unlink' => 'Rekod pekerja yang telah didaftarkan secara automatik tidak boleh dibatalkan melalui tindakan ini.',
            ]);
        }

        if (in_array($onboardingCase->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'unlink' => 'Pautan bagi kes onboarding yang selesai atau dibatalkan tidak boleh diubah.',
            ]);
        }

        if (! $onboardingCase->legacy_employee_id && ! $onboardingCase->employee_user_id) {
            throw ValidationException::withMessages([
                'unlink' => 'Kes onboarding ini tidak mempunyai pautan pekerja untuk dibatalkan.',
            ]);
        }

        $result = DB::transaction(function () use ($request, $onboardingCase, $validated) {
            $case = OnboardingCase::query()
                ->with('tasks')
                ->lockForUpdate()
                ->findOrFail($onboardingCase->getKey());

            if (
                $case->employee_record_id
                || in_array($case->status, ['completed', 'cancelled'], true)
                || (! $case->legacy_employee_id && ! $case->employee_user_id)
            ) {
                throw ValidationException::withMessages([
                    'unlink' => 'Status pautan telah berubah. Muat semula halaman sebelum mencuba semula.',
                ]);
            }

            $legacyEmployeeId = $case->legacy_employee_id;
            $employeeUserId = $case->employee_user_id;
            $taskQuery = $case->tasks()
                ->where('assignee_role', 'employee')
                ->when(
                    $employeeUserId,
                    fn ($query) => $query->where('assignee_user_id', $employeeUserId),
                    fn ($query) => $query->whereRaw('1 = 0'),
                );
            $tasksBefore = (clone $taskQuery)
                ->get([
                    'id',
                    'status',
                    'assignee_user_id',
                    'completion_notes',
                    'completed_by',
                    'completed_at',
                ])
                ->map(fn (OnboardingTask $task) => [
                    'id' => $task->getKey(),
                    'status' => $task->status,
                    'assignee_user_id' => $task->assignee_user_id,
                    'completed_by' => $task->completed_by,
                    'completed_at' => $task->completed_at?->toIso8601String(),
                ])
                ->values()
                ->all();
            $employeeLinkDeactivated = false;

            if (
                ($validated['deactivate_employee_link'] ?? false)
                && $legacyEmployeeId
                && $employeeUserId
            ) {
                $usedByAnotherCase = OnboardingCase::query()
                    ->where('id', '<>', $case->getKey())
                    ->where('legacy_employee_id', $legacyEmployeeId)
                    ->where('employee_user_id', $employeeUserId)
                    ->whereIn('status', ['pending', 'active', 'completed'])
                    ->exists();

                if ($usedByAnotherCase) {
                    throw ValidationException::withMessages([
                        'deactivate_employee_link' => 'Pautan akaun ini masih digunakan oleh kes onboarding lain dan tidak boleh dinyahaktifkan.',
                    ]);
                }

                $employeeLink = EmployeeUserLink::query()
                    ->where('user_id', $employeeUserId)
                    ->where('employee_id', $legacyEmployeeId)
                    ->where('employee_source', 'legacy')
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if ($employeeLink) {
                    $linkBefore = [
                        'user_id' => $employeeLink->user_id,
                        'employee_id' => $employeeLink->employee_id,
                        'employee_source' => $employeeLink->employee_source,
                        'office_location_id' => $employeeLink->office_location_id,
                        'is_active' => true,
                    ];
                    $employeeLink->update([
                        'is_active' => false,
                        'updated_by' => $request->user()->getAuthIdentifier(),
                    ]);
                    $employeeLinkDeactivated = true;

                    AuditLogger::record(
                        $request,
                        'employee_link.deactivated_from_onboarding_correction',
                        'employee_user_links',
                        $employeeLink->getKey(),
                        oldValues: $linkBefore,
                        newValues: [
                            ...$linkBefore,
                            'is_active' => false,
                            'reason' => $validated['reason'],
                            'onboarding_case_id' => $case->getKey(),
                        ],
                    );
                }
            }

            $case->update([
                'legacy_employee_id' => null,
                'employee_user_id' => null,
            ]);
            $taskQuery->update([
                'assignee_user_id' => null,
                'status' => 'pending',
                'completion_notes' => null,
                'completed_by' => null,
                'completed_at' => null,
            ]);

            AuditLogger::record(
                $request,
                'onboarding.employee_unlinked',
                'onboarding_cases',
                $case->getKey(),
                oldValues: [
                    'legacy_employee_id' => $legacyEmployeeId,
                    'employee_user_id' => $employeeUserId,
                    'employee_tasks' => $tasksBefore,
                ],
                newValues: [
                    'legacy_employee_id' => null,
                    'employee_user_id' => null,
                    'reset_employee_task_ids' => collect($tasksBefore)
                        ->pluck('id')
                        ->values()
                        ->all(),
                    'employee_link_deactivated' => $employeeLinkDeactivated,
                    'reason' => $validated['reason'],
                ],
            );

            return [
                'employee_link_deactivated' => $employeeLinkDeactivated,
                'reset_task_count' => count($tasksBefore),
            ];
        });

        return back()->with('toast', [
            'type' => 'success',
            'message' => $result['employee_link_deactivated']
                ? 'Pautan onboarding dan pautan akaun pekerja telah dibatalkan. Calon kini boleh didaftarkan sebagai pekerja.'
                : 'Pautan onboarding telah dibatalkan tanpa memadam akaun atau rekod pekerja. Calon kini boleh didaftarkan sebagai pekerja.',
        ]);
    }

    public function changeCaseStatus(
        Request $request,
        OnboardingCase $onboardingCase,
    ): RedirectResponse {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['start', 'complete', 'cancel', 'reopen'])],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $action = $validated['action'];

        if ($action === 'complete') {
            abort_unless($request->user()->hasPermission('onboarding.approve'), 403);
        } else {
            abort_unless($request->user()->hasPermission('onboarding.manage'), 403);
        }

        $next = [
            'pending:start' => 'active',
            'active:complete' => 'completed',
            'pending:cancel' => 'cancelled',
            'active:cancel' => 'cancelled',
            'cancelled:reopen' => 'pending',
        ][$onboardingCase->status.':'.$action] ?? null;

        if (! $next) {
            throw ValidationException::withMessages([
                'action' => 'Tindakan ini tidak sah untuk status kes semasa.',
            ]);
        }

        if ($action === 'complete') {
            if ((int) $onboardingCase->created_by === (int) $request->user()->getAuthIdentifier()) {
                throw ValidationException::withMessages([
                    'action' => 'Pencipta kes onboarding tidak boleh melengkapkan kelulusan kes yang sama.',
                ]);
            }

            if (
                (
                    ! $onboardingCase->employee_record_id
                    && ! $onboardingCase->legacy_employee_id
                )
                || ! $onboardingCase->employee_user_id
            ) {
                throw ValidationException::withMessages([
                    'action' => 'Daftarkan calon sebagai pekerja atau pautkan pekerja sedia ada sebelum melengkapkan onboarding.',
                ]);
            }

            if (
                $onboardingCase->tasks()
                    ->where('is_required', true)
                    ->whereNotIn('status', ['completed', 'waived'])
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'action' => 'Semua tugasan wajib mesti diselesaikan atau dikecualikan.',
                ]);
            }
        }

        $old = $onboardingCase->status;
        $onboardingCase->update([
            'status' => $next,
            'notes' => $validated['notes'] ?? $onboardingCase->notes,
            'started_at' => $action === 'start' ? now() : $onboardingCase->started_at,
            'completed_at' => $action === 'complete' ? now() : null,
        ]);
        AuditLogger::record(
            $request,
            "onboarding.case_{$action}",
            'onboarding_cases',
            $onboardingCase->getKey(),
            oldValues: ['status' => $old],
            newValues: ['status' => $next, 'notes' => $validated['notes'] ?? null],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Status kes onboarding telah dikemas kini.',
        ]);
    }

    public function updateTask(
        Request $request,
        OnboardingCase $onboardingCase,
        OnboardingTask $task,
    ): RedirectResponse {
        abort_unless($task->onboarding_case_id === $onboardingCase->getKey(), 404);
        $validated = $request->validate([
            'status' => ['required', Rule::in([
                'pending',
                'in_progress',
                'completed',
                'waived',
            ])],
            'assignee_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'completion_notes' => [
                Rule::requiredIf(in_array($request->input('status'), ['completed', 'waived'], true)),
                'nullable',
                'string',
                'max:3000',
            ],
        ]);
        $old = $task->only(['status', 'assignee_user_id', 'completion_notes']);
        $done = in_array($validated['status'], ['completed', 'waived'], true);
        $task->update([
            ...$validated,
            'completed_by' => $done ? $request->user()->getAuthIdentifier() : null,
            'completed_at' => $done ? now() : null,
        ]);
        AuditLogger::record(
            $request,
            'onboarding.task_updated',
            'onboarding_tasks',
            $task->getKey(),
            oldValues: $old,
            newValues: $task->only([
                'status',
                'assignee_user_id',
                'completion_notes',
                'completed_by',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Tugasan onboarding telah dikemas kini.',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = OnboardingCase::query()->with(['candidate.requisition', 'tasks']);
        $this->scopeCasesForUser($query, $request->user());

        return response()->streamDownload(function () use ($query) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, [
                'No. Calon',
                'Nama',
                'Jawatan',
                'Tarikh Mula',
                'Status',
                'Kemajuan',
                'No. Pekerja',
                'Tugasan Lewat',
            ]);
            $query
                ->orderBy('id')
                ->chunkById(100, function ($cases) use ($stream) {
                    foreach ($cases as $case) {
                        $tasks = $case->tasks;
                        $done = $tasks->whereIn('status', ['completed', 'waived'])->count();
                        fputcsv($stream, [
                            $case->candidate?->candidate_number,
                            $case->candidate?->name,
                            $case->candidate?->requisition?->title,
                            $case->start_date?->toDateString(),
                            $case->status,
                            $tasks->count() > 0
                                ? round(($done / $tasks->count()) * 100).'%'
                                : '0%',
                            $case->employeeRecord?->employee_number
                                ?? $case->legacy_employee_id,
                            $tasks
                                ->whereIn('status', ['pending', 'in_progress'])
                                ->filter(fn ($task) => $task->due_date?->isPast())
                                ->count(),
                        ]);
                    }
                });
            fclose($stream);
        }, 'laporan-onboarding-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function scopeCasesForUser(Builder $query, User $user): void
    {
        if (
            $user->hasPermission('onboarding.manage')
            || $user->hasPermission('onboarding.approve')
        ) {
            return;
        }

        $query->where(function (Builder $query) use ($user) {
            $query->where('manager_user_id', $user->getAuthIdentifier())
                ->orWhere('buddy_user_id', $user->getAuthIdentifier());
        });
    }

    private function serializeCase(OnboardingCase $case): array
    {
        $tasks = $case->tasks;
        $done = $tasks->whereIn('status', ['completed', 'waived'])->count();

        return [
            'id' => $case->getKey(),
            'candidate' => [
                'id' => $case->candidate?->getKey(),
                'candidate_number' => $case->candidate?->candidate_number,
                'name' => $case->candidate?->name,
                'email' => $case->candidate?->email,
                'phone' => $case->candidate?->phone,
                'identity_number' => $case->candidate?->nric,
                'position' => $case->candidate?->requisition?->title,
                'requisition_code' => $case->candidate?->requisition?->code,
            ],
            'template' => $case->template?->name,
            'legacy_employee_id' => $case->legacy_employee_id,
            'employee_record_id' => $case->employee_record_id,
            'employee_user_id' => $case->employee_user_id,
            'employee_user' => $case->employeeUser?->name,
            'employee_record' => $case->employeeRecord
                ? [
                    'directory_id' => $case->employeeRecord->directory_id,
                    'employee_number' => $case->employeeRecord->employee_number,
                    'official_email' => $case->employeeRecord->official_email,
                    'status' => $case->employeeRecord->status,
                    'activation_date' => $case->employeeRecord->start_date?->toDateString(),
                    'office' => $case->employeeRecord->officeLocation?->name,
                ]
                : null,
            'offer' => $case->offer
                ? [
                    'position_name' => $case->offer->position_name,
                    'department_id' => $case->offer->department_id,
                    'employment_type' => $case->offer->employment_type,
                    'salary' => (float) $case->offer->salary,
                    'probation_months' => $case->offer->probation_months,
                    'start_date' => $case->offer->start_date?->toDateString(),
                    'status' => $case->offer->status,
                ]
                : null,
            'registration_defaults' => [
                'identity_number' => $case->candidate?->nric ?? '',
                'employee_number' => sprintf(
                    'IBCO-%s-%04d',
                    $case->start_date?->format('Y') ?? now()->format('Y'),
                    $case->candidate?->getKey() ?? $case->getKey(),
                ),
                'official_email' => strtolower($case->candidate?->email ?? ''),
            ],
            'manager_user_id' => $case->manager_user_id,
            'manager' => $case->manager?->name,
            'buddy_user_id' => $case->buddy_user_id,
            'buddy' => $case->buddy?->name,
            'start_date' => $case->start_date?->toDateString(),
            'status' => $case->status,
            'notes' => $case->notes,
            'progress' => $tasks->count() > 0
                ? (int) round(($done / $tasks->count()) * 100)
                : 0,
            'overdue_tasks' => $tasks
                ->whereIn('status', ['pending', 'in_progress'])
                ->filter(fn ($task) => $task->due_date?->isPast())
                ->count(),
            'tasks' => $tasks->map(fn (OnboardingTask $task) => [
                'id' => $task->getKey(),
                'title' => $task->title,
                'description' => $task->description,
                'category' => $task->category,
                'assignee_role' => $task->assignee_role,
                'assignee_user_id' => $task->assignee_user_id,
                'assignee' => $task->assignee?->name,
                'due_date' => $task->due_date?->toDateString(),
                'is_required' => $task->is_required,
                'status' => $task->status,
                'completion_notes' => $task->completion_notes,
            ]),
        ];
    }
}
