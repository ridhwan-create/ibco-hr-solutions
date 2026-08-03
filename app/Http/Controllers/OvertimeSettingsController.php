<?php

namespace App\Http\Controllers;

use App\Models\OvertimeApprovalAssignment;
use App\Models\OvertimeRequest;
use App\Models\OvertimeType;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OvertimeSettingsController extends Controller
{
    public function index(): Response
    {
        $departments = DB::connection('ibco')
            ->table('xdepartment')
            ->where('rcd_enable', 1)
            ->orderBy('description')
            ->get(['id', 'description as name']);
        $departmentMap = $departments->pluck('name', 'id');
        $supervisors = User::query()
            ->with('roleAssignments')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('overtime.supervise'))
            ->values()
            ->map(fn (User $user) => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ]);

        return Inertia::render('OvertimeSettings/Index', [
            'overtimeTypes' => OvertimeType::query()
                ->orderBy('name')
                ->get()
                ->map(fn (OvertimeType $type) => [
                    'id' => $type->getKey(),
                    'code' => $type->code,
                    'name' => $type->name,
                    'rate_multiplier' => (float) $type->rate_multiplier,
                    'minimum_minutes' => $type->minimum_minutes,
                    'maximum_hours' => (float) $type->maximum_hours,
                    'requires_attachment' => $type->requires_attachment,
                    'is_active' => $type->is_active,
                ]),
            'departments' => $departments,
            'supervisors' => $supervisors,
            'assignments' => OvertimeApprovalAssignment::query()
                ->with('approver:id,name,email')
                ->orderBy('department_id')
                ->get()
                ->map(fn (OvertimeApprovalAssignment $assignment) => [
                    'id' => $assignment->getKey(),
                    'department_id' => $assignment->department_id,
                    'department' => $departmentMap[$assignment->department_id]
                        ?? "Jabatan #{$assignment->department_id}",
                    'approver_user_id' => $assignment->approver_user_id,
                    'approver_name' => $assignment->approver?->name,
                    'approver_email' => $assignment->approver?->email,
                    'is_active' => $assignment->is_active,
                ]),
        ]);
    }

    public function storeType(Request $request): RedirectResponse
    {
        $validated = $this->validatedType($request);
        $type = OvertimeType::query()->create([
            ...$validated,
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'overtime_type.created',
            'overtime_types',
            $type->getKey(),
            newValues: $type->only([
                'code',
                'name',
                'rate_multiplier',
                'minimum_minutes',
                'maximum_hours',
                'requires_attachment',
                'is_active',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jenis OT telah ditambah.',
        ]);
    }

    public function updateType(
        Request $request,
        OvertimeType $overtimeType,
    ): RedirectResponse {
        $validated = $this->validatedType($request, $overtimeType);
        $oldValues = $overtimeType->only(array_keys($validated));
        $overtimeType->update([
            ...$validated,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'overtime_type.updated',
            'overtime_types',
            $overtimeType->getKey(),
            oldValues: $oldValues,
            newValues: $overtimeType->fresh()->only(array_keys($validated)),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jenis OT telah dikemas kini.',
        ]);
    }

    public function toggleType(
        Request $request,
        OvertimeType $overtimeType,
    ): RedirectResponse {
        if (
            $overtimeType->is_active
            && OvertimeRequest::query()
                ->where('overtime_type_id', $overtimeType->getKey())
                ->where('status', 'pending')
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'type' => 'Jenis OT ini mempunyai permohonan yang masih menunggu.',
            ]);
        }

        $oldStatus = $overtimeType->is_active;
        $overtimeType->update([
            'is_active' => ! $oldStatus,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            $overtimeType->is_active
                ? 'overtime_type.activated'
                : 'overtime_type.deactivated',
            'overtime_types',
            $overtimeType->getKey(),
            oldValues: ['is_active' => $oldStatus],
            newValues: ['is_active' => $overtimeType->is_active],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $overtimeType->is_active
                ? 'Jenis OT telah diaktifkan.'
                : 'Jenis OT telah dinyahaktifkan.',
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

        if (! $approver->hasPermission('overtime.supervise')) {
            throw ValidationException::withMessages([
                'approver_user_id' => 'Pengguna mesti mempunyai kebenaran penyeliaan kerja lebih masa.',
            ]);
        }

        $assignment = OvertimeApprovalAssignment::query()
            ->where('department_id', $validated['department_id'])
            ->first();
        $oldValues = $assignment?->only([
            'department_id',
            'approver_user_id',
            'is_active',
        ]) ?? [];
        $assignment = OvertimeApprovalAssignment::query()->updateOrCreate(
            ['department_id' => $validated['department_id']],
            [
                'approver_user_id' => $validated['approver_user_id'],
                'is_active' => $validated['is_active'] ?? true,
                'updated_by' => $request->user()->getAuthIdentifier(),
            ],
        );

        AuditLogger::record(
            $request,
            'overtime_approver.assigned',
            'overtime_approval_assignments',
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
            'message' => 'Penyelia OT jabatan telah ditetapkan.',
        ]);
    }

    public function destroyAssignment(
        Request $request,
        OvertimeApprovalAssignment $assignment,
    ): RedirectResponse {
        if (
            OvertimeRequest::query()
                ->where('department_id', $assignment->department_id)
                ->where('status', 'pending')
                ->where('approval_stage', 'supervisor')
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'assignment' => 'Pemetaan tidak boleh dibuang kerana masih ada permohonan menunggu penyelia.',
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
            'overtime_approver.removed',
            'overtime_approval_assignments',
            $id,
            oldValues: $oldValues,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Pemetaan penyelia OT telah dibuang.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedType(
        Request $request,
        ?OvertimeType $overtimeType = null,
    ): array {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('overtime_types', 'code')->ignore($overtimeType),
            ],
            'name' => ['required', 'string', 'max:150'],
            'rate_multiplier' => ['required', 'numeric', 'min:0', 'max:10'],
            'minimum_minutes' => ['required', 'integer', 'min:1', 'max:720'],
            'maximum_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'requires_attachment' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        if (
            (int) $validated['minimum_minutes']
            > ((float) $validated['maximum_hours'] * 60)
        ) {
            throw ValidationException::withMessages([
                'minimum_minutes' => 'Minimum minit tidak boleh melebihi had maksimum jam.',
            ]);
        }

        $validated['code'] = strtoupper($validated['code']);

        return $validated;
    }
}
