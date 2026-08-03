<?php

namespace App\Http\Controllers;

use App\Models\CompetencyRequirement;
use App\Models\DevelopmentPlan;
use App\Models\EmployeeCompetency;
use App\Models\TrainingApprovalAssignment;
use App\Models\TrainingAttachment;
use App\Models\TrainingNotification;
use App\Models\TrainingRequest;
use App\Models\TrainingSession;
use App\Support\AuditLogger;
use App\Support\TrainingEmployeeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmployeeTrainingController extends Controller
{
    public function __construct(
        private readonly TrainingEmployeeResolver $employees,
    ) {}

    public function index(Request $request): Response
    {
        $employee = $this->employees->forUser($request->user());
        $requests = TrainingRequest::query()
            ->where('employee_user_id', $request->user()->getAuthIdentifier())
            ->with([
                'session.course.provider:id,name',
                'developmentPlan.competency:id,code,name',
                'attachments:id,training_request_id,attachment_type,original_name,mime_type,size,valid_until',
            ])
            ->latest()
            ->get();
        $plans = DevelopmentPlan::query()
            ->where('employee_user_id', $request->user()->getAuthIdentifier())
            ->with('competency:id,code,name,maximum_level')
            ->latest('due_date')
            ->get();
        $currentLevels = EmployeeCompetency::query()
            ->where('employee_user_id', $request->user()->getAuthIdentifier())
            ->pluck('current_level', 'competency_id');
        $requirements = $employee
            ? CompetencyRequirement::query()
                ->with('competency:id,code,name,category,maximum_level')
                ->whereHas('competency', fn ($query) => $query->where('is_active', true))
                ->where(function ($query) use ($employee) {
                    $query->whereNull('department_id')
                        ->orWhere('department_id', $employee->department_id);
                })
                ->where(function ($query) use ($employee) {
                    $query->whereNull('position_name')
                        ->orWhere('position_name', $employee->position_name);
                })
                ->get()
            : collect();
        $notifications = TrainingNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('EmployeeSelfService/Training', [
            'employee' => $employee,
            'requests' => $requests->map(fn (TrainingRequest $training) => $this->payload($training)),
            'sessions' => TrainingSession::query()
                ->where('status', 'open')
                ->where('starts_at', '>=', now())
                ->where(fn ($query) => $query
                    ->whereNull('registration_deadline')
                    ->orWhereDate('registration_deadline', '>=', today()))
                ->with('course.provider:id,name')
                ->withCount(['requests as approved_count' => fn ($query) => $query
                    ->whereIn('status', ['approved', 'completed'])])
                ->orderBy('starts_at')
                ->get()
                ->filter(fn (TrainingSession $session) => $session->approved_count < $session->capacity)
                ->values()
                ->map(fn (TrainingSession $session) => [
                    'id' => $session->getKey(),
                    'session_code' => $session->session_code,
                    'title' => $session->course?->title,
                    'provider' => $session->course?->provider?->name,
                    'starts_at' => $session->starts_at?->toIso8601String(),
                    'ends_at' => $session->ends_at?->toIso8601String(),
                    'venue' => $session->venue,
                    'cost' => (float) $session->cost_per_participant,
                    'available_seats' => max(0, $session->capacity - $session->approved_count),
                ]),
            'developmentPlans' => $plans->map(fn (DevelopmentPlan $plan) => [
                'id' => $plan->getKey(),
                'title' => $plan->title,
                'source' => $plan->source,
                'competency' => $plan->competency?->name,
                'action_plan' => $plan->action_plan,
                'target_level' => $plan->target_level,
                'due_date' => $plan->due_date?->toDateString(),
                'status' => $plan->status,
            ]),
            'competencyGaps' => $requirements->map(function (CompetencyRequirement $requirement) use ($currentLevels) {
                $current = (int) ($currentLevels[$requirement->competency_id] ?? 0);

                return [
                    'competency_id' => $requirement->competency_id,
                    'code' => $requirement->competency?->code,
                    'name' => $requirement->competency?->name,
                    'category' => $requirement->competency?->category,
                    'current_level' => $current,
                    'required_level' => $requirement->required_level,
                    'gap' => max(0, $requirement->required_level - $current),
                    'is_mandatory' => $requirement->is_mandatory,
                ];
            })->sortByDesc('gap')->values(),
            'notifications' => $notifications->map(fn (TrainingNotification $notification) => [
                'id' => $notification->getKey(),
                'title' => $notification->title,
                'message' => $notification->message,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]),
            'unreadNotifications' => $notifications->whereNull('read_at')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'training_session_id' => ['nullable', 'integer', 'exists:training_sessions,id'],
            'course_title' => ['required_without:training_session_id', 'nullable', 'string', 'max:200'],
            'justification' => ['required', 'string', 'max:5000'],
            'development_source' => ['required', Rule::in(['self', 'kpi', 'pip', 'onboarding', 'mandatory', 'competency_gap'])],
            'development_plan_id' => ['nullable', 'integer', 'exists:development_plans,id'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'supporting_document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);
        $employee = $this->employees->forUser($request->user());

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee' => 'Profil pekerja aktif belum dipautkan kepada akaun anda.',
            ]);
        }

        $session = isset($validated['training_session_id'])
            ? TrainingSession::query()->with('course')->findOrFail($validated['training_session_id'])
            : null;
        if ($session && (
            $session->status !== 'open'
            || $session->starts_at?->isPast()
            || ($session->registration_deadline && $session->registration_deadline->lt(today()))
        )) {
            throw ValidationException::withMessages([
                'training_session_id' => 'Sesi latihan ini tidak lagi dibuka untuk permohonan.',
            ]);
        }
        if ($session && TrainingRequest::query()
            ->where('employee_user_id', $request->user()->getAuthIdentifier())
            ->where('training_session_id', $session->getKey())
            ->whereIn('status', ['pending', 'approved', 'completed'])
            ->exists()) {
            throw ValidationException::withMessages([
                'training_session_id' => 'Anda telah mempunyai permohonan aktif bagi sesi ini.',
            ]);
        }
        $plan = isset($validated['development_plan_id'])
            ? DevelopmentPlan::query()
                ->whereKey($validated['development_plan_id'])
                ->where('employee_user_id', $request->user()->getAuthIdentifier())
                ->first()
            : null;
        if (isset($validated['development_plan_id']) && ! $plan) {
            throw ValidationException::withMessages([
                'development_plan_id' => 'Pelan pembangunan yang dipilih bukan milik anda.',
            ]);
        }

        $training = DB::transaction(function () use ($request, $validated, $employee, $session) {
            $supervisorId = TrainingApprovalAssignment::query()
                ->where('department_id', $employee->department_id)
                ->where('is_active', true)
                ->value('approver_user_id');
            $training = TrainingRequest::query()->create([
                'request_number' => $this->requestNumber(),
                'employee_user_id' => $request->user()->getAuthIdentifier(),
                'employee_id' => $employee->id,
                'department_id' => $employee->department_id,
                'budget_year' => $session?->starts_at?->year ?? now()->year,
                'position_name' => $employee->position_name,
                'training_session_id' => $session?->getKey(),
                'development_plan_id' => $validated['development_plan_id'] ?? null,
                'course_title' => $session?->course?->title ?? $validated['course_title'],
                'justification' => $validated['justification'],
                'development_source' => $validated['development_source'],
                'estimated_cost' => $session
                    ? $session->cost_per_participant
                    : ($validated['estimated_cost'] ?? 0),
                'status' => 'pending',
                'approval_stage' => $supervisorId ? 'supervisor' : 'hr',
                'supervisor_user_id' => $supervisorId,
                'created_by' => $request->user()->getAuthIdentifier(),
            ]);

            if ($request->hasFile('supporting_document')) {
                $this->storeAttachment(
                    $training,
                    $request->file('supporting_document'),
                    'supporting',
                    $request->user()->getAuthIdentifier(),
                );
            }
            if ($supervisorId) {
                TrainingNotification::query()->create([
                    'user_id' => $supervisorId,
                    'training_request_id' => $training->getKey(),
                    'type' => 'approval_required',
                    'title' => 'Permohonan latihan menunggu sokongan',
                    'message' => "{$employee->name} memohon latihan {$training->course_title}.",
                ]);
            }

            return $training;
        });

        AuditLogger::record(
            $request,
            'training.request_submitted',
            'training_requests',
            $training->getKey(),
            newValues: $training->only([
                'request_number', 'employee_id', 'course_title',
                'estimated_cost', 'development_source', 'approval_stage',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Permohonan latihan telah dihantar.',
        ]);
    }

    public function cancel(Request $request, TrainingRequest $training): RedirectResponse
    {
        $this->authorizeOwner($request, $training);
        if ($training->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Hanya permohonan yang masih menunggu boleh dibatalkan.',
            ]);
        }
        $training->update([
            'status' => 'cancelled',
            'approval_stage' => null,
            'cancelled_at' => now(),
        ]);
        AuditLogger::record(
            $request,
            'training.request_cancelled',
            'training_requests',
            $training->getKey(),
            oldValues: ['status' => 'pending'],
            newValues: ['status' => 'cancelled'],
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Permohonan latihan dibatalkan.']);
    }

    public function uploadCertificate(Request $request, TrainingRequest $training): RedirectResponse
    {
        $this->authorizeOwner($request, $training);
        if (! in_array($training->status, ['approved', 'completed'], true)) {
            throw ValidationException::withMessages([
                'certificate' => 'Sijil hanya boleh dimuat naik bagi latihan yang diluluskan atau selesai.',
            ]);
        }
        $validated = $request->validate([
            'certificate' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $training->loadMissing('session.course');
        $validUntil = $validated['valid_until'] ?? null;
        if (! $validUntil && $training->session?->course?->certificate_validity_months) {
            $validUntil = ($training->completed_at ?? now())
                ->copy()
                ->addMonths($training->session->course->certificate_validity_months)
                ->toDateString();
        }
        $attachment = $this->storeAttachment(
            $training,
            $request->file('certificate'),
            'certificate',
            $request->user()->getAuthIdentifier(),
            $validUntil,
        );
        AuditLogger::record(
            $request,
            'training.certificate_uploaded',
            'training_attachments',
            $attachment->getKey(),
            newValues: ['training_request_id' => $training->getKey(), 'valid_until' => $attachment->valid_until],
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Sijil latihan telah dimuat naik.']);
    }

    public function evaluate(Request $request, TrainingRequest $training): RedirectResponse
    {
        $this->authorizeOwner($request, $training);
        if ($training->status !== 'completed') {
            throw ValidationException::withMessages([
                'rating' => 'Penilaian hanya boleh diberikan selepas latihan selesai.',
            ]);
        }
        $validated = $request->validate([
            'employee_rating' => ['required', 'integer', 'between:1,5'],
            'employee_feedback' => ['required', 'string', 'max:5000'],
        ]);
        $training->update([...$validated, 'evaluated_at' => now()]);
        AuditLogger::record(
            $request,
            'training.employee_evaluated',
            'training_requests',
            $training->getKey(),
            newValues: ['employee_rating' => $training->employee_rating],
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Penilaian latihan telah disimpan.']);
    }

    public function downloadAttachment(
        Request $request,
        TrainingRequest $training,
        TrainingAttachment $attachment,
    ): HttpResponse {
        $this->authorizeOwner($request, $training);
        abort_unless((int) $attachment->training_request_id === (int) $training->getKey(), 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function readNotifications(Request $request): RedirectResponse
    {
        TrainingNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    private function payload(TrainingRequest $training): array
    {
        return [
            'id' => $training->getKey(),
            'request_number' => $training->request_number,
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
            'supervisor_notes' => $training->supervisor_notes,
            'hr_notes' => $training->hr_notes,
            'attendance_status' => $training->attendance_status,
            'attended_hours' => $training->attended_hours === null ? null : (float) $training->attended_hours,
            'assessment_score' => $training->assessment_score === null ? null : (float) $training->assessment_score,
            'passed' => $training->passed,
            'employee_rating' => $training->employee_rating,
            'employee_feedback' => $training->employee_feedback,
            'created_at' => $training->created_at?->toIso8601String(),
            'attachments' => $training->attachments->map(fn (TrainingAttachment $attachment) => [
                'id' => $attachment->getKey(),
                'type' => $attachment->attachment_type,
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'valid_until' => $attachment->valid_until?->toDateString(),
            ]),
        ];
    }

    private function requestNumber(): string
    {
        do {
            $number = 'TRN-'.now()->format('Ymd').'-'.strtoupper(str()->random(6));
        } while (TrainingRequest::query()->where('request_number', $number)->exists());

        return $number;
    }

    private function storeAttachment(
        TrainingRequest $training,
        $file,
        string $type,
        int $userId,
        ?string $validUntil = null,
    ): TrainingAttachment {
        $path = $file->store("private/training/{$training->getKey()}", 'local');

        return $training->attachments()->create([
            'attachment_type' => $type,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'valid_until' => $validUntil,
            'uploaded_by' => $userId,
        ]);
    }

    private function authorizeOwner(Request $request, TrainingRequest $training): void
    {
        abort_unless(
            (int) $training->employee_user_id === (int) $request->user()->getAuthIdentifier(),
            403,
        );
    }
}
