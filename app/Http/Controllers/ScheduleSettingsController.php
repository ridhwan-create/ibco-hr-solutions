<?php

namespace App\Http\Controllers;

use App\Models\OfficeLocation;
use App\Models\ScheduleAssignment;
use App\Models\ShiftTemplate;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleSettingsController extends Controller
{
    public function index(): Response
    {
        $assignments = ScheduleAssignment::query()
            ->with(['shiftTemplate:id,code,name', 'officeLocation:id,name'])
            ->latest('effective_from')
            ->latest('id')
            ->get();
        $employeeIds = $assignments
            ->where('scope_type', 'employee')
            ->pluck('employee_id')
            ->filter()
            ->unique();
        $employees = $employeeIds->isEmpty()
            ? collect()
            : DB::connection('ibco')
                ->table('maklumatpekerja')
                ->whereIn('id', $employeeIds)
                ->get(['id', 'employeeID', 'nama'])
                ->keyBy(fn ($employee) => (string) $employee->id);
        $departments = DB::connection('ibco')
            ->table('xdepartment')
            ->where('rcd_enable', 1)
            ->orderBy('description')
            ->get(['id', 'description']);
        $departmentMap = $departments->keyBy(
            fn ($department) => (string) $department->id,
        );

        return Inertia::render('ScheduleSettings/Index', [
            'templates' => ShiftTemplate::query()
                ->orderByDesc('is_default')
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (ShiftTemplate $template) => $this->templatePayload(
                    $template,
                )),
            'assignments' => $assignments->map(
                function (ScheduleAssignment $assignment) use (
                    $employees,
                    $departmentMap,
                ) {
                    $scopeLabel = match ($assignment->scope_type) {
                        'employee' => $employees
                            ->get((string) $assignment->employee_id)
                            ?->nama ?? "Pekerja #{$assignment->employee_id}",
                        'department' => $departmentMap
                            ->get((string) $assignment->department_id)
                            ?->description
                            ?? "Jabatan #{$assignment->department_id}",
                        'office' => $assignment->officeLocation?->name
                            ?? "Lokasi #{$assignment->office_location_id}",
                        default => 'Tidak diketahui',
                    };

                    return [
                        'id' => $assignment->getKey(),
                        'scope_type' => $assignment->scope_type,
                        'scope_label' => $scopeLabel,
                        'shift_template' => $assignment->shiftTemplate
                            ? [
                                'id' => $assignment->shiftTemplate->getKey(),
                                'code' => $assignment->shiftTemplate->code,
                                'name' => $assignment->shiftTemplate->name,
                            ]
                            : null,
                        'effective_from' => $assignment->effective_from
                            ?->toDateString(),
                        'effective_to' => $assignment->effective_to
                            ?->toDateString(),
                        'priority' => $assignment->priority,
                        'notes' => $assignment->notes,
                        'is_active' => $assignment->is_active,
                    ];
                },
            ),
            'employeeOptions' => DB::connection('ibco')
                ->table('maklumatpekerja')
                ->where('rcd_enable', 1)
                ->orderBy('nama')
                ->get(['id', 'employeeID', 'nama'])
                ->map(fn ($employee) => [
                    'id' => (int) $employee->id,
                    'employee_id' => $employee->employeeID,
                    'name' => $employee->nama,
                ]),
            'departmentOptions' => $departments->map(fn ($department) => [
                'id' => (int) $department->id,
                'name' => $department->description,
            ]),
            'officeOptions' => OfficeLocation::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $validated = $this->validateTemplate($request);
        $template = DB::transaction(function () use ($request, $validated) {
            if ($validated['is_default']) {
                ShiftTemplate::query()->update(['is_default' => false]);
            }

            return ShiftTemplate::query()->create([
                ...$validated,
                'created_by' => $request->user()->getAuthIdentifier(),
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
        });

        AuditLogger::record(
            $request,
            'shift_template.created',
            'shift_templates',
            $template->getKey(),
            newValues: $this->templatePayload($template),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Template syif berjaya ditambah.',
        ]);
    }

    public function updateTemplate(
        Request $request,
        ShiftTemplate $shiftTemplate,
    ): RedirectResponse {
        $before = $this->templatePayload($shiftTemplate);
        $validated = $this->validateTemplate($request, $shiftTemplate);

        DB::transaction(function () use (
            $request,
            $shiftTemplate,
            $validated,
        ) {
            if ($validated['is_default']) {
                ShiftTemplate::query()
                    ->whereKeyNot($shiftTemplate->getKey())
                    ->update(['is_default' => false]);
            }

            $shiftTemplate->update([
                ...$validated,
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
        });

        AuditLogger::record(
            $request,
            'shift_template.updated',
            'shift_templates',
            $shiftTemplate->getKey(),
            oldValues: $before,
            newValues: $this->templatePayload($shiftTemplate->fresh()),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Template syif berjaya dikemas kini.',
        ]);
    }

    public function toggleTemplate(
        Request $request,
        ShiftTemplate $shiftTemplate,
    ): RedirectResponse {
        if (
            $shiftTemplate->is_active
            && (
                $shiftTemplate->is_default
                || $shiftTemplate->assignments()->where('is_active', true)->exists()
            )
        ) {
            throw ValidationException::withMessages([
                'template' => 'Template lalai atau template dengan penetapan aktif tidak boleh dinyahaktifkan.',
            ]);
        }

        $before = $shiftTemplate->is_active;
        $shiftTemplate->update([
            'is_active' => ! $shiftTemplate->is_active,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            $shiftTemplate->is_active
                ? 'shift_template.activated'
                : 'shift_template.deactivated',
            'shift_templates',
            $shiftTemplate->getKey(),
            oldValues: ['is_active' => $before],
            newValues: ['is_active' => $shiftTemplate->is_active],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $shiftTemplate->is_active
                ? 'Template syif diaktifkan.'
                : 'Template syif dinyahaktifkan.',
        ]);
    }

    public function storeAssignment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shift_template_id' => [
                'required',
                'integer',
                Rule::exists('shift_templates', 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],
            'scope_type' => ['required', Rule::in(ScheduleAssignment::SCOPES)],
            'employee_id' => ['nullable', 'integer', 'required_if:scope_type,employee'],
            'department_id' => ['nullable', 'integer', 'required_if:scope_type,department'],
            'office_location_id' => [
                'nullable',
                'integer',
                'required_if:scope_type,office',
                'exists:office_locations,id',
            ],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'priority' => ['required', 'integer', 'between:1,999'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $this->assertLegacyScopeExists($validated);

        $assignment = ScheduleAssignment::query()->create([
            ...$validated,
            'employee_id' => $validated['scope_type'] === 'employee'
                ? (int) $validated['employee_id']
                : null,
            'department_id' => $validated['scope_type'] === 'department'
                ? (int) $validated['department_id']
                : null,
            'office_location_id' => $validated['scope_type'] === 'office'
                ? (int) $validated['office_location_id']
                : null,
            'is_active' => true,
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'schedule_assignment.created',
            'schedule_assignments',
            $assignment->getKey(),
            newValues: $assignment->only([
                'shift_template_id',
                'scope_type',
                'employee_id',
                'department_id',
                'office_location_id',
                'effective_from',
                'effective_to',
                'priority',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Penetapan jadual berjaya disimpan.',
        ]);
    }

    public function toggleAssignment(
        Request $request,
        ScheduleAssignment $assignment,
    ): RedirectResponse {
        $before = $assignment->is_active;
        $assignment->update([
            'is_active' => ! $assignment->is_active,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            $assignment->is_active
                ? 'schedule_assignment.activated'
                : 'schedule_assignment.deactivated',
            'schedule_assignments',
            $assignment->getKey(),
            oldValues: ['is_active' => $before],
            newValues: ['is_active' => $assignment->is_active],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $assignment->is_active
                ? 'Penetapan jadual diaktifkan.'
                : 'Penetapan jadual dinyahaktifkan.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplate(
        Request $request,
        ?ShiftTemplate $template = null,
    ): array {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('shift_templates', 'code')->ignore($template),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'break_minutes' => ['required', 'integer', 'between:0,240'],
            'grace_minutes' => ['required', 'integer', 'between:0,120'],
            'early_departure_grace_minutes' => [
                'required',
                'integer',
                'between:0,120',
            ],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['integer', 'between:1,7', 'distinct'],
            'is_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        return [
            ...$validated,
            'code' => strtoupper($validated['code']),
            'crosses_midnight' => $validated['end_time']
                <= $validated['start_time'],
            'work_days' => array_values(
                array_unique(array_map('intval', $validated['work_days'])),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function assertLegacyScopeExists(array $validated): void
    {
        $exists = match ($validated['scope_type']) {
            'employee' => DB::connection('ibco')
                ->table('maklumatpekerja')
                ->where('id', $validated['employee_id'])
                ->where('rcd_enable', 1)
                ->exists(),
            'department' => DB::connection('ibco')
                ->table('xdepartment')
                ->where('id', $validated['department_id'])
                ->where('rcd_enable', 1)
                ->exists(),
            'office' => OfficeLocation::query()
                ->whereKey($validated['office_location_id'])
                ->where('is_active', true)
                ->exists(),
        };

        if (! $exists) {
            throw ValidationException::withMessages([
                'scope_type' => 'Skop pekerja, jabatan atau lokasi yang dipilih tidak aktif.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function templatePayload(ShiftTemplate $template): array
    {
        return [
            'id' => $template->getKey(),
            'code' => $template->code,
            'name' => $template->name,
            'description' => $template->description,
            'start_time' => substr($template->start_time, 0, 5),
            'end_time' => substr($template->end_time, 0, 5),
            'break_minutes' => $template->break_minutes,
            'grace_minutes' => $template->grace_minutes,
            'early_departure_grace_minutes' => $template
                ->early_departure_grace_minutes,
            'crosses_midnight' => $template->crosses_midnight,
            'work_days' => $template->work_days,
            'is_default' => $template->is_default,
            'is_active' => $template->is_active,
        ];
    }
}
