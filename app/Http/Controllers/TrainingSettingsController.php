<?php

namespace App\Http\Controllers;

use App\Models\Competency;
use App\Models\CompetencyRequirement;
use App\Models\TrainingApprovalAssignment;
use App\Models\TrainingBudget;
use App\Models\TrainingCourse;
use App\Models\TrainingProvider;
use App\Models\TrainingSession;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TrainingBudgetManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TrainingSettingsController extends Controller
{
    public function __construct(
        private readonly TrainingBudgetManager $budgets,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'between:2020,2100'],
        ]);
        $year = (int) ($filters['year'] ?? now()->year);
        $departments = DB::connection('ibco')
            ->table('xdepartment')
            ->where('rcd_enable', 1)
            ->orderBy('description')
            ->get(['id', 'description as name']);
        $departmentMap = $departments->pluck('name', 'id');

        return Inertia::render('TrainingSettings/Index', [
            'year' => $year,
            'providers' => TrainingProvider::query()
                ->withCount('courses')
                ->orderBy('name')
                ->get(),
            'courses' => TrainingCourse::query()
                ->with('provider:id,name')
                ->withCount('sessions')
                ->orderBy('title')
                ->get(),
            'sessions' => TrainingSession::query()
                ->with('course.provider:id,name')
                ->withCount(['requests as enrolled_count' => fn ($query) => $query
                    ->whereIn('status', ['approved', 'completed'])])
                ->latest('starts_at')
                ->limit(100)
                ->get(),
            'budgets' => TrainingBudget::query()
                ->where('year', $year)
                ->orderBy('department_id')
                ->get()
                ->map(fn (TrainingBudget $budget) => [
                    'id' => $budget->getKey(),
                    'year' => $budget->year,
                    'department_id' => $budget->department_id,
                    'department' => $budget->department_id
                        ? ($departmentMap[$budget->department_id] ?? 'Jabatan #'.$budget->department_id)
                        : 'Bajet Umum',
                    'budget_code' => $budget->budget_code,
                    'allocated_amount' => (float) $budget->allocated_amount,
                    'notes' => $budget->notes,
                    ...$this->budgets->summary($year, $budget->department_id),
                ]),
            'competencies' => Competency::query()
                ->withCount('requirements')
                ->orderBy('category')
                ->orderBy('name')
                ->get(),
            'requirements' => CompetencyRequirement::query()
                ->with('competency:id,code,name,maximum_level')
                ->orderBy('department_id')
                ->orderBy('position_name')
                ->get()
                ->map(fn (CompetencyRequirement $requirement) => [
                    'id' => $requirement->getKey(),
                    'competency_id' => $requirement->competency_id,
                    'competency' => $requirement->competency?->name,
                    'department_id' => $requirement->department_id,
                    'department' => $requirement->department_id
                        ? ($departmentMap[$requirement->department_id] ?? 'Jabatan #'.$requirement->department_id)
                        : 'Semua Jabatan',
                    'position_name' => $requirement->position_name,
                    'required_level' => $requirement->required_level,
                    'is_mandatory' => $requirement->is_mandatory,
                    'notes' => $requirement->notes,
                ]),
            'assignments' => TrainingApprovalAssignment::query()
                ->with('approver:id,name,email')
                ->orderBy('department_id')
                ->get()
                ->map(fn (TrainingApprovalAssignment $assignment) => [
                    'id' => $assignment->getKey(),
                    'department_id' => $assignment->department_id,
                    'department' => $departmentMap[$assignment->department_id] ?? 'Jabatan #'.$assignment->department_id,
                    'approver_user_id' => $assignment->approver_user_id,
                    'approver' => $assignment->approver?->name,
                    'is_active' => $assignment->is_active,
                ]),
            'departments' => $departments,
            'positionNames' => DB::connection('ibco')
                ->table('maklumatjawatan')
                ->where('rcd_enable', 1)
                ->whereNotNull('jawatan')
                ->where('jawatan', '<>', '')
                ->distinct()
                ->orderBy('jawatan')
                ->pluck('jawatan'),
            'supervisors' => User::query()
                ->with('roleAssignments')
                ->orderBy('name')
                ->get()
                ->filter(fn (User $user) => $user->hasPermission('training.supervise'))
                ->values()
                ->map(fn (User $user) => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
        ]);
    }

    public function storeProvider(Request $request): RedirectResponse
    {
        $validated = $this->validateProvider($request);
        $provider = TrainingProvider::query()->create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        $this->audit($request, 'training.provider_created', 'training_providers', $provider->getKey(), $provider->only(['code', 'name', 'accreditation']));

        return $this->success('Penyedia latihan telah ditambah.');
    }

    public function updateProvider(Request $request, TrainingProvider $provider): RedirectResponse
    {
        $validated = $this->validateProvider($request, $provider);
        $old = $provider->only(['code', 'name', 'contact_person', 'email', 'phone', 'accreditation', 'notes', 'is_active']);
        $provider->update([...$validated, 'code' => strtoupper($validated['code']), 'updated_by' => $request->user()->getAuthIdentifier()]);
        AuditLogger::record($request, 'training.provider_updated', 'training_providers', $provider->getKey(), oldValues: $old, newValues: $provider->only(array_keys($old)));

        return $this->success('Penyedia latihan telah dikemas kini.');
    }

    public function toggleProvider(Request $request, TrainingProvider $provider): RedirectResponse
    {
        $provider->update(['is_active' => ! $provider->is_active, 'updated_by' => $request->user()->getAuthIdentifier()]);
        $this->audit($request, 'training.provider_status_changed', 'training_providers', $provider->getKey(), ['is_active' => $provider->is_active]);

        return $this->success($provider->is_active ? 'Penyedia latihan diaktifkan.' : 'Penyedia latihan dinyahaktifkan.');
    }

    public function storeCourse(Request $request): RedirectResponse
    {
        $validated = $this->validateCourse($request);
        $course = TrainingCourse::query()->create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        $this->audit($request, 'training.course_created', 'training_courses', $course->getKey(), $course->only(['code', 'title', 'category', 'default_cost']));

        return $this->success('Kursus latihan telah ditambah ke katalog.');
    }

    public function updateCourse(Request $request, TrainingCourse $course): RedirectResponse
    {
        $validated = $this->validateCourse($request, $course);
        $old = $course->only(['code', 'title', 'category', 'delivery_method', 'duration_hours', 'cpd_points', 'default_cost', 'is_mandatory', 'is_active']);
        $course->update([...$validated, 'code' => strtoupper($validated['code']), 'updated_by' => $request->user()->getAuthIdentifier()]);
        AuditLogger::record($request, 'training.course_updated', 'training_courses', $course->getKey(), oldValues: $old, newValues: $course->only(array_keys($old)));

        return $this->success('Katalog kursus telah dikemas kini.');
    }

    public function toggleCourse(Request $request, TrainingCourse $course): RedirectResponse
    {
        $course->update(['is_active' => ! $course->is_active, 'updated_by' => $request->user()->getAuthIdentifier()]);
        $this->audit($request, 'training.course_status_changed', 'training_courses', $course->getKey(), ['is_active' => $course->is_active]);

        return $this->success($course->is_active ? 'Kursus diaktifkan.' : 'Kursus dinyahaktifkan.');
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $validated = $this->validateSession($request);
        $session = TrainingSession::query()->create([
            ...$validated,
            'session_code' => strtoupper($validated['session_code']),
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        $this->audit($request, 'training.session_created', 'training_sessions', $session->getKey(), $session->only(['session_code', 'training_course_id', 'starts_at', 'capacity', 'status']));

        return $this->success('Sesi latihan telah dijadualkan.');
    }

    public function updateSession(Request $request, TrainingSession $session): RedirectResponse
    {
        if (in_array($session->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages(['session' => 'Sesi selesai atau dibatalkan tidak boleh diubah.']);
        }
        $validated = $this->validateSession($request, $session);
        $enrolled = $session->requests()->whereIn('status', ['approved', 'completed'])->count();
        if ($validated['capacity'] < $enrolled) {
            throw ValidationException::withMessages(['capacity' => "Kapasiti tidak boleh kurang daripada {$enrolled} peserta berdaftar."]);
        }
        $old = $session->only(['session_code', 'training_course_id', 'starts_at', 'ends_at', 'capacity', 'cost_per_participant', 'status']);
        $session->update([...$validated, 'session_code' => strtoupper($validated['session_code']), 'updated_by' => $request->user()->getAuthIdentifier()]);
        AuditLogger::record($request, 'training.session_updated', 'training_sessions', $session->getKey(), oldValues: $old, newValues: $session->only(array_keys($old)));

        return $this->success('Sesi latihan telah dikemas kini.');
    }

    public function changeSessionStatus(Request $request, TrainingSession $session): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'closed', 'completed', 'cancelled'])],
        ]);
        $allowed = match ($session->status) {
            'draft' => ['open', 'cancelled'],
            'open' => ['closed', 'cancelled'],
            'closed' => ['open', 'completed', 'cancelled'],
            default => [],
        };
        if (! in_array($validated['status'], $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'Perubahan status sesi ini tidak dibenarkan.']);
        }
        if ($validated['status'] === 'cancelled' && $session->requests()->where('status', 'approved')->exists()) {
            throw ValidationException::withMessages(['status' => 'Selesaikan pemindahan atau pembatalan peserta diluluskan terlebih dahulu.']);
        }
        $old = $session->status;
        $session->update(['status' => $validated['status'], 'updated_by' => $request->user()->getAuthIdentifier()]);
        AuditLogger::record($request, 'training.session_status_changed', 'training_sessions', $session->getKey(), oldValues: ['status' => $old], newValues: ['status' => $session->status]);

        return $this->success('Status sesi latihan telah dikemas kini.');
    }

    public function saveBudget(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'between:2020,2100'],
            'department_id' => ['nullable', 'integer'],
            'budget_code' => ['nullable', 'string', 'max:50'],
            'allocated_amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $budget = TrainingBudget::query()->firstOrNew([
            'year' => $validated['year'],
            'department_id' => $validated['department_id'] ?? null,
        ]);
        $old = $budget->exists ? $budget->only(['budget_code', 'allocated_amount', 'notes']) : [];
        $budget->fill([
            ...$validated,
            'created_by' => $budget->created_by ?? $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ])->save();
        AuditLogger::record($request, 'training.budget_saved', 'training_budgets', $budget->getKey(), oldValues: $old, newValues: $budget->only(['year', 'department_id', 'budget_code', 'allocated_amount']));

        return $this->success('Bajet latihan telah disimpan.');
    }

    public function storeCompetency(Request $request): RedirectResponse
    {
        $validated = $this->validateCompetency($request);
        $competency = Competency::query()->create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        $this->audit($request, 'competency.created', 'competencies', $competency->getKey(), $competency->only(['code', 'name', 'category', 'maximum_level']));

        return $this->success('Kompetensi telah ditambah ke kerangka organisasi.');
    }

    public function updateCompetency(Request $request, Competency $competency): RedirectResponse
    {
        $validated = $this->validateCompetency($request, $competency);
        if ($competency->requirements()->where('required_level', '>', $validated['maximum_level'])->exists()) {
            throw ValidationException::withMessages(['maximum_level' => 'Tahap maksimum tidak boleh lebih rendah daripada keperluan jawatan sedia ada.']);
        }
        $old = $competency->only(['code', 'name', 'category', 'description', 'maximum_level', 'level_descriptions', 'is_active']);
        $competency->update([...$validated, 'code' => strtoupper($validated['code']), 'updated_by' => $request->user()->getAuthIdentifier()]);
        AuditLogger::record($request, 'competency.updated', 'competencies', $competency->getKey(), oldValues: $old, newValues: $competency->only(array_keys($old)));

        return $this->success('Kompetensi telah dikemas kini.');
    }

    public function toggleCompetency(Request $request, Competency $competency): RedirectResponse
    {
        $competency->update(['is_active' => ! $competency->is_active, 'updated_by' => $request->user()->getAuthIdentifier()]);
        $this->audit($request, 'competency.status_changed', 'competencies', $competency->getKey(), ['is_active' => $competency->is_active]);

        return $this->success($competency->is_active ? 'Kompetensi diaktifkan.' : 'Kompetensi dinyahaktifkan.');
    }

    public function storeRequirement(Request $request): RedirectResponse
    {
        $validated = $this->validateRequirement($request);
        $this->assertUniqueRequirement($validated);
        $requirement = CompetencyRequirement::query()->create([
            ...$validated,
            'created_by' => $request->user()->getAuthIdentifier(),
        ]);
        $this->audit($request, 'competency.requirement_created', 'competency_requirements', $requirement->getKey(), $requirement->only(['competency_id', 'department_id', 'position_name', 'required_level', 'is_mandatory']));

        return $this->success('Keperluan kompetensi jawatan telah ditambah.');
    }

    public function updateRequirement(Request $request, CompetencyRequirement $requirement): RedirectResponse
    {
        $validated = $this->validateRequirement($request);
        $this->assertUniqueRequirement($validated, $requirement);
        $old = $requirement->only(['competency_id', 'department_id', 'position_name', 'required_level', 'is_mandatory', 'notes']);
        $requirement->update($validated);
        AuditLogger::record($request, 'competency.requirement_updated', 'competency_requirements', $requirement->getKey(), oldValues: $old, newValues: $requirement->only(array_keys($old)));

        return $this->success('Keperluan kompetensi telah dikemas kini.');
    }

    public function destroyRequirement(Request $request, CompetencyRequirement $requirement): RedirectResponse
    {
        $old = $requirement->only(['competency_id', 'department_id', 'position_name', 'required_level']);
        $id = $requirement->getKey();
        $requirement->delete();
        AuditLogger::record($request, 'competency.requirement_deleted', 'competency_requirements', $id, oldValues: $old);

        return $this->success('Keperluan kompetensi telah dipadam.');
    }

    public function saveAssignment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'integer'],
            'approver_user_id' => ['required', 'integer', 'exists:users,id'],
            'is_active' => ['required', 'boolean'],
        ]);
        $approver = User::query()->findOrFail($validated['approver_user_id']);
        if (! $approver->hasPermission('training.supervise')) {
            throw ValidationException::withMessages(['approver_user_id' => 'Pengguna ini tidak mempunyai kebenaran penyeliaan latihan.']);
        }
        $assignment = TrainingApprovalAssignment::query()->updateOrCreate(
            ['department_id' => $validated['department_id']],
            [
                'approver_user_id' => $validated['approver_user_id'],
                'is_active' => $validated['is_active'],
                'created_by' => $request->user()->getAuthIdentifier(),
            ],
        );
        $this->audit($request, 'training.approver_saved', 'training_approval_assignments', $assignment->getKey(), $assignment->only(['department_id', 'approver_user_id', 'is_active']));

        return $this->success('Penyelia kelulusan latihan telah ditetapkan.');
    }

    private function validateProvider(Request $request, ?TrainingProvider $provider = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('training_providers', 'code')->ignore($provider)],
            'name' => ['required', 'string', 'max:180'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'accreditation' => ['nullable', 'string', 'max:180'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function validateCourse(Request $request, ?TrainingCourse $course = null): array
    {
        return $request->validate([
            'training_provider_id' => ['nullable', 'integer', 'exists:training_providers,id'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('training_courses', 'code')->ignore($course)],
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', 'max:60'],
            'delivery_method' => ['required', Rule::in(['physical', 'online', 'hybrid', 'self_paced', 'on_the_job'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'learning_objectives' => ['nullable', 'string', 'max:5000'],
            'duration_hours' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'cpd_points' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'default_cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'certificate_validity_months' => ['nullable', 'integer', 'between:1,1200'],
            'is_mandatory' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function validateSession(Request $request, ?TrainingSession $session = null): array
    {
        return $request->validate([
            'training_course_id' => ['required', 'integer', 'exists:training_courses,id'],
            'session_code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('training_sessions', 'session_code')->ignore($session)],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'registration_deadline' => ['nullable', 'date', 'before_or_equal:starts_at'],
            'venue' => ['nullable', 'string', 'max:250'],
            'facilitator' => ['nullable', 'string', 'max:180'],
            'capacity' => ['required', 'integer', 'between:1,10000'],
            'cost_per_participant' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'budget_code' => ['nullable', 'string', 'max:50'],
            'status' => ['required', Rule::in(['draft', 'open', 'closed'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function validateCompetency(Request $request, ?Competency $competency = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('competencies', 'code')->ignore($competency)],
            'name' => ['required', 'string', 'max:180'],
            'category' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:5000'],
            'maximum_level' => ['required', 'integer', 'between:1,10'],
            'level_descriptions' => ['nullable', 'array', 'max:10'],
            'level_descriptions.*' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function validateRequirement(Request $request): array
    {
        $validated = $request->validate([
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'department_id' => ['nullable', 'integer'],
            'position_name' => ['nullable', 'string', 'max:150'],
            'required_level' => ['required', 'integer', 'between:1,10'],
            'is_mandatory' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $competency = Competency::query()->findOrFail($validated['competency_id']);
        if ($validated['required_level'] > $competency->maximum_level) {
            throw ValidationException::withMessages(['required_level' => "Tahap maksimum kompetensi ini ialah {$competency->maximum_level}."]);
        }

        return $validated;
    }

    private function assertUniqueRequirement(array $validated, ?CompetencyRequirement $ignore = null): void
    {
        $exists = CompetencyRequirement::query()
            ->where('competency_id', $validated['competency_id'])
            ->where('department_id', $validated['department_id'] ?? null)
            ->where('position_name', $validated['position_name'] ?? null)
            ->when($ignore, fn ($query) => $query->where('id', '<>', $ignore->getKey()))
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages(['competency_id' => 'Keperluan kompetensi bagi skop ini telah wujud.']);
        }
    }

    private function audit(Request $request, string $action, string $type, int $id, array $values): void
    {
        AuditLogger::record($request, $action, $type, $id, newValues: $values);
    }

    private function success(string $message): RedirectResponse
    {
        return back()->with('toast', ['type' => 'success', 'message' => $message]);
    }
}
