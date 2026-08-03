<?php

namespace App\Http\Controllers;

use App\Models\Competency;
use App\Models\CompetencyRequirement;
use App\Models\DevelopmentPlan;
use App\Models\EmployeeCompetency;
use App\Models\EmployeeRecord;
use App\Models\TrainingApprovalAssignment;
use App\Models\TrainingAttachment;
use App\Models\TrainingBudget;
use App\Models\TrainingCourse;
use App\Models\TrainingNotification;
use App\Models\TrainingRequest;
use App\Models\TrainingSession;
use App\Support\AuditLogger;
use App\Support\TrainingBudgetManager;
use App\Support\TrainingEmployeeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingController extends Controller
{
    public function __construct(
        private readonly TrainingEmployeeResolver $employees,
        private readonly TrainingBudgetManager $budgets,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(TrainingRequest::STATUSES)],
            'department_id' => ['nullable', 'integer'],
            'year' => ['nullable', 'integer', 'between:2020,2100'],
        ]);
        $year = (int) ($filters['year'] ?? now()->year);
        $query = $this->visibleQuery($request)
            ->when(! empty($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['department_id']), fn (Builder $query) => $query->where('department_id', $filters['department_id']))
            ->when(! empty($filters['search']), function (Builder $query) use ($filters) {
                $search = trim($filters['search']);
                $employeeIds = DB::connection('ibco')
                    ->table('maklumatpekerja')
                    ->where('rcd_enable', 1)
                    ->where(fn ($query) => $query
                        ->where('nama', 'like', "%{$search}%")
                        ->orWhere('employeeID', 'like', "%{$search}%"))
                    ->pluck('id')
                    ->concat(
                        EmployeeRecord::query()
                            ->whereIn('status', ['pending_activation', 'active'])
                            ->where(fn ($query) => $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('employee_number', 'like', "%{$search}%"))
                            ->pluck('directory_id'),
                    );
                $query->where(fn (Builder $query) => $query
                    ->where('request_number', 'like', "%{$search}%")
                    ->orWhere('course_title', 'like', "%{$search}%")
                    ->orWhereIn('employee_id', $employeeIds));
            });
        $requests = $query
            ->with([
                'employeeUser:id,name,email',
                'session.course.provider:id,name',
                'developmentPlan.competency:id,code,name',
                'supervisor:id,name,email',
                'hrReviewer:id,name,email',
                'attachments:id,training_request_id,attachment_type,original_name,mime_type,size,valid_until',
            ])
            ->latest()
            ->paginate(20)
            ->withQueryString();
        $employeeMap = $this->employees
            ->forEmployees(collect($requests->items())->pluck('employee_id')->all())
            ->keyBy('id');
        $departments = DB::connection('ibco')
            ->table('xdepartment')
            ->where('rcd_enable', 1)
            ->orderBy('description')
            ->get(['id', 'description as name']);
        $departmentMap = $departments->pluck('name', 'id');
        $requests->through(fn (TrainingRequest $training) => $this->payload(
            $training,
            $employeeMap[$training->employee_id] ?? null,
            $departmentMap[$training->department_id] ?? null,
        ));
        $summaryBase = $this->visibleQuery($request)->where('budget_year', $year);
        $statusCounts = (clone $summaryBase)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $budgetRows = TrainingBudget::query()->where('year', $year)->orderBy('department_id')->get();

        return Inertia::render('Training/Index', [
            'requests' => $requests,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'department_id' => isset($filters['department_id']) ? (string) $filters['department_id'] : '',
                'year' => (string) $year,
            ],
            'statistics' => [
                'total' => (int) $statusCounts->sum(),
                'pending_supervisor' => (int) (clone $summaryBase)->where('status', 'pending')->where('approval_stage', 'supervisor')->count(),
                'pending_hr' => (int) (clone $summaryBase)->where('status', 'pending')->where('approval_stage', 'hr')->count(),
                'approved' => (int) ($statusCounts['approved'] ?? 0),
                'completed' => (int) ($statusCounts['completed'] ?? 0),
                'total_spent' => round((float) (clone $summaryBase)
                    ->whereIn('status', ['approved', 'completed'])
                    ->sum('approved_cost'), 2),
                'total_hours' => round((float) (clone $summaryBase)
                    ->where('status', 'completed')
                    ->sum('attended_hours'), 2),
            ],
            'budgets' => $budgetRows->map(function (TrainingBudget $budget) use ($departmentMap, $year) {
                $summary = $this->budgets->summary($year, $budget->department_id);

                return [
                    'id' => $budget->getKey(),
                    'department_id' => $budget->department_id,
                    'department' => $budget->department_id
                        ? ($departmentMap[$budget->department_id] ?? 'Jabatan #'.$budget->department_id)
                        : 'Bajet Umum',
                    'budget_code' => $budget->budget_code,
                    ...$summary,
                ];
            }),
            'departments' => $departments,
            'sessions' => TrainingSession::query()
                ->where('status', 'open')
                ->where('starts_at', '>=', now())
                ->with('course:id,title')
                ->orderBy('starts_at')
                ->get()
                ->map(fn (TrainingSession $session) => [
                    'id' => $session->getKey(),
                    'title' => $session->course?->title,
                    'session_code' => $session->session_code,
                    'starts_at' => $session->starts_at?->toIso8601String(),
                    'cost' => (float) $session->cost_per_participant,
                ]),
            'employees' => $this->employees->linkedOptions(),
            'competencies' => Competency::query()->where('is_active', true)->orderBy('name')->get([
                'id', 'code', 'name', 'category', 'maximum_level',
            ]),
            'competencyMatrix' => $this->competencyMatrix(),
            'developmentPlans' => DevelopmentPlan::query()
                ->with(['employeeUser:id,name,email', 'competency:id,code,name'])
                ->latest('due_date')
                ->limit(100)
                ->get()
                ->map(fn (DevelopmentPlan $plan) => [
                    'id' => $plan->getKey(),
                    'employee_user_id' => $plan->employee_user_id,
                    'employee' => $plan->employeeUser?->name,
                    'competency' => $plan->competency?->name,
                    'source' => $plan->source,
                    'title' => $plan->title,
                    'action_plan' => $plan->action_plan,
                    'target_level' => $plan->target_level,
                    'due_date' => $plan->due_date?->toDateString(),
                    'status' => $plan->status,
                ]),
            'permissions' => [
                'can_manage' => $request->user()->hasPermission('training.manage'),
                'can_approve' => $request->user()->hasPermission('training.approve'),
                'can_supervise' => $request->user()->hasPermission('training.supervise'),
                'can_assess' => $request->user()->hasPermission('competency.assess'),
            ],
        ]);
    }

    public function supervisorReview(Request $request, TrainingRequest $training): RedirectResponse
    {
        abort_unless($this->canSupervise($request, $training), 403);
        if ($training->status !== 'pending' || $training->approval_stage !== 'supervisor') {
            throw ValidationException::withMessages([
                'status' => 'Permohonan ini tidak lagi menunggu sokongan penyelia.',
            ]);
        }
        $validated = $request->validate([
            'action' => ['required', Rule::in(['support', 'reject'])],
            'notes' => ['required', 'string', 'max:5000'],
        ]);
        $supported = $validated['action'] === 'support';
        $training->update([
            'status' => $supported ? 'pending' : 'rejected',
            'approval_stage' => $supported ? 'hr' : null,
            'supervisor_user_id' => $request->user()->getAuthIdentifier(),
            'supervisor_notes' => $validated['notes'],
            'supervisor_reviewed_at' => now(),
        ]);
        $this->notifyEmployee(
            $training,
            $supported ? 'supervisor_supported' : 'supervisor_rejected',
            $supported ? 'Permohonan latihan disokong' : 'Permohonan latihan ditolak',
            $supported
                ? 'Permohonan anda telah disokong dan dihantar kepada Pengurus HR.'
                : 'Permohonan anda ditolak oleh penyelia. Semak catatan untuk maklumat lanjut.',
        );
        AuditLogger::record(
            $request,
            $supported ? 'training.supervisor_supported' : 'training.supervisor_rejected',
            'training_requests',
            $training->getKey(),
            oldValues: ['status' => 'pending', 'approval_stage' => 'supervisor'],
            newValues: ['status' => $training->status, 'approval_stage' => $training->approval_stage],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $supported ? 'Permohonan disokong dan dihantar kepada Pengurus HR.' : 'Permohonan ditolak.',
        ]);
    }

    public function review(Request $request, TrainingRequest $training): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('training.approve'), 403);

        if ($training->status !== 'pending' || $training->approval_stage !== 'hr') {
            throw ValidationException::withMessages([
                'status' => 'Permohonan ini tidak lagi menunggu keputusan HR.',
            ]);
        }

        if (in_array((int) $request->user()->getAuthIdentifier(), [
            (int) $training->employee_user_id,
            (int) $training->created_by,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Pengguna yang memohon atau menyediakan pencalonan tidak boleh meluluskan latihan yang sama.',
            ]);
        }
        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'approved_cost' => [Rule::requiredIf($request->input('action') === 'approve'), 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'notes' => ['required', 'string', 'max:5000'],
        ]);
        $approved = $validated['action'] === 'approve';

        DB::transaction(function () use ($request, $training, $validated, $approved) {
            if ($approved) {
                $this->assertSessionCapacity($training);
                $year = $training->session?->starts_at?->year ?? now()->year;
                $this->budgets->assertAvailable(
                    $year,
                    $training->department_id,
                    (float) $validated['approved_cost'],
                );
            }
            $training->update([
                'status' => $approved ? 'approved' : 'rejected',
                'approval_stage' => null,
                'approved_cost' => $approved ? $validated['approved_cost'] : null,
                'hr_user_id' => $request->user()->getAuthIdentifier(),
                'hr_notes' => $validated['notes'],
                'hr_reviewed_at' => now(),
            ]);
        });
        $this->notifyEmployee(
            $training,
            $approved ? 'approved' : 'rejected',
            $approved ? 'Latihan diluluskan' : 'Permohonan latihan ditolak',
            $approved
                ? "Permohonan {$training->request_number} telah diluluskan."
                : "Permohonan {$training->request_number} tidak diluluskan oleh HR.",
        );
        AuditLogger::record(
            $request,
            $approved ? 'training.hr_approved' : 'training.hr_rejected',
            'training_requests',
            $training->getKey(),
            oldValues: ['status' => 'pending', 'approval_stage' => 'hr'],
            newValues: [
                'status' => $training->status,
                'approved_cost' => $training->approved_cost,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $approved ? 'Permohonan latihan diluluskan.' : 'Permohonan latihan ditolak.',
        ]);
    }

    public function recordCompletion(Request $request, TrainingRequest $training): RedirectResponse
    {
        if ($training->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Kehadiran hanya boleh direkod bagi latihan yang telah diluluskan.',
            ]);
        }
        $validated = $request->validate([
            'attendance_status' => ['required', Rule::in(['attended', 'passed', 'failed', 'no_show'])],
            'attended_hours' => ['required_unless:attendance_status,no_show', 'nullable', 'numeric', 'min:0', 'max:9999.99'],
            'assessment_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $passed = match ($validated['attendance_status']) {
            'passed' => true,
            'failed', 'no_show' => false,
            default => null,
        };
        DB::transaction(function () use ($request, $training, $validated, $passed) {
            $training->update([
                'status' => 'completed',
                'attendance_status' => $validated['attendance_status'],
                'attended_hours' => $validated['attendance_status'] === 'no_show'
                    ? 0
                    : $validated['attended_hours'],
                'assessment_score' => $validated['assessment_score'] ?? null,
                'passed' => $passed,
                'hr_notes' => $validated['notes'] ?? $training->hr_notes,
                'completed_at' => now(),
            ]);
            if (
                $training->developmentPlan
                && in_array($validated['attendance_status'], ['attended', 'passed'], true)
            ) {
                $training->developmentPlan->update([
                    'status' => 'completed',
                    'completion_notes' => 'Diselesaikan melalui latihan '.$training->course_title.'.',
                    'updated_by' => $request->user()->getAuthIdentifier(),
                    'completed_at' => now(),
                ]);
            }
        });
        $this->notifyEmployee(
            $training,
            'completion_recorded',
            'Rekod latihan dikemas kini',
            "Kehadiran dan keputusan bagi {$training->course_title} telah direkodkan.",
        );
        AuditLogger::record(
            $request,
            'training.completion_recorded',
            'training_requests',
            $training->getKey(),
            oldValues: ['status' => 'approved'],
            newValues: [
                'status' => 'completed',
                'attendance_status' => $training->attendance_status,
                'attended_hours' => $training->attended_hours,
                'passed' => $training->passed,
            ],
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Kehadiran dan keputusan latihan telah direkodkan.']);
    }

    public function nominate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_user_id' => ['required', 'integer', 'exists:users,id'],
            'training_session_id' => ['required', 'integer', 'exists:training_sessions,id'],
            'development_plan_id' => ['nullable', 'integer', 'exists:development_plans,id'],
            'development_source' => ['required', Rule::in(['kpi', 'pip', 'onboarding', 'mandatory', 'competency_gap'])],
            'justification' => ['required', 'string', 'max:5000'],
        ]);
        $employee = $this->employees->forUser((int) $validated['employee_user_id']);
        if (! $employee) {
            throw ValidationException::withMessages(['employee_user_id' => 'Akaun pekerja belum dipautkan kepada rekod aktif.']);
        }
        if (isset($validated['development_plan_id'])) {
            $planBelongsToEmployee = DevelopmentPlan::query()
                ->whereKey($validated['development_plan_id'])
                ->where('employee_user_id', $validated['employee_user_id'])
                ->exists();
            if (! $planBelongsToEmployee) {
                throw ValidationException::withMessages([
                    'development_plan_id' => 'Pelan pembangunan tidak sepadan dengan pekerja yang dipilih.',
                ]);
            }
        }
        $training = DB::transaction(function () use ($request, $validated, $employee) {
            $session = TrainingSession::query()
                ->with('course')
                ->lockForUpdate()
                ->findOrFail($validated['training_session_id']);
            if ($session->status !== 'open') {
                throw ValidationException::withMessages(['training_session_id' => 'Sesi latihan tidak dibuka.']);
            }
            if (TrainingRequest::query()
                ->where('employee_user_id', $validated['employee_user_id'])
                ->where('training_session_id', $session->getKey())
                ->whereIn('status', ['pending', 'approved', 'completed'])
                ->exists()) {
                throw ValidationException::withMessages([
                    'training_session_id' => 'Pekerja ini telah mempunyai pendaftaran aktif bagi sesi tersebut.',
                ]);
            }
            $this->assertSessionCapacityFor($session);
            $this->budgets->assertAvailable(
                $session->starts_at?->year ?? now()->year,
                $employee->department_id,
                (float) $session->cost_per_participant,
            );

            return TrainingRequest::query()->create([
                'request_number' => $this->requestNumber(),
                'employee_user_id' => $validated['employee_user_id'],
                'employee_id' => $employee->id,
                'department_id' => $employee->department_id,
                'budget_year' => $session->starts_at?->year ?? now()->year,
                'position_name' => $employee->position_name,
                'training_session_id' => $session->getKey(),
                'development_plan_id' => $validated['development_plan_id'] ?? null,
                'course_title' => $session->course?->title,
                'justification' => $validated['justification'],
                'development_source' => $validated['development_source'],
                'estimated_cost' => $session->cost_per_participant,
                'approved_cost' => null,
                'status' => 'pending',
                'approval_stage' => 'hr',
                'hr_user_id' => null,
                'hr_notes' => null,
                'hr_reviewed_at' => null,
                'created_by' => $request->user()->getAuthIdentifier(),
            ]);
        });
        $this->notifyEmployee(
            $training,
            'nominated',
            'Pencalonan latihan menunggu kelulusan',
            "Anda telah dicalonkan untuk {$training->course_title} dan permohonan sedang menunggu kelulusan Pengurus HR.",
        );
        AuditLogger::record(
            $request,
            'training.employee_nominated',
            'training_requests',
            $training->getKey(),
            newValues: $training->only([
                'employee_id', 'course_title', 'estimated_cost',
                'status', 'approval_stage', 'development_source',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Pencalonan telah dihantar kepada Pengurus HR untuk kelulusan.',
        ]);
    }

    public function saveCompetency(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_user_id' => ['required', 'integer', 'exists:users,id'],
            'competency_id' => ['required', 'integer', 'exists:competencies,id'],
            'current_level' => ['required', 'integer', 'min:0', 'max:10'],
            'assessment_source' => ['required', Rule::in(['manager', 'assessment', 'certificate', 'training', 'self_verified'])],
            'evidence_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $employee = $this->employees->forUser((int) $validated['employee_user_id']);
        $competency = Competency::query()->findOrFail($validated['competency_id']);
        if (! $employee) {
            throw ValidationException::withMessages(['employee_user_id' => 'Akaun pekerja belum dipautkan.']);
        }
        if ($validated['current_level'] > $competency->maximum_level) {
            throw ValidationException::withMessages([
                'current_level' => "Tahap maksimum kompetensi ini ialah {$competency->maximum_level}.",
            ]);
        }
        $record = EmployeeCompetency::query()->updateOrCreate(
            [
                'employee_user_id' => $validated['employee_user_id'],
                'competency_id' => $validated['competency_id'],
            ],
            [
                'employee_id' => $employee->id,
                'current_level' => $validated['current_level'],
                'assessment_source' => $validated['assessment_source'],
                'evidence_notes' => $validated['evidence_notes'] ?? null,
                'assessed_by' => $request->user()->getAuthIdentifier(),
                'assessed_at' => now(),
            ],
        );
        AuditLogger::record(
            $request,
            'competency.employee_assessed',
            'employee_competencies',
            $record->getKey(),
            newValues: $record->only(['employee_id', 'competency_id', 'current_level', 'assessment_source']),
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Tahap kompetensi pekerja telah dikemas kini.']);
    }

    public function storeDevelopmentPlan(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_user_id' => ['required', 'integer', 'exists:users,id'],
            'competency_id' => ['nullable', 'integer', 'exists:competencies,id'],
            'performance_review_id' => ['nullable', 'integer', 'exists:performance_reviews,id'],
            'performance_improvement_plan_id' => ['nullable', 'integer', 'exists:performance_improvement_plans,id'],
            'source' => ['required', Rule::in(['competency_gap', 'kpi', 'pip', 'onboarding', 'career'])],
            'title' => ['required', 'string', 'max:200'],
            'action_plan' => ['required', 'string', 'max:5000'],
            'target_level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
        ]);
        $employee = $this->employees->forUser((int) $validated['employee_user_id']);
        if (! $employee) {
            throw ValidationException::withMessages(['employee_user_id' => 'Akaun pekerja belum dipautkan.']);
        }
        $plan = DevelopmentPlan::query()->create([
            ...$validated,
            'employee_id' => $employee->id,
            'status' => 'planned',
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        TrainingNotification::query()->create([
            'user_id' => $validated['employee_user_id'],
            'type' => 'development_plan_created',
            'title' => 'Pelan pembangunan baharu',
            'message' => "Pelan {$plan->title} telah ditetapkan sehingga {$plan->due_date->format('d/m/Y')}.",
        ]);
        AuditLogger::record(
            $request,
            'development_plan.created',
            'development_plans',
            $plan->getKey(),
            newValues: $plan->only(['employee_id', 'competency_id', 'source', 'title', 'target_level', 'due_date']),
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Pelan pembangunan pekerja telah ditambah.']);
    }

    public function updateDevelopmentPlan(
        Request $request,
        DevelopmentPlan $plan,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['planned', 'in_progress', 'completed', 'cancelled'])],
            'completion_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $old = $plan->only(['status', 'completion_notes']);
        $plan->update([
            ...$validated,
            'updated_by' => $request->user()->getAuthIdentifier(),
            'completed_at' => $validated['status'] === 'completed' ? now() : null,
        ]);
        AuditLogger::record(
            $request,
            'development_plan.updated',
            'development_plans',
            $plan->getKey(),
            oldValues: $old,
            newValues: $plan->only(['status', 'completion_notes']),
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Pelan pembangunan telah dikemas kini.']);
    }

    public function downloadAttachment(
        Request $request,
        TrainingRequest $training,
        TrainingAttachment $attachment,
    ): HttpResponse {
        abort_unless($this->visibleQuery($request)->whereKey($training)->exists(), 403);
        abort_unless((int) $attachment->training_request_id === (int) $training->getKey(), 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function export(Request $request): StreamedResponse
    {
        $requests = $this->visibleQuery($request)
            ->with('session.course.provider:id,name')
            ->orderBy('created_at')
            ->get();
        $employeeMap = $this->employees->forEmployees($requests->pluck('employee_id')->all())->keyBy('id');
        AuditLogger::record(
            $request,
            'training.report_exported',
            'training_requests',
            'csv',
            newValues: ['records' => $requests->count()],
        );

        return response()->streamDownload(function () use ($requests, $employeeMap) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, [
                'No. Permohonan', 'No. Pekerja', 'Nama', 'Jabatan', 'Kursus',
                'Tarikh Sesi', 'Status', 'Sumber', 'Kos Diluluskan', 'Jam',
                'Keputusan', 'Rating',
            ]);
            foreach ($requests as $training) {
                $employee = $employeeMap[$training->employee_id] ?? null;
                fputcsv($stream, [
                    $training->request_number,
                    $employee?->employee_number,
                    $employee?->name,
                    $employee?->department_name,
                    $training->course_title,
                    $training->session?->starts_at?->format('Y-m-d H:i'),
                    $training->status,
                    $training->development_source,
                    $training->approved_cost,
                    $training->attended_hours,
                    $training->attendance_status,
                    $training->employee_rating,
                ]);
            }
            fclose($stream);
        }, 'laporan-latihan-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function visibleQuery(Request $request): Builder
    {
        $query = TrainingRequest::query();
        if ($request->user()->hasPermission('training.manage')
            || $request->user()->hasPermission('training.approve')
            || ($request->user()->hasPermission('training.view')
                && ! $request->user()->hasPermission('training.supervise'))) {
            return $query;
        }
        $departmentIds = TrainingApprovalAssignment::query()
            ->where('approver_user_id', $request->user()->getAuthIdentifier())
            ->where('is_active', true)
            ->pluck('department_id');

        return $query->where(fn (Builder $query) => $query
            ->whereIn('department_id', $departmentIds)
            ->orWhere('supervisor_user_id', $request->user()->getAuthIdentifier()));
    }

    private function canSupervise(Request $request, TrainingRequest $training): bool
    {
        if ($request->user()->hasPermission('training.manage')) {
            return true;
        }
        if (! $request->user()->hasPermission('training.supervise')) {
            return false;
        }

        return (int) $training->supervisor_user_id === (int) $request->user()->getAuthIdentifier()
            || TrainingApprovalAssignment::query()
                ->where('department_id', $training->department_id)
                ->where('approver_user_id', $request->user()->getAuthIdentifier())
                ->where('is_active', true)
                ->exists();
    }

    private function assertSessionCapacity(TrainingRequest $training): void
    {
        if (! $training->training_session_id) {
            return;
        }
        $session = TrainingSession::query()->lockForUpdate()->findOrFail($training->training_session_id);
        $this->assertSessionCapacityFor($session, $training->getKey());
    }

    private function assertSessionCapacityFor(
        TrainingSession $session,
        ?int $ignoreRequestId = null,
    ): void {
        $enrolled = TrainingRequest::query()
            ->where('training_session_id', $session->getKey())
            ->whereIn('status', ['approved', 'completed'])
            ->when($ignoreRequestId, fn ($query) => $query->where('id', '<>', $ignoreRequestId))
            ->count();
        if ($enrolled >= $session->capacity) {
            throw ValidationException::withMessages([
                'training_session_id' => 'Kapasiti sesi latihan telah penuh.',
            ]);
        }
    }

    private function competencyMatrix(): Collection
    {
        $employees = $this->employees->linkedOptions();
        $levels = EmployeeCompetency::query()
            ->with('competency:id,code,name,category,maximum_level')
            ->get()
            ->groupBy('employee_user_id');
        $requirements = CompetencyRequirement::query()
            ->with('competency:id,code,name,category,maximum_level')
            ->get();

        return $employees->map(function (array $employee) use ($levels, $requirements) {
            $employeeLevels = ($levels[$employee['user_id']] ?? collect())->keyBy('competency_id');
            $scoped = $requirements->filter(fn (CompetencyRequirement $requirement) =>
                ($requirement->department_id === null
                    || (int) $requirement->department_id === (int) $employee['department_id'])
                && ($requirement->position_name === null
                    || $requirement->position_name === $employee['position_name']));
            $skills = $scoped->map(function (CompetencyRequirement $requirement) use ($employeeLevels) {
                $current = (int) ($employeeLevels[$requirement->competency_id]?->current_level ?? 0);

                return [
                    'competency_id' => $requirement->competency_id,
                    'code' => $requirement->competency?->code,
                    'name' => $requirement->competency?->name,
                    'current_level' => $current,
                    'required_level' => $requirement->required_level,
                    'gap' => max(0, $requirement->required_level - $current),
                    'is_mandatory' => $requirement->is_mandatory,
                ];
            })->values();

            return [
                ...$employee,
                'skills' => $skills,
                'gap_count' => $skills->where('gap', '>', 0)->count(),
            ];
        })->sortByDesc('gap_count')->values();
    }

    private function payload(
        TrainingRequest $training,
        ?object $employee,
        ?string $department,
    ): array {
        return [
            'id' => $training->getKey(),
            'request_number' => $training->request_number,
            'employee' => $employee ? [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->name,
            ] : null,
            'employee_user_id' => $training->employee_user_id,
            'department_id' => $training->department_id,
            'department' => $department,
            'position_name' => $training->position_name,
            'course_title' => $training->course_title,
            'session' => $training->session ? [
                'session_code' => $training->session->session_code,
                'starts_at' => $training->session->starts_at?->toIso8601String(),
                'ends_at' => $training->session->ends_at?->toIso8601String(),
                'venue' => $training->session->venue,
                'provider' => $training->session->course?->provider?->name,
            ] : null,
            'development_source' => $training->development_source,
            'development_plan' => $training->developmentPlan?->title,
            'justification' => $training->justification,
            'estimated_cost' => (float) $training->estimated_cost,
            'approved_cost' => $training->approved_cost === null ? null : (float) $training->approved_cost,
            'status' => $training->status,
            'approval_stage' => $training->approval_stage,
            'supervisor' => $training->supervisor?->name,
            'supervisor_notes' => $training->supervisor_notes,
            'hr_reviewer' => $training->hrReviewer?->name,
            'hr_notes' => $training->hr_notes,
            'attendance_status' => $training->attendance_status,
            'attended_hours' => $training->attended_hours === null ? null : (float) $training->attended_hours,
            'assessment_score' => $training->assessment_score === null ? null : (float) $training->assessment_score,
            'passed' => $training->passed,
            'employee_rating' => $training->employee_rating,
            'created_at' => $training->created_at?->toIso8601String(),
            'attachments' => $training->attachments->map(fn (TrainingAttachment $attachment) => [
                'id' => $attachment->getKey(),
                'type' => $attachment->attachment_type,
                'name' => $attachment->original_name,
                'valid_until' => $attachment->valid_until?->toDateString(),
            ]),
        ];
    }

    private function notifyEmployee(
        TrainingRequest $training,
        string $type,
        string $title,
        string $message,
    ): void {
        TrainingNotification::query()->create([
            'user_id' => $training->employee_user_id,
            'training_request_id' => $training->getKey(),
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);
    }

    private function requestNumber(): string
    {
        do {
            $number = 'TRN-'.now()->format('Ymd').'-'.strtoupper(str()->random(6));
        } while (TrainingRequest::query()->where('request_number', $number)->exists());

        return $number;
    }
}
