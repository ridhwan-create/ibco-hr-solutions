<?php

namespace App\Http\Controllers;

use App\Models\EmployeeRecord;
use App\Models\ClaimApprovalAssignment;
use App\Models\ClaimLimitOverride;
use App\Models\ClaimRequest;
use App\Models\ClaimType;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClaimSettingsController extends Controller
{
    public function index(): Response
    {
        $departments = DB::connection('ibco')
            ->table('xdepartment')
            ->where('rcd_enable', 1)
            ->orderBy('description')
            ->get(['id', 'description as name']);
        $departmentMap = $departments->pluck('name', 'id');
        $employees = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('rcd_enable', 1)
            ->orderBy('nama')
            ->get(['id', 'employeeID as employee_number', 'nama as name']);
        $employees = $employees
            ->concat(
                EmployeeRecord::query()
                    ->whereIn('status', ['pending_activation', 'active'])
                    ->orderBy('name')
                    ->get()
                    ->map(fn (EmployeeRecord $employee) => (object) [
                        'id' => $employee->directory_id,
                        'employee_number' => $employee->employee_number,
                        'name' => $employee->name,
                    ]),
            )
            ->sortBy('name')
            ->values();
        $employeeMap = $employees->keyBy('id');
        $positions = DB::connection('ibco')
            ->table('maklumatjawatan as position')
            ->leftJoin('maklumatpekerja as employee', 'employee.id', '=', 'position.id_pekerja')
            ->where('position.rcd_enable', 1)
            ->orderBy('employee.nama')
            ->get([
                'position.id',
                'position.id_pekerja as employee_id',
                'position.jawatan as title',
                'employee.nama as employee_name',
            ]);
        $positionMap = $positions->keyBy('id');
        $supervisors = User::query()
            ->with('roleAssignments')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('claims.supervise'))
            ->values()
            ->map(fn (User $user) => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ]);

        return Inertia::render('ClaimSettings/Index', [
            'claimTypes' => ClaimType::query()
                ->orderBy('name')
                ->get()
                ->map(fn (ClaimType $type) => [
                    'id' => $type->getKey(),
                    'code' => $type->code,
                    'name' => $type->name,
                    'description' => $type->description,
                    'max_per_claim' => $type->max_per_claim === null
                        ? null
                        : (float) $type->max_per_claim,
                    'monthly_limit' => $type->monthly_limit === null
                        ? null
                        : (float) $type->monthly_limit,
                    'annual_limit' => $type->annual_limit === null
                        ? null
                        : (float) $type->annual_limit,
                    'requires_receipt' => $type->requires_receipt,
                    'requires_receipt_number' => $type->requires_receipt_number,
                    'allow_payroll_reimbursement' => $type
                        ->allow_payroll_reimbursement,
                    'is_active' => $type->is_active,
                ]),
            'departments' => $departments,
            'supervisors' => $supervisors,
            'assignments' => ClaimApprovalAssignment::query()
                ->with('approver:id,name,email')
                ->orderBy('department_id')
                ->get()
                ->map(fn (ClaimApprovalAssignment $assignment) => [
                    'id' => $assignment->getKey(),
                    'department_id' => $assignment->department_id,
                    'department' => $departmentMap[$assignment->department_id]
                        ?? "Jabatan #{$assignment->department_id}",
                    'approver_user_id' => $assignment->approver_user_id,
                    'approver_name' => $assignment->approver?->name,
                    'approver_email' => $assignment->approver?->email,
                    'is_active' => $assignment->is_active,
                ]),
            'employees' => $employees,
            'positions' => $positions,
            'limitOverrides' => ClaimLimitOverride::query()
                ->with('claimType:id,name')
                ->latest()
                ->get()
                ->map(function (ClaimLimitOverride $override) use (
                    $employeeMap,
                    $positionMap,
                ) {
                    $scope = $override->scope_type === 'employee'
                        ? $employeeMap->get($override->scope_id)
                        : $positionMap->get($override->scope_id);

                    return [
                        'id' => $override->getKey(),
                        'claim_type' => $override->claimType?->name,
                        'scope_type' => $override->scope_type,
                        'scope_id' => $override->scope_id,
                        'scope_label' => $override->scope_type === 'employee'
                            ? ($scope?->name ?? "Pekerja #{$override->scope_id}")
                            : trim(
                                ($scope?->title ?? "Jawatan #{$override->scope_id}")
                                .' · '.($scope?->employee_name ?? ''),
                            ),
                        'max_per_claim' => $override->max_per_claim === null
                            ? null
                            : (float) $override->max_per_claim,
                        'monthly_limit' => $override->monthly_limit === null
                            ? null
                            : (float) $override->monthly_limit,
                        'annual_limit' => $override->annual_limit === null
                            ? null
                            : (float) $override->annual_limit,
                        'is_active' => $override->is_active,
                    ];
                }),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $validated = $this->validatedType($request);
        $type = ClaimType::query()->create([
            ...$validated,
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'claim_type.created',
            'claim_types',
            $type->getKey(),
            newValues: $type->only(array_keys($validated)),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jenis tuntutan telah ditambah.',
        ]);
    }

    public function updateType(
        Request $request,
        ClaimType $claimType,
    ): RedirectResponse {
        $validated = $this->validatedType($request, $claimType);
        $oldValues = $claimType->only(array_keys($validated));
        $claimType->update([
            ...$validated,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'claim_type.updated',
            'claim_types',
            $claimType->getKey(),
            oldValues: $oldValues,
            newValues: $claimType->fresh()->only(array_keys($validated)),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jenis tuntutan telah dikemas kini.',
        ]);
    }

    public function toggleType(
        Request $request,
        ClaimType $claimType,
    ): RedirectResponse {
        if (
            $claimType->is_active
            && ClaimRequest::query()
                ->where('claim_type_id', $claimType->getKey())
                ->where('status', 'pending')
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'type' => 'Jenis tuntutan ini mempunyai permohonan yang masih menunggu.',
            ]);
        }

        $oldStatus = $claimType->is_active;
        $claimType->update([
            'is_active' => ! $oldStatus,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            $claimType->is_active
                ? 'claim_type.activated'
                : 'claim_type.deactivated',
            'claim_types',
            $claimType->getKey(),
            oldValues: ['is_active' => $oldStatus],
            newValues: ['is_active' => $claimType->is_active],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $claimType->is_active
                ? 'Jenis tuntutan telah diaktifkan.'
                : 'Jenis tuntutan telah dinyahaktifkan.',
        ]);
    }

    public function saveAssignment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'min:1'],
            'approver_user_id' => ['required', 'integer', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $departmentExists = DB::connection('ibco')
            ->table('xdepartment')
            ->where('id', $validated['department_id'])
            ->where('rcd_enable', 1)
            ->exists();

        if (! $departmentExists) {
            throw ValidationException::withMessages([
                'department_id' => 'Jabatan yang dipilih tidak sah atau tidak aktif.',
            ]);
        }

        $approver = User::query()
            ->with('roleAssignments')
            ->findOrFail($validated['approver_user_id']);

        if (! $approver->hasPermission('claims.supervise')) {
            throw ValidationException::withMessages([
                'approver_user_id' => 'Pengguna mesti mempunyai kebenaran penyeliaan tuntutan.',
            ]);
        }

        $assignment = ClaimApprovalAssignment::query()
            ->where('department_id', $validated['department_id'])
            ->first();
        $oldValues = $assignment?->only([
            'department_id',
            'approver_user_id',
            'is_active',
        ]) ?? [];
        $assignment = ClaimApprovalAssignment::query()->updateOrCreate(
            ['department_id' => $validated['department_id']],
            [
                'approver_user_id' => $validated['approver_user_id'],
                'is_active' => $validated['is_active'] ?? true,
                'updated_by' => $request->user()->getAuthIdentifier(),
            ],
        );

        AuditLogger::record(
            $request,
            'claim_approver.assigned',
            'claim_approval_assignments',
            $assignment->getKey(),
            oldValues: $oldValues,
            newValues: $assignment->only([
                'department_id',
                'approver_user_id',
                'is_active',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Penyelia tuntutan jabatan telah ditetapkan.',
        ]);
    }

    public function destroyAssignment(
        Request $request,
        ClaimApprovalAssignment $assignment,
    ): RedirectResponse {
        if (
            ClaimRequest::query()
                ->where('department_id', $assignment->department_id)
                ->where('status', 'pending')
                ->where('approval_stage', 'supervisor')
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'assignment' => 'Pemetaan tidak boleh dibuang kerana masih ada tuntutan menunggu penyelia.',
            ]);
        }

        $oldValues = $assignment->only([
            'department_id',
            'approver_user_id',
            'is_active',
        ]);
        $id = $assignment->getKey();
        $assignment->delete();

        AuditLogger::record(
            $request,
            'claim_approver.removed',
            'claim_approval_assignments',
            $id,
            oldValues: $oldValues,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Pemetaan penyelia tuntutan telah dibuang.',
        ]);
    }

    public function saveLimitOverride(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'claim_type_id' => ['required', 'integer', 'exists:claim_types,id'],
            'scope_type' => ['required', Rule::in(['employee', 'position'])],
            'scope_id' => ['required', 'integer', 'min:1'],
            'max_per_claim' => ['nullable', 'numeric', 'min:0.01', 'max:9999999.99'],
            'monthly_limit' => ['nullable', 'numeric', 'min:0.01', 'max:9999999.99'],
            'annual_limit' => ['nullable', 'numeric', 'min:0.01', 'max:9999999.99'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (
            $validated['max_per_claim'] === null
            && $validated['monthly_limit'] === null
            && $validated['annual_limit'] === null
        ) {
            throw ValidationException::withMessages([
                'max_per_claim' => 'Masukkan sekurang-kurangnya satu had khas.',
            ]);
        }

        $exists = $validated['scope_type'] === 'employee'
            ? DB::connection('ibco')
                ->table('maklumatpekerja')
                ->where('id', $validated['scope_id'])
                ->where('rcd_enable', 1)
                ->exists()
                || EmployeeRecord::query()
                    ->where('directory_id', $validated['scope_id'])
                    ->whereIn('status', ['pending_activation', 'active'])
                    ->exists()
            : DB::connection('ibco')
                ->table('maklumatjawatan')
                ->where('id', $validated['scope_id'])
                ->where('rcd_enable', 1)
                ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'scope_id' => 'Pekerja atau jawatan yang dipilih tidak sah.',
            ]);
        }

        $override = ClaimLimitOverride::query()
            ->where('claim_type_id', $validated['claim_type_id'])
            ->where('scope_type', $validated['scope_type'])
            ->where('scope_id', $validated['scope_id'])
            ->first();
        $oldValues = $override?->only([
            'max_per_claim',
            'monthly_limit',
            'annual_limit',
            'is_active',
        ]) ?? [];
        $override = ClaimLimitOverride::query()->updateOrCreate(
            [
                'claim_type_id' => $validated['claim_type_id'],
                'scope_type' => $validated['scope_type'],
                'scope_id' => $validated['scope_id'],
            ],
            [
                'max_per_claim' => $validated['max_per_claim'],
                'monthly_limit' => $validated['monthly_limit'],
                'annual_limit' => $validated['annual_limit'],
                'is_active' => $validated['is_active'] ?? true,
                'updated_by' => $request->user()->getAuthIdentifier(),
            ],
        );

        AuditLogger::record(
            $request,
            'claim_limit.saved',
            'claim_limit_overrides',
            $override->getKey(),
            oldValues: $oldValues,
            newValues: $override->only([
                'claim_type_id',
                'scope_type',
                'scope_id',
                'max_per_claim',
                'monthly_limit',
                'annual_limit',
                'is_active',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Had tuntutan khas telah disimpan.',
        ]);
    }

    public function destroyLimitOverride(
        Request $request,
        ClaimLimitOverride $override,
    ): RedirectResponse {
        $oldValues = $override->toArray();
        $id = $override->getKey();
        $override->delete();

        AuditLogger::record(
            $request,
            'claim_limit.removed',
            'claim_limit_overrides',
            $id,
            oldValues: $oldValues,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Had tuntutan khas telah dibuang.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedType(
        Request $request,
        ?ClaimType $claimType = null,
    ): array {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('claim_types', 'code')->ignore($claimType),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'max_per_claim' => ['nullable', 'numeric', 'min:0.01', 'max:9999999.99'],
            'monthly_limit' => ['nullable', 'numeric', 'min:0.01', 'max:9999999.99'],
            'annual_limit' => ['nullable', 'numeric', 'min:0.01', 'max:9999999.99'],
            'requires_receipt' => ['required', 'boolean'],
            'requires_receipt_number' => ['required', 'boolean'],
            'allow_payroll_reimbursement' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (
            $validated['monthly_limit'] !== null
            && $validated['max_per_claim'] !== null
            && (float) $validated['monthly_limit']
                < (float) $validated['max_per_claim']
        ) {
            throw ValidationException::withMessages([
                'monthly_limit' => 'Had bulanan tidak boleh lebih rendah daripada had setiap tuntutan.',
            ]);
        }

        if (
            $validated['annual_limit'] !== null
            && $validated['monthly_limit'] !== null
            && (float) $validated['annual_limit']
                < (float) $validated['monthly_limit']
        ) {
            throw ValidationException::withMessages([
                'annual_limit' => 'Had tahunan tidak boleh lebih rendah daripada had bulanan.',
            ]);
        }

        $validated['code'] = strtoupper($validated['code']);

        return $validated;
    }
}
