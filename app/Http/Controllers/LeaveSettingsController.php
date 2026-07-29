<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\EmployeeLeaveRequest;
use App\Models\LeaveApprovalAssignment;
use App\Models\LeaveEntitlement;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LeaveSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2020', 'max:2100'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $year = (int) ($validated['year'] ?? now()->year);
        $search = trim($validated['search'] ?? '');
        $employees = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('rcd_enable', 1)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('nama', 'like', "%{$search}%")
                        ->orWhere('employeeID', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama')
            ->limit(500)
            ->get(['id', 'employeeID as employee_id', 'nama as name']);
        $employeeIds = $employees->pluck('id');
        $positions = $employeeIds->isEmpty()
            ? collect()
            : DB::connection('ibco')
                ->table('maklumatjawatan')
                ->whereIn('id_pekerja', $employeeIds)
                ->where('rcd_enable', 1)
                ->orderByDesc('id')
                ->get(['id_pekerja', 'id_department'])
                ->unique(fn ($position) => (string) $position->id_pekerja)
                ->keyBy(fn ($position) => (string) $position->id_pekerja);
        $employeeMap = $employees->keyBy(fn ($employee) => (string) $employee->id);
        $entitlements = LeaveEntitlement::query()
            ->with(['leaveType:id,name', 'transactions:id,leave_entitlement_id,days'])
            ->where('year', $year)
            ->when(
                $employeeIds->isNotEmpty(),
                fn ($query) => $query->whereIn('employee_id', $employeeIds),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->latest('updated_at')
            ->limit(500)
            ->get();
        $departments = DB::connection('ibco')
            ->table('xdepartment')
            ->where('rcd_enable', 1)
            ->orderBy('description')
            ->get(['id', 'description as name']);
        $departmentMap = $departments->keyBy(fn ($department) => (string) $department->id);

        return Inertia::render('LeaveSettings/Index', [
            'filters' => [
                'year' => $year,
                'search' => $search,
            ],
            'leaveTypes' => LeaveType::query()
                ->orderBy('name')
                ->get()
                ->map(fn (LeaveType $type) => [
                    'id' => $type->getKey(),
                    'code' => $type->code,
                    'name' => $type->name,
                    'default_entitlement_days' => (float) $type->default_entitlement_days,
                    'deduct_balance' => $type->deduct_balance,
                    'allow_half_day' => $type->allow_half_day,
                    'requires_attachment' => $type->requires_attachment,
                    'is_active' => $type->is_active,
                ]),
            'employees' => $employees->map(fn ($employee) => [
                'id' => (int) $employee->id,
                'employee_id' => $employee->employee_id,
                'name' => $employee->name,
                'department_id' => isset($positions[(string) $employee->id])
                    ? (int) $positions[(string) $employee->id]->id_department
                    : null,
            ])->values(),
            'entitlements' => $entitlements->map(function (
                LeaveEntitlement $entitlement,
            ) use ($employeeMap) {
                $employee = $employeeMap[(string) $entitlement->employee_id] ?? null;

                return [
                    'id' => $entitlement->getKey(),
                    'employee_id' => $entitlement->employee_id,
                    'employee_number' => $employee?->employee_id,
                    'employee_name' => $employee?->name,
                    'leave_type_id' => $entitlement->leave_type_id,
                    'leave_type' => $entitlement->leaveType?->name,
                    'year' => $entitlement->year,
                    'entitled_days' => (float) $entitlement->entitled_days,
                    'carry_forward_days' => (float) $entitlement->carry_forward_days,
                    'adjustment_days' => (float) $entitlement->adjustment_days,
                    'balance' => (float) $entitlement->entitled_days
                        + (float) $entitlement->carry_forward_days
                        + (float) $entitlement->adjustment_days
                        + (float) $entitlement->transactions->sum('days'),
                    'notes' => $entitlement->notes,
                ];
            }),
            'departments' => $departments->map(fn ($department) => [
                'id' => (int) $department->id,
                'name' => $department->name,
            ]),
            'supervisors' => User::query()
                ->whereHas('roleAssignments', fn ($query) => $query
                    ->where('role', UserRole::Supervisor->value))
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
            'assignments' => LeaveApprovalAssignment::query()
                ->with('approver:id,name,email')
                ->orderBy('department_id')
                ->get()
                ->map(fn (LeaveApprovalAssignment $assignment) => [
                    'id' => $assignment->getKey(),
                    'department_id' => $assignment->department_id,
                    'department' => $departmentMap[(string) $assignment->department_id]->name
                        ?? "Jabatan #{$assignment->department_id}",
                    'approver_user_id' => $assignment->approver_user_id,
                    'approver_name' => $assignment->approver?->name,
                    'approver_email' => $assignment->approver?->email,
                    'is_active' => $assignment->is_active,
                ]),
            'holidays' => PublicHoliday::query()
                ->whereYear('holiday_date', $year)
                ->orderBy('holiday_date')
                ->get()
                ->map(fn (PublicHoliday $holiday) => [
                    'id' => $holiday->getKey(),
                    'name' => $holiday->name,
                    'holiday_date' => $holiday->holiday_date?->toDateString(),
                    'is_active' => $holiday->is_active,
                ]),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $validated = $this->validateType($request);
        $type = LeaveType::query()->create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'leave_type.created',
            'leave_types',
            $type->getKey(),
            newValues: $type->only([
                'code',
                'name',
                'default_entitlement_days',
                'deduct_balance',
                'allow_half_day',
                'requires_attachment',
                'is_active',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jenis cuti berjaya ditambah.',
        ]);
    }

    public function updateType(
        Request $request,
        LeaveType $leaveType,
    ): RedirectResponse {
        $validated = $this->validateType($request, $leaveType);
        $before = $leaveType->only([
            'code',
            'name',
            'default_entitlement_days',
            'deduct_balance',
            'allow_half_day',
            'requires_attachment',
            'is_active',
        ]);
        $leaveType->update([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'leave_type.updated',
            'leave_types',
            $leaveType->getKey(),
            oldValues: $before,
            newValues: $leaveType->only(array_keys($before)),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jenis cuti berjaya dikemas kini.',
        ]);
    }

    public function toggleType(
        Request $request,
        LeaveType $leaveType,
    ): RedirectResponse {
        if (
            $leaveType->is_active
            && EmployeeLeaveRequest::query()
                ->where('system_leave_type_id', $leaveType->getKey())
                ->where('status', 'pending')
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'leave_type' => 'Jenis cuti ini mempunyai permohonan yang masih menunggu.',
            ]);
        }

        $oldStatus = $leaveType->is_active;
        $leaveType->update([
            'is_active' => ! $leaveType->is_active,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            $leaveType->is_active
                ? 'leave_type.activated'
                : 'leave_type.deactivated',
            'leave_types',
            $leaveType->getKey(),
            oldValues: ['is_active' => $oldStatus],
            newValues: ['is_active' => $leaveType->is_active],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $leaveType->is_active
                ? 'Jenis cuti telah diaktifkan.'
                : 'Jenis cuti telah dinyahaktifkan.',
        ]);
    }

    public function saveEntitlement(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'min:1'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'entitled_days' => ['required', 'numeric', 'min:0', 'max:365'],
            'carry_forward_days' => ['required', 'numeric', 'min:0', 'max:365'],
            'adjustment_days' => ['required', 'numeric', 'min:-365', 'max:365'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $employeeExists = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('id', $validated['employee_id'])
            ->where('rcd_enable', 1)
            ->exists();

        if (! $employeeExists) {
            throw ValidationException::withMessages([
                'employee_id' => 'Pekerja aktif tidak dijumpai dalam db_spp.',
            ]);
        }

        $entitlement = LeaveEntitlement::query()->firstOrNew([
            'employee_id' => $validated['employee_id'],
            'leave_type_id' => $validated['leave_type_id'],
            'year' => $validated['year'],
        ]);
        $before = $entitlement->exists
            ? $entitlement->only([
                'entitled_days',
                'carry_forward_days',
                'adjustment_days',
                'notes',
            ])
            : [];
        $entitlement->fill([
            'entitled_days' => $validated['entitled_days'],
            'carry_forward_days' => $validated['carry_forward_days'],
            'adjustment_days' => $validated['adjustment_days'],
            'notes' => $validated['notes'] ?? null,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ])->save();

        AuditLogger::record(
            $request,
            $before === [] ? 'leave_entitlement.created' : 'leave_entitlement.updated',
            'leave_entitlements',
            $entitlement->getKey(),
            oldValues: $before,
            newValues: $entitlement->only([
                'employee_id',
                'leave_type_id',
                'year',
                'entitled_days',
                'carry_forward_days',
                'adjustment_days',
                'notes',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Kelayakan dan baki cuti berjaya disimpan.',
        ]);
    }

    public function saveAssignment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'min:1'],
            'approver_user_id' => ['required', 'integer', 'exists:users,id'],
            'is_active' => ['required', 'accepted'],
        ]);
        $departmentExists = DB::connection('ibco')
            ->table('xdepartment')
            ->where('id', $validated['department_id'])
            ->where('rcd_enable', 1)
            ->exists();

        if (! $departmentExists) {
            throw ValidationException::withMessages([
                'department_id' => 'Jabatan aktif tidak dijumpai dalam db_spp.',
            ]);
        }

        $approver = User::query()->findOrFail($validated['approver_user_id']);

        if (! $approver->hasPermission('leave.supervise')) {
            throw ValidationException::withMessages([
                'approver_user_id' => 'Pengguna ini tidak mempunyai role Penyelia / Ketua Jabatan.',
            ]);
        }

        $assignment = LeaveApprovalAssignment::query()->updateOrCreate(
            ['department_id' => $validated['department_id']],
            [
                'approver_user_id' => $validated['approver_user_id'],
                'is_active' => $validated['is_active'],
                'updated_by' => $request->user()->getAuthIdentifier(),
            ],
        );
        AuditLogger::record(
            $request,
            'leave_approver.assigned',
            'leave_approval_assignments',
            $assignment->getKey(),
            newValues: $assignment->only([
                'department_id',
                'approver_user_id',
                'is_active',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Penyelia kelulusan jabatan berjaya ditetapkan.',
        ]);
    }

    public function destroyAssignment(
        Request $request,
        LeaveApprovalAssignment $assignment,
    ): RedirectResponse {
        if (
            EmployeeLeaveRequest::query()
                ->where('department_id', $assignment->department_id)
                ->where('status', 'pending')
                ->where('approval_stage', 'supervisor')
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'assignment' => 'Tetapan penyelia tidak boleh dibuang kerana masih ada permohonan menunggu.',
            ]);
        }

        $before = $assignment->only([
            'department_id',
            'approver_user_id',
            'is_active',
        ]);
        $id = $assignment->getKey();
        $assignment->delete();
        AuditLogger::record(
            $request,
            'leave_approver.removed',
            'leave_approval_assignments',
            $id,
            oldValues: $before,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Tetapan penyelia jabatan telah dibuang.',
        ]);
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'holiday_date' => ['required', 'date', 'unique:public_holidays,holiday_date'],
        ]);
        $holiday = PublicHoliday::query()->create([
            ...$validated,
            'is_active' => true,
            'created_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'public_holiday.created',
            'public_holidays',
            $holiday->getKey(),
            newValues: [
                'name' => $holiday->name,
                'holiday_date' => $holiday->holiday_date?->toDateString(),
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Cuti umum berjaya ditambah.',
        ]);
    }

    public function destroyHoliday(
        Request $request,
        PublicHoliday $holiday,
    ): RedirectResponse {
        $before = [
            'name' => $holiday->name,
            'holiday_date' => $holiday->holiday_date?->toDateString(),
        ];
        $id = $holiday->getKey();
        $holiday->delete();
        AuditLogger::record(
            $request,
            'public_holiday.deleted',
            'public_holidays',
            $id,
            oldValues: $before,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Cuti umum berjaya dibuang.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateType(
        Request $request,
        ?LeaveType $leaveType = null,
    ): array {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_]+$/',
                Rule::unique('leave_types', 'code')->ignore($leaveType?->getKey()),
            ],
            'name' => ['required', 'string', 'max:150'],
            'default_entitlement_days' => ['required', 'numeric', 'min:0', 'max:365'],
            'deduct_balance' => ['required', 'boolean'],
            'allow_half_day' => ['required', 'boolean'],
            'requires_attachment' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
