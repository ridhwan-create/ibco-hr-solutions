<?php

namespace App\Http\Controllers;

use App\Models\EmployeeUserLink;
use App\Models\EmployeeRecord;
use App\Models\PerformanceCycle;
use App\Models\PerformanceEvidence;
use App\Models\PerformanceImprovementPlan;
use App\Models\PerformanceNotification;
use App\Models\PerformanceReview;
use App\Models\PerformanceSupervisorAssignment;
use App\Models\PerformanceTemplate;
use App\Support\AuditLogger;
use App\Support\PerformancePdfRenderer;
use App\Support\PerformanceScoreCalculator;
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

class PerformanceReviewController extends Controller
{
    public function __construct(
        private readonly PerformanceScoreCalculator $scoreCalculator,
        private readonly PerformancePdfRenderer $pdfRenderer,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'cycle_id' => ['nullable', 'integer', 'exists:performance_cycles,id'],
            'status' => ['nullable', Rule::in(PerformanceReview::STATUSES)],
            'department_id' => ['nullable', 'integer'],
        ]);
        $search = trim($filters['search'] ?? '');
        $cycleId = isset($filters['cycle_id'])
            ? (int) $filters['cycle_id']
            : PerformanceCycle::query()
                ->whereIn('status', ['open', 'in_review'])
                ->latest('period_start')
                ->value('id');
        $employeeIds = $search !== ''
            ? DB::connection('ibco')
                ->table('maklumatpekerja')
                ->where('rcd_enable', 1)
                ->where(function ($query) use ($search) {
                    $query->where('nama', 'like', "%{$search}%")
                        ->orWhere('employeeID', 'like', "%{$search}%");
                })
                ->pluck('id')
                ->concat(
                    EmployeeRecord::query()
                        ->whereIn('status', ['pending_activation', 'active'])
                        ->where(function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhere('employee_number', 'like', "%{$search}%");
                        })
                        ->pluck('directory_id'),
                )
            : collect();
        $visibleBase = $this->visibleQuery($request);
        $query = (clone $visibleBase)
            ->when($cycleId, fn (Builder $query) => $query->where('performance_cycle_id', $cycleId))
            ->when(
                ! empty($filters['status']),
                fn (Builder $query) => $query->where('status', $filters['status']),
            )
            ->when(
                ! empty($filters['department_id']),
                fn (Builder $query) => $query->where('department_id', $filters['department_id']),
            )
            ->when($search !== '', fn (Builder $query) => $query
                ->whereIn('employee_id', $employeeIds));
        $reviews = $query
            ->with([
                'cycle',
                'template:id,name',
                'supervisor:id,name,email',
                'goals',
                'evidence:id,performance_review_id,performance_goal_id,original_name,mime_type,size,description',
                'improvementPlan.checkins',
            ])
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();
        $employeeMap = $this->employeeMap(
            collect($reviews->items())->pluck('employee_id')->all(),
        );
        $departments = $this->departments();
        $departmentMap = $departments->pluck('name', 'id');
        $reviews->through(
            fn (PerformanceReview $review) => $this->reviewPayload(
                $review,
                $employeeMap,
                $departmentMap,
            ),
        );
        $summaryQuery = (clone $visibleBase)
            ->when($cycleId, fn (Builder $query) => $query->where('performance_cycle_id', $cycleId));
        $statusCounts = (clone $summaryQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $departmentPerformance = (clone $summaryQuery)
            ->selectRaw(
                "department_id, COUNT(*) as total, "
                ."SUM(CASE WHEN status = 'finalized' THEN 1 ELSE 0 END) as finalized, "
                ."AVG(CASE WHEN status = 'finalized' THEN moderated_score END) as average_score",
            )
            ->groupBy('department_id')
            ->get()
            ->map(fn ($row) => [
                'department_id' => $row->department_id,
                'department' => $departmentMap[$row->department_id] ?? 'Tanpa Jabatan',
                'total' => (int) $row->total,
                'finalized' => (int) $row->finalized,
                'average_score' => $row->average_score === null
                    ? null
                    : round((float) $row->average_score, 2),
            ]);

        return Inertia::render('PerformanceReviews/Index', [
            'reviews' => $reviews,
            'filters' => [
                'search' => $search,
                'cycle_id' => $cycleId ? (string) $cycleId : '',
                'status' => $filters['status'] ?? '',
                'department_id' => isset($filters['department_id'])
                    ? (string) $filters['department_id']
                    : '',
            ],
            'statistics' => [
                'total' => (int) $statusCounts->sum(),
                'self_pending' => (int) (
                    ($statusCounts['goal_setting'] ?? 0)
                    + ($statusCounts['self_assessment'] ?? 0)
                ),
                'supervisor_pending' => (int) ($statusCounts['supervisor_assessment'] ?? 0),
                'hr_pending' => (int) ($statusCounts['hr_moderation'] ?? 0),
                'finalized' => (int) ($statusCounts['finalized'] ?? 0),
                'average_score' => round((float) (clone $summaryQuery)
                    ->where('status', 'finalized')
                    ->avg('moderated_score'), 2),
                'active_pips' => PerformanceImprovementPlan::query()
                    ->whereIn('status', ['active', 'extended'])
                    ->whereIn('performance_review_id', (clone $summaryQuery)->select('id'))
                    ->count(),
            ],
            'cycles' => PerformanceCycle::query()
                ->latest('period_start')
                ->get(['id', 'name', 'status', 'period_start', 'period_end']),
            'departments' => $departments,
            'departmentPerformance' => $departmentPerformance,
            'templates' => PerformanceTemplate::query()
                ->where('is_active', true)
                ->with('items:id,performance_template_id,weight')
                ->orderBy('name')
                ->get()
                ->map(fn (PerformanceTemplate $template) => [
                    'id' => $template->getKey(),
                    'name' => $template->name,
                    'department_id' => $template->department_id,
                    'position_name' => $template->position_name,
                    'total_weight' => round((float) $template->items->sum('weight'), 2),
                ]),
            'employees' => $this->linkedEmployeeOptions(),
            'supervisors' => $this->supervisorOptions(),
            'permissions' => [
                'can_manage' => $request->user()->hasPermission('performance.manage'),
                'can_supervise' => $request->user()->hasPermission('performance.supervise'),
                'can_moderate' => $request->user()->hasPermission('performance.moderate'),
                'can_finalize' => $request->user()->hasPermission('performance.finalize'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'performance_cycle_id' => ['required', 'integer', 'exists:performance_cycles,id'],
            'employee_id' => ['required', 'integer'],
            'performance_template_id' => ['required', 'integer', 'exists:performance_templates,id'],
            'supervisor_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $cycle = PerformanceCycle::query()->findOrFail($validated['performance_cycle_id']);

        if (! in_array($cycle->status, ['draft', 'open'], true)) {
            throw ValidationException::withMessages([
                'performance_cycle_id' => 'Penilaian hanya boleh dijana untuk kitaran Draf atau Dibuka.',
            ]);
        }

        $template = PerformanceTemplate::query()
            ->whereKey($validated['performance_template_id'])
            ->where('is_active', true)
            ->with('items')
            ->firstOrFail();
        $employee = $this->employeeSnapshot((int) $validated['employee_id']);

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee_id' => 'Pekerja aktif tidak dijumpai dalam rekod asal.',
            ]);
        }

        if (
            PerformanceReview::query()
                ->where('performance_cycle_id', $cycle->getKey())
                ->where('employee_id', $employee->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'employee_id' => 'Penilaian pekerja ini telah wujud bagi kitaran yang dipilih.',
            ]);
        }

        $supervisorId = $validated['supervisor_user_id']
            ?? PerformanceSupervisorAssignment::query()
                ->where('department_id', $employee->department_id)
                ->where('is_active', true)
                ->value('supervisor_user_id');
        $review = $this->createReview(
            $request,
            $cycle,
            $template,
            $employee,
            $supervisorId ? (int) $supervisorId : null,
        );

        AuditLogger::record(
            $request,
            'performance_review.created',
            'performance_reviews',
            $review->getKey(),
            newValues: [
                'cycle_id' => $cycle->getKey(),
                'employee_id' => $review->employee_id,
                'template_id' => $template->getKey(),
                'supervisor_user_id' => $review->supervisor_user_id,
                'status' => $review->status,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Penilaian pekerja telah dijana daripada template KPI.',
        ]);
    }

    public function generateCycle(
        Request $request,
        PerformanceCycle $cycle,
    ): RedirectResponse {
        if (! in_array($cycle->status, ['draft', 'open'], true)) {
            throw ValidationException::withMessages([
                'cycle' => 'Penilaian pukal hanya boleh dijana untuk kitaran Draf atau Dibuka.',
            ]);
        }

        $links = EmployeeUserLink::query()
            ->where('is_active', true)
            ->get()
            ->keyBy('employee_id');
        $employees = $this->employeeSnapshots($links->keys()->all());
        $templates = PerformanceTemplate::query()
            ->where('is_active', true)
            ->with('items')
            ->get();
        $supervisorMap = PerformanceSupervisorAssignment::query()
            ->where('is_active', true)
            ->pluck('supervisor_user_id', 'department_id');
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $request,
            $cycle,
            $links,
            $employees,
            $templates,
            $supervisorMap,
            &$created,
            &$skipped,
        ) {
            foreach ($employees as $employee) {
                if (
                    PerformanceReview::query()
                        ->where('performance_cycle_id', $cycle->getKey())
                        ->where('employee_id', $employee->id)
                        ->exists()
                ) {
                    $skipped++;

                    continue;
                }

                $template = $this->matchingTemplate($templates, $employee);

                if (! $template) {
                    $skipped++;

                    continue;
                }

                $this->createReview(
                    $request,
                    $cycle,
                    $template,
                    $employee,
                    isset($supervisorMap[$employee->department_id])
                        ? (int) $supervisorMap[$employee->department_id]
                        : null,
                    (int) $links[$employee->id]->user_id,
                );
                $created++;
            }
        });

        AuditLogger::record(
            $request,
            'performance_review.bulk_generated',
            'performance_cycles',
            $cycle->getKey(),
            newValues: ['created' => $created, 'skipped' => $skipped],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "{$created} penilaian dijana. {$skipped} pekerja dilangkau kerana telah wujud atau tiada template sepadan.",
        ]);
    }

    public function supervisorReview(
        Request $request,
        PerformanceReview $review,
    ): RedirectResponse {
        abort_unless(
            $review->supervisor_user_id === $request->user()->getAuthIdentifier()
            || $request->user()->hasPermission('performance.manage'),
            403,
        );

        if ($review->status !== 'supervisor_assessment') {
            throw ValidationException::withMessages([
                'review' => 'Penilaian ini belum dihantar oleh pekerja atau telah selesai disemak.',
            ]);
        }

        $validated = $request->validate([
            'goals' => ['required', 'array', 'min:1'],
            'goals.*.id' => ['required', 'integer'],
            'goals.*.supervisor_score' => ['required', 'numeric', 'min:1', 'max:5'],
            'goals.*.supervisor_comments' => ['required', 'string', 'max:2000'],
            'supervisor_summary' => ['required', 'string', 'max:3000'],
            'strengths' => ['required', 'string', 'max:3000'],
            'improvement_areas' => ['required', 'string', 'max:3000'],
            'development_plan' => ['required', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($request, $review, $validated) {
            $this->updateGoalScores($review, $validated['goals'], 'supervisor');
            $score = $this->scoreCalculator->supervisorScore($review->fresh('goals'));
            $review->update([
                'supervisor_score' => $score,
                'supervisor_summary' => $validated['supervisor_summary'],
                'strengths' => $validated['strengths'],
                'improvement_areas' => $validated['improvement_areas'],
                'development_plan' => $validated['development_plan'],
                'status' => 'hr_moderation',
                'supervisor_submitted_at' => now(),
            ]);
            $this->notifyEmployee(
                $review,
                'supervisor_submitted',
                'Penilaian penyelia selesai',
                'Penilaian anda telah dihantar kepada HR untuk moderasi.',
            );
        });

        AuditLogger::record(
            $request,
            'performance_review.supervisor_submitted',
            'performance_reviews',
            $review->getKey(),
            oldValues: ['status' => 'supervisor_assessment'],
            newValues: [
                'status' => 'hr_moderation',
                'supervisor_score' => $review->fresh()->supervisor_score,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Penilaian penyelia dihantar kepada HR untuk moderasi.',
        ]);
    }

    public function moderate(
        Request $request,
        PerformanceReview $review,
    ): RedirectResponse {
        if ($review->status !== 'hr_moderation') {
            throw ValidationException::withMessages([
                'review' => 'Hanya penilaian di peringkat moderasi HR boleh dikemas kini.',
            ]);
        }

        $validated = $request->validate([
            'goals' => ['required', 'array', 'min:1'],
            'goals.*.id' => ['required', 'integer'],
            'goals.*.moderated_score' => ['required', 'numeric', 'min:1', 'max:5'],
            'goals.*.moderation_comments' => ['nullable', 'string', 'max:2000'],
            'hr_comments' => ['required', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($request, $review, $validated) {
            $this->updateGoalScores($review, $validated['goals'], 'moderated');
            $score = $this->scoreCalculator->moderatedScore($review->fresh('goals'));
            $review->update([
                'moderated_score' => $score,
                'final_rating' => $this->scoreCalculator->rating(
                    $review->fresh('cycle'),
                    $score,
                ),
                'hr_comments' => $validated['hr_comments'],
                'moderated_at' => now(),
                'moderated_by' => $request->user()->getAuthIdentifier(),
            ]);
        });

        AuditLogger::record(
            $request,
            'performance_review.moderated',
            'performance_reviews',
            $review->getKey(),
            newValues: [
                'moderated_score' => $review->fresh()->moderated_score,
                'final_rating' => $review->fresh()->final_rating,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Moderasi HR disimpan. Penilaian masih belum dimuktamadkan.',
        ]);
    }

    public function finalize(
        Request $request,
        PerformanceReview $review,
    ): RedirectResponse {
        if ($review->status !== 'hr_moderation' || $review->moderated_score === null) {
            throw ValidationException::withMessages([
                'review' => 'Moderasi HR mesti disimpan sebelum penilaian dimuktamadkan.',
            ]);
        }

        if (in_array((int) $request->user()->getAuthIdentifier(), [
            (int) $review->employee_user_id,
            (int) $review->moderated_by,
        ], true)) {
            throw ValidationException::withMessages([
                'review' => 'Pengurus HR yang memuktamadkan mestilah berbeza daripada pekerja dan pegawai yang membuat moderasi.',
            ]);
        }

        DB::transaction(function () use ($request, $review) {
            $review->update([
                'status' => 'finalized',
                'finalized_at' => now(),
                'finalized_by' => $request->user()->getAuthIdentifier(),
            ]);
            $this->notifyEmployee(
                $review,
                'finalized',
                'Penilaian prestasi dimuktamadkan',
                "Keputusan akhir: {$review->final_rating} ({$review->moderated_score}/5.00).",
            );
        });

        AuditLogger::record(
            $request,
            'performance_review.finalized',
            'performance_reviews',
            $review->getKey(),
            oldValues: ['status' => 'hr_moderation'],
            newValues: [
                'status' => 'finalized',
                'moderated_score' => $review->moderated_score,
                'final_rating' => $review->final_rating,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Penilaian prestasi telah dimuktamadkan.',
        ]);
    }

    public function savePip(
        Request $request,
        PerformanceReview $review,
    ): RedirectResponse {
        if ($review->status !== 'finalized') {
            throw ValidationException::withMessages([
                'pip' => 'PIP hanya boleh dibuka selepas penilaian dimuktamadkan.',
            ]);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'active', 'completed', 'extended', 'cancelled'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'reason' => ['required', 'string', 'max:3000'],
            'objectives' => ['required', 'string', 'max:5000'],
            'required_actions' => ['required', 'string', 'max:5000'],
            'support_required' => ['nullable', 'string', 'max:3000'],
            'success_criteria' => ['required', 'string', 'max:5000'],
            'outcome' => ['nullable', 'string', 'max:3000'],
        ]);
        $plan = PerformanceImprovementPlan::query()->updateOrCreate(
            ['performance_review_id' => $review->getKey()],
            [
                ...$validated,
                'employee_id' => $review->employee_id,
                'supervisor_user_id' => $review->supervisor_user_id,
                'created_by' => $review->improvementPlan?->created_by
                    ?? $request->user()->getAuthIdentifier(),
                'updated_by' => $request->user()->getAuthIdentifier(),
            ],
        );
        $this->notifyEmployee(
            $review,
            'pip_updated',
            $plan->wasRecentlyCreated ? 'Pelan Peningkatan Prestasi dibuka' : 'Pelan Peningkatan Prestasi dikemas kini',
            'Sila semak sasaran, tindakan dan tempoh PIP dalam Prestasi Saya.',
        );

        AuditLogger::record(
            $request,
            $plan->wasRecentlyCreated ? 'performance_pip.created' : 'performance_pip.updated',
            'performance_improvement_plans',
            $plan->getKey(),
            newValues: $plan->only(['performance_review_id', 'employee_id', 'status', 'start_date', 'end_date']),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $plan->wasRecentlyCreated
                ? 'PIP telah diwujudkan.'
                : 'PIP telah dikemas kini.',
        ]);
    }

    public function storePipCheckin(
        Request $request,
        PerformanceImprovementPlan $plan,
    ): RedirectResponse {
        $validated = $request->validate([
            'checkin_date' => ['required', 'date'],
            'progress_status' => ['required', Rule::in(['on_track', 'needs_attention', 'completed'])],
            'progress_notes' => ['required', 'string', 'max:3000'],
            'next_actions' => ['nullable', 'string', 'max:3000'],
        ]);
        $checkin = $plan->checkins()->create([
            ...$validated,
            'recorded_by' => $request->user()->getAuthIdentifier(),
        ]);
        $plan->loadMissing('review');
        $this->notifyEmployee(
            $plan->review,
            'pip_checkin',
            'Semakan kemajuan PIP direkodkan',
            'Rekod kemajuan baharu telah ditambah pada Pelan Peningkatan Prestasi anda.',
        );

        AuditLogger::record(
            $request,
            'performance_pip.checkin_added',
            'performance_pip_checkins',
            $checkin->getKey(),
            newValues: [
                'performance_improvement_plan_id' => $plan->getKey(),
                'checkin_date' => $checkin->checkin_date,
                'progress_status' => $checkin->progress_status,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Semakan kemajuan PIP telah direkodkan.',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $reviews = $this->visibleQuery($request)
            ->with(['cycle:id,name', 'supervisor:id,name'])
            ->latest()
            ->get();
        $employeeMap = $this->employeeMap($reviews->pluck('employee_id')->all());
        $departmentMap = $this->departments()->pluck('name', 'id');

        AuditLogger::record(
            $request,
            'performance_report.exported',
            'performance_reviews',
            'report',
            newValues: ['total' => $reviews->count()],
        );

        return response()->streamDownload(function () use (
            $reviews,
            $employeeMap,
            $departmentMap,
        ) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Kitaran',
                'ID Pekerja',
                'Nama',
                'Jabatan',
                'Jawatan',
                'Penyelia',
                'Status',
                'Skor Kendiri',
                'Skor Penyelia',
                'Skor Akhir',
                'Rating',
                'PIP',
            ]);

            foreach ($reviews as $review) {
                $employee = $employeeMap[$review->employee_id] ?? null;
                fputcsv($output, [
                    $review->cycle?->name,
                    $employee?->employee_number,
                    $employee?->name,
                    $departmentMap[$review->department_id] ?? null,
                    $review->position_name,
                    $review->supervisor?->name,
                    $review->status,
                    $review->self_score,
                    $review->supervisor_score,
                    $review->moderated_score,
                    $review->final_rating,
                    $review->improvementPlan?->status,
                ]);
            }

            fclose($output);
        }, 'laporan-prestasi-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function pdf(
        Request $request,
        PerformanceReview $review,
    ): HttpResponse {
        $this->authorizeVisible($request, $review);
        $employee = $this->employeeMap([$review->employee_id])[$review->employee_id]
            ?? null;
        abort_unless($employee, 404);
        $department = $this->departments()
            ->firstWhere('id', $review->department_id)?->name;
        $pdf = $this->pdfRenderer->render(
            $review,
            [
                'name' => $employee->name,
                'employee_number' => $employee->employee_number,
            ],
            $department,
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="prestasi-'
                .($employee->employee_number ?: $review->employee_id)
                .'-'.$review->cycle?->code.'.pdf"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadEvidence(
        Request $request,
        PerformanceReview $review,
        PerformanceEvidence $evidence,
    ): StreamedResponse {
        abort_unless($evidence->performance_review_id === $review->getKey(), 404);
        $this->authorizeVisible($request, $review);

        if (! Storage::disk($evidence->disk)->exists($evidence->path)) {
            abort(404, 'Fail bukti tidak dijumpai.');
        }

        return Storage::disk($evidence->disk)->download(
            $evidence->path,
            $evidence->original_name,
        );
    }

    private function visibleQuery(Request $request): Builder
    {
        $query = PerformanceReview::query();

        if (
            $request->user()->hasPermission('performance.manage')
            || $request->user()->hasPermission('performance.moderate')
            || $request->user()->hasPermission('performance.finalize')
        ) {
            return $query;
        }

        return $query->where(
            'supervisor_user_id',
            $request->user()->getAuthIdentifier(),
        );
    }

    private function authorizeVisible(
        Request $request,
        PerformanceReview $review,
    ): void {
        abort_unless(
            $request->user()->hasPermission('performance.manage')
            || $request->user()->hasPermission('performance.moderate')
            || $request->user()->hasPermission('performance.finalize')
            || $review->supervisor_user_id === $request->user()->getAuthIdentifier(),
            403,
        );
    }

    private function createReview(
        Request $request,
        PerformanceCycle $cycle,
        PerformanceTemplate $template,
        object $employee,
        ?int $supervisorId,
        ?int $employeeUserId = null,
    ): PerformanceReview {
        $template->loadMissing('items');
        $totalWeight = round((float) $template->items->sum('weight'), 2);

        if (abs($totalWeight - 100) > .01) {
            throw ValidationException::withMessages([
                'performance_template_id' => 'Template KPI mesti mempunyai jumlah pemberat tepat 100%.',
            ]);
        }

        $employeeUserId ??= EmployeeUserLink::query()
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->value('user_id');
        $review = PerformanceReview::query()->create([
            'performance_cycle_id' => $cycle->getKey(),
            'performance_template_id' => $template->getKey(),
            'employee_id' => $employee->id,
            'employee_user_id' => $employeeUserId,
            'supervisor_user_id' => $supervisorId,
            'department_id' => $employee->department_id,
            'position_name' => $employee->position_name,
            'status' => $cycle->status === 'open'
                ? 'self_assessment'
                : 'goal_setting',
            'total_weight' => $totalWeight,
        ]);

        foreach ($template->items as $item) {
            $review->goals()->create([
                'performance_template_item_id' => $item->getKey(),
                'title' => $item->title,
                'description' => $item->description,
                'measure_type' => $item->measure_type,
                'target_value' => $item->target_value,
                'unit' => $item->unit,
                'weight' => $item->weight,
                'scoring_guide' => $item->scoring_guide,
                'sort_order' => $item->sort_order,
            ]);
        }

        if ($employeeUserId) {
            PerformanceNotification::query()->create([
                'user_id' => $employeeUserId,
                'performance_review_id' => $review->getKey(),
                'type' => 'review_created',
                'title' => 'Penilaian prestasi baharu',
                'message' => $cycle->status === 'open'
                    ? "Self-Assessment bagi {$cycle->name} telah dibuka."
                    : "Sasaran KPI bagi {$cycle->name} telah disediakan dan akan dibuka oleh HR.",
            ]);
        }

        return $review;
    }

    /**
     * @param  Collection<int, PerformanceTemplate>  $templates
     */
    private function matchingTemplate(
        Collection $templates,
        object $employee,
    ): ?PerformanceTemplate {
        return $templates
            ->sortByDesc(function (PerformanceTemplate $template) use ($employee) {
                $score = 0;

                if ($template->department_id !== null) {
                    if ((int) $template->department_id !== (int) $employee->department_id) {
                        return -1;
                    }

                    $score += 2;
                }

                if ($template->position_name !== null) {
                    if (strcasecmp($template->position_name, (string) $employee->position_name) !== 0) {
                        return -1;
                    }

                    $score += 4;
                }

                return $score;
            })
            ->first(function (PerformanceTemplate $template) use ($employee) {
                return ($template->department_id === null
                        || (int) $template->department_id === (int) $employee->department_id)
                    && ($template->position_name === null
                        || strcasecmp($template->position_name, (string) $employee->position_name) === 0);
            });
    }

    /**
     * @param  array<int, array<string, mixed>>  $goals
     */
    private function updateGoalScores(
        PerformanceReview $review,
        array $goals,
        string $prefix,
    ): void {
        $reviewGoals = $review->goals()->get()->keyBy('id');

        foreach ($goals as $goalData) {
            $goal = $reviewGoals[(int) $goalData['id']] ?? null;

            if (! $goal) {
                throw ValidationException::withMessages([
                    'goals' => 'Salah satu sasaran tidak tergolong dalam penilaian ini.',
                ]);
            }

            $scoreKey = $prefix === 'moderated'
                ? 'moderated_score'
                : 'supervisor_score';
            $commentsKey = $prefix === 'moderated'
                ? 'moderation_comments'
                : 'supervisor_comments';
            $goal->update([
                $scoreKey => $goalData[$scoreKey],
                $commentsKey => $goalData[$commentsKey] ?? null,
            ]);
        }
    }

    private function notifyEmployee(
        PerformanceReview $review,
        string $type,
        string $title,
        string $message,
    ): void {
        if (! $review->employee_user_id) {
            return;
        }

        PerformanceNotification::query()->create([
            'user_id' => $review->employee_user_id,
            'performance_review_id' => $review->getKey(),
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);
    }

    private function employeeSnapshot(int $employeeId): ?object
    {
        return $this->employeeSnapshots([$employeeId])->first();
    }

    /**
     * @param  array<int, int|string>  $employeeIds
     * @return Collection<int, object>
     */
    private function employeeSnapshots(array $employeeIds): Collection
    {
        $legacy = DB::connection('ibco')
            ->table('maklumatpekerja as employee')
            ->leftJoin('maklumatjawatan as position', function ($join) {
                $join->on('position.id_pekerja', '=', 'employee.id')
                    ->where('position.rcd_enable', 1);
            })
            ->where('employee.rcd_enable', 1)
            ->whereIn('employee.id', $employeeIds)
            ->orderByDesc('position.id')
            ->get([
                'employee.id',
                'employee.employeeID as employee_number',
                'employee.nama as name',
                'position.id_department as department_id',
                'position.jawatan as position_name',
            ])
            ->unique('id')
            ->values();
        $local = EmployeeRecord::query()
            ->whereIn('directory_id', $employeeIds)
            ->whereIn('status', ['pending_activation', 'active'])
            ->get()
            ->map(fn (EmployeeRecord $employee) => (object) [
                'id' => $employee->directory_id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->name,
                'department_id' => $employee->department_id,
                'position_name' => $employee->position_name,
            ]);

        return $legacy->concat($local)->values();
    }

    /**
     * @param  array<int, int|string>  $employeeIds
     * @return Collection<int|string, object>
     */
    private function employeeMap(array $employeeIds): Collection
    {
        return $this->employeeSnapshots($employeeIds)->keyBy('id');
    }

    private function departments(): Collection
    {
        return DB::connection('ibco')
            ->table('xdepartment')
            ->where('rcd_enable', 1)
            ->orderBy('description')
            ->get(['id', 'description as name']);
    }

    private function linkedEmployeeOptions(): Collection
    {
        $links = EmployeeUserLink::query()
            ->where('is_active', true)
            ->with(['user:id,name,email', 'employeeRecord'])
            ->get()
            ->keyBy('employee_id');

        return $this->employeeSnapshots($links->keys()->all())
            ->map(fn ($employee) => [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'name' => $employee->name,
                'department_id' => $employee->department_id,
                'position_name' => $employee->position_name,
                'user_id' => $links[$employee->id]?->user_id,
            ]);
    }

    private function supervisorOptions(): Collection
    {
        return \App\Models\User::query()
            ->with('roleAssignments')
            ->orderBy('name')
            ->get()
            ->filter(fn ($user) => $user->hasPermission('performance.supervise')
                || $user->hasPermission('performance.manage'))
            ->values()
            ->map(fn ($user) => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ]);
    }

    /**
     * @param  Collection<int|string, object>  $employeeMap
     * @param  Collection<int|string, string>  $departmentMap
     * @return array<string, mixed>
     */
    private function reviewPayload(
        PerformanceReview $review,
        Collection $employeeMap,
        Collection $departmentMap,
    ): array {
        $employee = $employeeMap[$review->employee_id] ?? null;

        return [
            'id' => $review->getKey(),
            'employee' => $employee
                ? [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'name' => $employee->name,
                ]
                : null,
            'department' => $departmentMap[$review->department_id] ?? null,
            'position_name' => $review->position_name,
            'cycle' => [
                'id' => $review->cycle?->getKey(),
                'name' => $review->cycle?->name,
                'self_assessment_due_at' => $review->cycle?->self_assessment_due_at?->toDateString(),
                'supervisor_due_at' => $review->cycle?->supervisor_due_at?->toDateString(),
                'moderation_due_at' => $review->cycle?->moderation_due_at?->toDateString(),
            ],
            'template' => $review->template?->name,
            'supervisor' => $review->supervisor?->name,
            'supervisor_user_id' => $review->supervisor_user_id,
            'status' => $review->status,
            'total_weight' => (float) $review->total_weight,
            'self_score' => $review->self_score === null ? null : (float) $review->self_score,
            'supervisor_score' => $review->supervisor_score === null ? null : (float) $review->supervisor_score,
            'moderated_score' => $review->moderated_score === null ? null : (float) $review->moderated_score,
            'final_rating' => $review->final_rating,
            'employee_summary' => $review->employee_summary,
            'supervisor_summary' => $review->supervisor_summary,
            'strengths' => $review->strengths,
            'improvement_areas' => $review->improvement_areas,
            'development_plan' => $review->development_plan,
            'hr_comments' => $review->hr_comments,
            'goals' => $review->goals->map(fn ($goal) => [
                'id' => $goal->getKey(),
                'title' => $goal->title,
                'description' => $goal->description,
                'measure_type' => $goal->measure_type,
                'target_value' => $goal->target_value === null ? null : (float) $goal->target_value,
                'unit' => $goal->unit,
                'weight' => (float) $goal->weight,
                'scoring_guide' => $goal->scoring_guide,
                'actual_achievement' => $goal->actual_achievement,
                'self_score' => $goal->self_score === null ? null : (float) $goal->self_score,
                'self_comments' => $goal->self_comments,
                'supervisor_score' => $goal->supervisor_score === null ? null : (float) $goal->supervisor_score,
                'supervisor_comments' => $goal->supervisor_comments,
                'moderated_score' => $goal->moderated_score === null ? null : (float) $goal->moderated_score,
                'moderation_comments' => $goal->moderation_comments,
            ]),
            'evidence' => $review->evidence->map(fn ($evidence) => [
                'id' => $evidence->getKey(),
                'goal_id' => $evidence->performance_goal_id,
                'original_name' => $evidence->original_name,
                'mime_type' => $evidence->mime_type,
                'size' => $evidence->size,
                'description' => $evidence->description,
            ]),
            'pip' => $review->improvementPlan
                ? [
                    'id' => $review->improvementPlan->getKey(),
                    'status' => $review->improvementPlan->status,
                    'start_date' => $review->improvementPlan->start_date?->toDateString(),
                    'end_date' => $review->improvementPlan->end_date?->toDateString(),
                    'reason' => $review->improvementPlan->reason,
                    'objectives' => $review->improvementPlan->objectives,
                    'required_actions' => $review->improvementPlan->required_actions,
                    'support_required' => $review->improvementPlan->support_required,
                    'success_criteria' => $review->improvementPlan->success_criteria,
                    'outcome' => $review->improvementPlan->outcome,
                    'checkins' => $review->improvementPlan->checkins->map(fn ($checkin) => [
                        'id' => $checkin->getKey(),
                        'checkin_date' => $checkin->checkin_date?->toDateString(),
                        'progress_status' => $checkin->progress_status,
                        'progress_notes' => $checkin->progress_notes,
                        'next_actions' => $checkin->next_actions,
                    ]),
                ]
                : null,
        ];
    }
}
