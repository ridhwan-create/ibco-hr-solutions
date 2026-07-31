<?php

namespace App\Http\Controllers;

use App\Models\EmployeeUserLink;
use App\Models\PerformanceEvidence;
use App\Models\PerformanceGoal;
use App\Models\PerformanceNotification;
use App\Models\PerformanceReview;
use App\Support\AuditLogger;
use App\Support\PerformancePdfRenderer;
use App\Support\PerformanceScoreCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeePerformanceController extends Controller
{
    public function __construct(
        private readonly PerformanceScoreCalculator $scoreCalculator,
        private readonly PerformancePdfRenderer $pdfRenderer,
    ) {}

    public function index(Request $request): Response
    {
        $link = $this->activeLink($request);

        if (! $link) {
            return Inertia::render('EmployeeSelfService/Performance', [
                'employee' => null,
                'summary' => $this->emptySummary(),
                'reviews' => [],
                'notifications' => [],
            ]);
        }

        $employee = DB::connection('ibco')
            ->table('maklumatpekerja as employee')
            ->leftJoin('maklumatjawatan as position', function ($join) {
                $join->on('position.id_pekerja', '=', 'employee.id')
                    ->where('position.rcd_enable', 1);
            })
            ->leftJoin('xdepartment as department', 'department.id', '=', 'position.id_department')
            ->where('employee.id', $link->employee_id)
            ->where('employee.rcd_enable', 1)
            ->orderByDesc('position.id')
            ->first([
                'employee.id',
                'employee.employeeID as employee_number',
                'employee.nama as name',
                'position.jawatan as position_name',
                'department.description as department',
            ]);
        $reviews = PerformanceReview::query()
            ->where('employee_id', $link->employee_id)
            ->with([
                'cycle',
                'template:id,name',
                'supervisor:id,name',
                'goals',
                'evidence:id,performance_review_id,performance_goal_id,original_name,mime_type,size,description',
                'improvementPlan.checkins',
            ])
            ->latest('created_at')
            ->get();
        $notifications = PerformanceNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('EmployeeSelfService/Performance', [
            'employee' => $employee,
            'summary' => [
                'active' => $reviews->whereIn('status', [
                    'goal_setting',
                    'self_assessment',
                    'supervisor_assessment',
                    'hr_moderation',
                ])->count(),
                'awaiting_self' => $reviews->where('status', 'self_assessment')->count(),
                'finalized' => $reviews->where('status', 'finalized')->count(),
                'latest_score' => $reviews
                    ->where('status', 'finalized')
                    ->sortByDesc('finalized_at')
                    ->first()?->moderated_score,
                'unread_notifications' => $notifications->whereNull('read_at')->count(),
                'active_pip' => $reviews
                    ->pluck('improvementPlan')
                    ->filter()
                    ->whereIn('status', ['active', 'extended'])
                    ->count(),
            ],
            'reviews' => $reviews->map(fn (PerformanceReview $review) => [
                'id' => $review->getKey(),
                'cycle' => [
                    'name' => $review->cycle?->name,
                    'period_start' => $review->cycle?->period_start?->toDateString(),
                    'period_end' => $review->cycle?->period_end?->toDateString(),
                    'self_assessment_due_at' => $review->cycle?->self_assessment_due_at?->toDateString(),
                    'supervisor_due_at' => $review->cycle?->supervisor_due_at?->toDateString(),
                ],
                'template' => $review->template?->name,
                'supervisor' => $review->supervisor?->name,
                'status' => $review->status,
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
                            'checkin_date' => $checkin->checkin_date?->toDateString(),
                            'progress_status' => $checkin->progress_status,
                            'progress_notes' => $checkin->progress_notes,
                            'next_actions' => $checkin->next_actions,
                        ]),
                    ]
                    : null,
            ]),
            'notifications' => $notifications->map(fn (PerformanceNotification $notification) => [
                'id' => $notification->getKey(),
                'title' => $notification->title,
                'message' => $notification->message,
                'type' => $notification->type,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function saveSelfAssessment(
        Request $request,
        PerformanceReview $review,
    ): RedirectResponse {
        return $this->updateSelfAssessment($request, $review, false);
    }

    public function submitSelfAssessment(
        Request $request,
        PerformanceReview $review,
    ): RedirectResponse {
        return $this->updateSelfAssessment($request, $review, true);
    }

    public function uploadEvidence(
        Request $request,
        PerformanceReview $review,
    ): RedirectResponse {
        $this->authorizeOwnReview($request, $review);

        if ($review->status !== 'self_assessment') {
            throw ValidationException::withMessages([
                'evidence' => 'Bukti hanya boleh ditambah semasa Self-Assessment.',
            ]);
        }

        if ($review->evidence()->count() >= 10) {
            throw ValidationException::withMessages([
                'evidence' => 'Maksimum 10 fail bukti dibenarkan bagi setiap penilaian.',
            ]);
        }

        $validated = $request->validate([
            'performance_goal_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string', 'max:1000'],
            'evidence' => [
                'required',
                'file',
                'max:8192',
                'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
            ],
        ]);
        $goalId = ! empty($validated['performance_goal_id'])
            ? (int) $validated['performance_goal_id']
            : null;

        if (
            $goalId
            && ! $review->goals()->whereKey($goalId)->exists()
        ) {
            throw ValidationException::withMessages([
                'performance_goal_id' => 'Sasaran yang dipilih tidak sah.',
            ]);
        }

        $file = $request->file('evidence');
        $path = $file->storeAs(
            "performance-evidence/{$request->user()->getAuthIdentifier()}",
            Str::uuid().'.'.$file->getClientOriginalExtension(),
            'local',
        );
        $evidence = $review->evidence()->create([
            'performance_goal_id' => $goalId,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'description' => $validated['description'] ?? null,
            'uploaded_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'performance_evidence.uploaded',
            'performance_evidence',
            $evidence->getKey(),
            newValues: [
                'performance_review_id' => $review->getKey(),
                'performance_goal_id' => $goalId,
                'original_name' => $evidence->original_name,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Bukti pencapaian telah dimuat naik.',
        ]);
    }

    public function deleteEvidence(
        Request $request,
        PerformanceReview $review,
        PerformanceEvidence $evidence,
    ): RedirectResponse {
        $this->authorizeOwnReview($request, $review);
        abort_unless($evidence->performance_review_id === $review->getKey(), 404);

        if ($review->status !== 'self_assessment') {
            throw ValidationException::withMessages([
                'evidence' => 'Bukti tidak boleh dibuang selepas Self-Assessment dihantar.',
            ]);
        }

        Storage::disk($evidence->disk)->delete($evidence->path);
        $id = $evidence->getKey();
        $name = $evidence->original_name;
        $evidence->delete();

        AuditLogger::record(
            $request,
            'performance_evidence.deleted',
            'performance_evidence',
            $id,
            oldValues: ['performance_review_id' => $review->getKey(), 'original_name' => $name],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Bukti pencapaian telah dibuang.',
        ]);
    }

    public function downloadEvidence(
        Request $request,
        PerformanceReview $review,
        PerformanceEvidence $evidence,
    ): StreamedResponse {
        $this->authorizeOwnReview($request, $review);
        abort_unless($evidence->performance_review_id === $review->getKey(), 404);

        if (! Storage::disk($evidence->disk)->exists($evidence->path)) {
            abort(404, 'Fail bukti tidak dijumpai.');
        }

        return Storage::disk($evidence->disk)->download(
            $evidence->path,
            $evidence->original_name,
        );
    }

    public function downloadPdf(
        Request $request,
        PerformanceReview $review,
    ): HttpResponse {
        $this->authorizeOwnReview($request, $review);
        abort_unless($review->status === 'finalized', 404);
        $employee = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('id', $review->employee_id)
            ->first(['employeeID as employee_number', 'nama as name']);
        $department = DB::connection('ibco')
            ->table('xdepartment')
            ->where('id', $review->department_id)
            ->value('description');

        return response(
            $this->pdfRenderer->render(
                $review,
                [
                    'name' => $employee?->name,
                    'employee_number' => $employee?->employee_number,
                ],
                $department,
            ),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="prestasi-saya-'
                    .($employee?->employee_number ?: $review->employee_id)
                    .'-'.$review->cycle?->code.'.pdf"',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function readNotifications(Request $request): RedirectResponse
    {
        PerformanceNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    private function updateSelfAssessment(
        Request $request,
        PerformanceReview $review,
        bool $submit,
    ): RedirectResponse {
        $this->authorizeOwnReview($request, $review);

        if ($review->status !== 'self_assessment') {
            throw ValidationException::withMessages([
                'review' => 'Self-Assessment ini tidak lagi boleh dikemas kini.',
            ]);
        }

        $scoreRules = $submit
            ? ['required', 'numeric', 'min:1', 'max:5']
            : ['nullable', 'numeric', 'min:1', 'max:5'];
        $commentRules = $submit
            ? ['required', 'string', 'max:2000']
            : ['nullable', 'string', 'max:2000'];
        $validated = $request->validate([
            'goals' => ['required', 'array', 'min:1'],
            'goals.*.id' => ['required', 'integer'],
            'goals.*.actual_achievement' => $submit
                ? ['required', 'string', 'max:3000']
                : ['nullable', 'string', 'max:3000'],
            'goals.*.self_score' => $scoreRules,
            'goals.*.self_comments' => $commentRules,
            'employee_summary' => $submit
                ? ['required', 'string', 'max:3000']
                : ['nullable', 'string', 'max:3000'],
        ]);
        $goals = $review->goals()->get()->keyBy('id');

        DB::transaction(function () use (
            $request,
            $review,
            $validated,
            $goals,
            $submit,
        ) {
            foreach ($validated['goals'] as $goalData) {
                $goal = $goals[(int) $goalData['id']] ?? null;

                if (! $goal) {
                    throw ValidationException::withMessages([
                        'goals' => 'Salah satu sasaran tidak tergolong dalam penilaian ini.',
                    ]);
                }

                $goal->update([
                    'actual_achievement' => $goalData['actual_achievement'] ?? null,
                    'self_score' => $goalData['self_score'] ?? null,
                    'self_comments' => $goalData['self_comments'] ?? null,
                ]);
            }

            $review->update([
                'employee_summary' => $validated['employee_summary'] ?? null,
            ]);

            if ($submit) {
                $score = $this->scoreCalculator->selfScore($review->fresh('goals'));
                $review->update([
                    'self_score' => $score,
                    'status' => 'supervisor_assessment',
                    'self_submitted_at' => now(),
                ]);

                if ($review->supervisor_user_id) {
                    PerformanceNotification::query()->create([
                        'user_id' => $review->supervisor_user_id,
                        'performance_review_id' => $review->getKey(),
                        'type' => 'supervisor_action',
                        'title' => 'Penilaian pekerja menunggu semakan',
                        'message' => 'Self-Assessment pekerja telah dihantar untuk penilaian penyelia.',
                    ]);
                }
            }
        });

        AuditLogger::record(
            $request,
            $submit
                ? 'performance_review.self_submitted'
                : 'performance_review.self_saved',
            'performance_reviews',
            $review->getKey(),
            oldValues: ['status' => 'self_assessment'],
            newValues: [
                'status' => $submit ? 'supervisor_assessment' : 'self_assessment',
                'self_score' => $submit ? $review->fresh()->self_score : null,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $submit
                ? 'Self-Assessment dihantar kepada penyelia.'
                : 'Draf Self-Assessment telah disimpan.',
        ]);
    }

    private function authorizeOwnReview(
        Request $request,
        PerformanceReview $review,
    ): void {
        $link = $this->activeLink($request);
        abort_unless($link && $review->employee_id === $link->employee_id, 403);
    }

    private function activeLink(Request $request): ?EmployeeUserLink
    {
        return EmployeeUserLink::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return array<string, int|float|null>
     */
    private function emptySummary(): array
    {
        return [
            'active' => 0,
            'awaiting_self' => 0,
            'finalized' => 0,
            'latest_score' => null,
            'unread_notifications' => 0,
            'active_pip' => 0,
        ];
    }
}
