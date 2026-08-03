<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\OnboardingCase;
use App\Models\OnboardingTemplate;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentDocument;
use App\Models\RecruitmentInterview;
use App\Models\RecruitmentNotification;
use App\Models\RecruitmentOffer;
use App\Models\RecruitmentRequisition;
use App\Models\RecruitmentScorecard;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\RecruitmentAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecruitmentController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();
        $stage = $request->string('stage')->trim()->toString();
        $requisitionId = $request->integer('requisition_id');
        $user = $request->user();
        $candidateQuery = RecruitmentCandidate::query()
            ->with([
                'requisition:id,code,title,department_id,hiring_manager_user_id',
                'owner:id,name',
                'offers:id,recruitment_candidate_id,status,start_date',
                'onboardingCase:id,recruitment_candidate_id,status',
            ]);
        $this->scopeCandidatesForUser($candidateQuery, $user);
        $candidateQuery
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('candidate_number', 'like', "%{$search}%"),
            ))
            ->when(
                in_array($stage, RecruitmentCandidate::STAGES, true),
                fn (Builder $query) => $query->where('stage', $stage),
            )
            ->when(
                $requisitionId > 0,
                fn (Builder $query) => $query->where(
                    'recruitment_requisition_id',
                    $requisitionId,
                ),
            );

        $requisitions = RecruitmentRequisition::query()
            ->with('hiringManager:id,name')
            ->withCount('candidates')
            ->when(
                ! $user->hasPermission('recruitment.manage')
                    && ! $user->hasPermission('recruitment.approve'),
                fn (Builder $query) => $query->where(
                    'hiring_manager_user_id',
                    $user->getAuthIdentifier(),
                ),
            )
            ->latest()
            ->get();
        $visibleCandidateIds = (clone $candidateQuery)
            ->reorder()
            ->pluck('recruitment_candidates.id');
        $pipeline = collect(RecruitmentCandidate::STAGES)->mapWithKeys(
            fn (string $pipelineStage) => [
                $pipelineStage => RecruitmentCandidate::query()
                    ->whereIn('id', $visibleCandidateIds)
                    ->where('stage', $pipelineStage)
                    ->count(),
            ],
        );
        $upcomingInterviews = RecruitmentInterview::query()
            ->with('candidate:id,candidate_number,name,recruitment_requisition_id')
            ->whereIn('recruitment_candidate_id', $visibleCandidateIds)
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [now(), now()->addDays(14)])
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get()
            ->map(fn (RecruitmentInterview $interview) => [
                'id' => $interview->getKey(),
                'candidate_id' => $interview->recruitment_candidate_id,
                'candidate_name' => $interview->candidate?->name,
                'candidate_number' => $interview->candidate?->candidate_number,
                'round' => $interview->round,
                'type' => $interview->interview_type,
                'scheduled_at' => $interview->scheduled_at?->toIso8601String(),
                'location_or_link' => $interview->location_or_link,
            ]);

        return Inertia::render('Recruitment/Index', [
            'candidates' => $candidateQuery
                ->latest('applied_at')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (RecruitmentCandidate $candidate) => [
                    'id' => $candidate->getKey(),
                    'candidate_number' => $candidate->candidate_number,
                    'name' => $candidate->name,
                    'email' => $candidate->email,
                    'phone' => $candidate->phone,
                    'source' => $candidate->source,
                    'stage' => $candidate->stage,
                    'rating' => $candidate->rating === null
                        ? null
                        : (float) $candidate->rating,
                    'applied_at' => $candidate->applied_at?->toDateString(),
                    'requisition' => [
                        'id' => $candidate->requisition?->getKey(),
                        'code' => $candidate->requisition?->code,
                        'title' => $candidate->requisition?->title,
                    ],
                    'owner' => $candidate->owner?->name,
                    'offer_status' => $candidate->offers->sortByDesc('id')->first()?->status,
                    'onboarding_status' => $candidate->onboardingCase?->status,
                ]),
            'requisitions' => $requisitions->map(fn (RecruitmentRequisition $requisition) => [
                'id' => $requisition->getKey(),
                'code' => $requisition->code,
                'title' => $requisition->title,
                'department_id' => $requisition->department_id,
                'position_name' => $requisition->position_name,
                'employment_type' => $requisition->employment_type,
                'vacancies' => $requisition->vacancies,
                'hiring_manager_user_id' => $requisition->hiring_manager_user_id,
                'hiring_manager' => $requisition->hiringManager?->name,
                'location' => $requisition->location,
                'description' => $requisition->description,
                'requirements' => $requisition->requirements,
                'min_salary' => $requisition->min_salary === null
                    ? null
                    : (float) $requisition->min_salary,
                'max_salary' => $requisition->max_salary === null
                    ? null
                    : (float) $requisition->max_salary,
                'target_hire_date' => $requisition->target_hire_date?->toDateString(),
                'status' => $requisition->status,
                'approval_notes' => $requisition->approval_notes,
                'candidates_count' => $requisition->candidates_count,
            ]),
            'statistics' => [
                'open_requisitions' => $requisitions
                    ->whereIn('status', ['approved', 'published', 'on_hold'])
                    ->count(),
                'open_vacancies' => $requisitions
                    ->whereIn('status', ['approved', 'published'])
                    ->sum('vacancies'),
                'active_candidates' => $pipeline
                    ->only(['applied', 'screening', 'shortlisted', 'interview', 'offer'])
                    ->sum(),
                'interviews_14_days' => $upcomingInterviews->count(),
                'pending_offers' => RecruitmentOffer::query()
                    ->whereIn('recruitment_candidate_id', $visibleCandidateIds)
                    ->whereIn('status', ['draft', 'pending_approval', 'approved', 'sent'])
                    ->count(),
                'active_onboarding' => OnboardingCase::query()
                    ->whereHas(
                        'candidate',
                        fn (Builder $query) => $query->whereIn('id', $visibleCandidateIds),
                    )
                    ->whereIn('status', ['pending', 'active'])
                    ->count(),
            ],
            'pipeline' => $pipeline,
            'upcomingInterviews' => $upcomingInterviews,
            'departments' => $this->departments(),
            'positionNames' => $this->positionNames(),
            'users' => $this->recruitmentUsers(),
            'filters' => [
                'search' => $search,
                'stage' => $stage,
                'requisition_id' => $requisitionId ?: '',
            ],
            'permissions' => [
                'can_manage' => $user->hasPermission('recruitment.manage'),
                'can_approve' => $user->hasPermission('recruitment.approve'),
                'can_interview' => $user->hasPermission('recruitment.interview'),
            ],
        ]);
    }

    public function show(Request $request, RecruitmentCandidate $candidate): Response
    {
        abort_unless(
            RecruitmentAccess::canAccessCandidate($request->user(), $candidate),
            403,
        );
        $candidate->load([
            'requisition.hiringManager:id,name,email',
            'owner:id,name,email',
            'documents',
            'interviews.scorecards.panelUser:id,name,email',
            'offers',
            'onboardingCase.template:id,name',
            'onboardingCase.employeeRecord:id,directory_id,employee_number,official_email,status,start_date,office_location_id',
            'onboardingCase.employeeRecord.officeLocation:id,name',
            'onboardingCase.employeeUser:id,name,email',
            'onboardingCase.tasks.assignee:id,name,email',
        ]);
        $canManage = $request->user()->hasPermission('recruitment.manage');
        $isHiringManager = (int) $candidate->requisition?->hiring_manager_user_id
            === (int) $request->user()->getAuthIdentifier();
        $canSeeOffer = $canManage
            || $request->user()->hasPermission('recruitment.approve')
            || $isHiringManager;
        $panelIds = $candidate->interviews
            ->flatMap(fn (RecruitmentInterview $interview) => $interview->panel_user_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $panelMap = User::query()
            ->whereIn('id', $panelIds)
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        return Inertia::render('Recruitment/Show', [
            'candidate' => [
                'id' => $candidate->getKey(),
                'candidate_number' => $candidate->candidate_number,
                'name' => $candidate->name,
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'nric' => $canManage ? $candidate->nric : null,
                'current_company' => $candidate->current_company,
                'current_position' => $candidate->current_position,
                'expected_salary' => $candidate->expected_salary === null
                    ? null
                    : (float) $candidate->expected_salary,
                'notice_period_days' => $candidate->notice_period_days,
                'source' => $candidate->source,
                'stage' => $candidate->stage,
                'rating' => $candidate->rating === null
                    ? null
                    : (float) $candidate->rating,
                'screening_notes' => $candidate->screening_notes,
                'rejection_reason' => $candidate->rejection_reason,
                'withdrawal_reason' => $candidate->withdrawal_reason,
                'applied_at' => $candidate->applied_at?->toDateString(),
                'owner_user_id' => $candidate->owner_user_id,
                'owner' => $candidate->owner?->name,
                'requisition' => [
                    'id' => $candidate->requisition?->getKey(),
                    'code' => $candidate->requisition?->code,
                    'title' => $candidate->requisition?->title,
                    'department_id' => $candidate->requisition?->department_id,
                    'position_name' => $candidate->requisition?->position_name,
                    'employment_type' => $candidate->requisition?->employment_type,
                    'hiring_manager_user_id' => $candidate->requisition?->hiring_manager_user_id,
                    'hiring_manager' => $candidate->requisition?->hiringManager?->name,
                ],
                'documents' => $candidate->documents
                    ->when(
                        ! $canManage,
                        fn ($documents) => $documents->whereNotIn(
                            'document_type',
                            ['identity', 'offer_letter'],
                        ),
                    )
                    ->values()
                    ->map(fn (RecruitmentDocument $document) => [
                        'id' => $document->getKey(),
                        'document_type' => $document->document_type,
                        'original_name' => $document->original_name,
                        'mime_type' => $document->mime_type,
                        'size' => $document->size,
                        'created_at' => $document->created_at?->toIso8601String(),
                    ]),
                'interviews' => $candidate->interviews
                    ->sortByDesc('scheduled_at')
                    ->values()
                    ->map(fn (RecruitmentInterview $interview) => [
                        'id' => $interview->getKey(),
                        'round' => $interview->round,
                        'interview_type' => $interview->interview_type,
                        'scheduled_at' => $interview->scheduled_at?->format('Y-m-d\TH:i'),
                        'duration_minutes' => $interview->duration_minutes,
                        'location_or_link' => $interview->location_or_link,
                        'panel_user_ids' => array_map(
                            'intval',
                            $interview->panel_user_ids ?? [],
                        ),
                        'panel' => collect($interview->panel_user_ids ?? [])
                            ->map(fn ($id) => [
                                'id' => (int) $id,
                                'name' => $panelMap[(int) $id]?->name ?? 'Pengguna #'.$id,
                            ])
                            ->values(),
                        'status' => $interview->status,
                        'overall_score' => $interview->overall_score === null
                            ? null
                            : (float) $interview->overall_score,
                        'overall_recommendation' => $interview->overall_recommendation,
                        'notes' => $interview->notes,
                        'scorecards' => $interview->scorecards
                            ->when(
                                ! $canManage && $interview->status !== 'completed',
                                fn ($scorecards) => $scorecards->where(
                                    'panel_user_id',
                                    $request->user()->getAuthIdentifier(),
                                ),
                            )
                            ->values()
                            ->map(fn (RecruitmentScorecard $scorecard) => [
                                'id' => $scorecard->getKey(),
                                'panel_user_id' => $scorecard->panel_user_id,
                                'panel_name' => $scorecard->panelUser?->name,
                                'technical_score' => (float) $scorecard->technical_score,
                                'communication_score' => (float) $scorecard->communication_score,
                                'culture_score' => (float) $scorecard->culture_score,
                                'overall_score' => (float) $scorecard->overall_score,
                                'recommendation' => $scorecard->recommendation,
                                'strengths' => $scorecard->strengths,
                                'concerns' => $scorecard->concerns,
                                'comments' => $scorecard->comments,
                            ]),
                    ]),
                'offers' => $canSeeOffer
                    ? $candidate->offers
                        ->sortByDesc('id')
                        ->values()
                        ->map(fn (RecruitmentOffer $offer) => [
                        'id' => $offer->getKey(),
                        'offer_number' => $offer->offer_number,
                        'position_name' => $offer->position_name,
                        'department_id' => $offer->department_id,
                        'employment_type' => $offer->employment_type,
                        'salary' => (float) $offer->salary,
                        'start_date' => $offer->start_date?->toDateString(),
                        'probation_months' => $offer->probation_months,
                        'expiry_date' => $offer->expiry_date?->toDateString(),
                        'terms' => $offer->terms,
                        'status' => $offer->status,
                        'approval_notes' => $offer->approval_notes,
                            'response_notes' => $offer->response_notes,
                        ])
                    : [],
                'onboarding' => $canSeeOffer && $candidate->onboardingCase
                    ? $this->serializeOnboarding($candidate->onboardingCase)
                    : null,
            ],
            'users' => $this->recruitmentUsers(),
            'onboardingTemplates' => OnboardingTemplate::query()
                ->where('is_active', true)
                ->withCount('tasks')
                ->orderBy('name')
                ->get(['id', 'name', 'department_id', 'position_name'])
                ->map(fn (OnboardingTemplate $template) => [
                    'id' => $template->getKey(),
                    'name' => $template->name,
                    'department_id' => $template->department_id,
                    'position_name' => $template->position_name,
                    'tasks_count' => $template->tasks_count,
                ]),
            'permissions' => [
                'can_manage' => $request->user()->hasPermission('recruitment.manage'),
                'can_approve' => $request->user()->hasPermission('recruitment.approve'),
                'can_interview' => $request->user()->hasPermission('recruitment.interview'),
                'can_manage_onboarding' => $request->user()->hasPermission('onboarding.manage'),
            ],
        ]);
    }

    public function storeRequisition(Request $request): RedirectResponse
    {
        $validated = $this->validateRequisition($request);
        $requisition = RecruitmentRequisition::query()->create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'status' => 'draft',
            'created_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'recruitment.requisition_created',
            'recruitment_requisitions',
            $requisition->getKey(),
            newValues: $requisition->only([
                'code',
                'title',
                'department_id',
                'employment_type',
                'vacancies',
                'status',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Permohonan kekosongan jawatan disimpan sebagai Draf.',
        ]);
    }

    public function updateRequisition(
        Request $request,
        RecruitmentRequisition $requisition,
    ): RedirectResponse {
        if (! in_array($requisition->status, ['draft', 'approved'], true)) {
            throw ValidationException::withMessages([
                'requisition' => 'Hanya permohonan Draf atau Diluluskan boleh dikemas kini.',
            ]);
        }

        $old = $requisition->toArray();
        $validated = $this->validateRequisition($request, $requisition);
        $requisition->update([
            ...$validated,
            'code' => strtoupper($validated['code']),
        ]);
        AuditLogger::record(
            $request,
            'recruitment.requisition_updated',
            'recruitment_requisitions',
            $requisition->getKey(),
            oldValues: array_intersect_key($old, $validated),
            newValues: $requisition->only(array_keys($validated)),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Permohonan kekosongan jawatan telah dikemas kini.',
        ]);
    }

    public function changeRequisitionStatus(
        Request $request,
        RecruitmentRequisition $requisition,
    ): RedirectResponse {
        $validated = $request->validate([
            'action' => [
                'required',
                Rule::in(['submit', 'approve', 'reject', 'publish', 'hold', 'resume', 'close', 'cancel']),
            ],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $action = $validated['action'];

        if (in_array($action, ['approve', 'reject'], true)) {
            abort_unless($request->user()->hasPermission('recruitment.approve'), 403);

            if ((int) $requisition->created_by === (int) $request->user()->getAuthIdentifier()) {
                throw ValidationException::withMessages([
                    'action' => 'Penyedia permohonan kekosongan tidak boleh meluluskan atau menolak rekod yang sama.',
                ]);
            }
        } else {
            abort_unless($request->user()->hasPermission('recruitment.manage'), 403);
        }

        $transition = [
            'draft:submit' => 'pending_approval',
            'pending_approval:approve' => 'approved',
            'pending_approval:reject' => 'draft',
            'approved:publish' => 'published',
            'published:hold' => 'on_hold',
            'on_hold:resume' => 'published',
            'approved:close' => 'closed',
            'published:close' => 'closed',
            'on_hold:close' => 'closed',
            'draft:cancel' => 'cancelled',
            'pending_approval:cancel' => 'cancelled',
            'approved:cancel' => 'cancelled',
            'published:cancel' => 'cancelled',
            'on_hold:cancel' => 'cancelled',
        ][$requisition->status.':'.$action] ?? null;

        if (! $transition) {
            throw ValidationException::withMessages([
                'action' => 'Tindakan ini tidak sah untuk status semasa.',
            ]);
        }

        $old = $requisition->status;
        $requisition->update([
            'status' => $transition,
            'approval_notes' => $validated['notes'] ?? $requisition->approval_notes,
            'submitted_at' => $action === 'submit' ? now() : $requisition->submitted_at,
            'approved_by' => $action === 'approve'
                ? $request->user()->getAuthIdentifier()
                : $requisition->approved_by,
            'approved_at' => $action === 'approve' ? now() : $requisition->approved_at,
            'published_at' => in_array($action, ['publish', 'resume'], true)
                ? now()
                : $requisition->published_at,
            'closed_at' => in_array($action, ['close', 'cancel'], true)
                ? now()
                : $requisition->closed_at,
        ]);
        $this->notifyRequisitionParticipants(
            $requisition,
            "requisition_{$action}",
            'Status kekosongan jawatan dikemas kini',
            "{$requisition->code} · {$requisition->title} kini berstatus {$transition}.",
        );
        AuditLogger::record(
            $request,
            "recruitment.requisition_{$action}",
            'recruitment_requisitions',
            $requisition->getKey(),
            oldValues: ['status' => $old],
            newValues: ['status' => $transition, 'notes' => $validated['notes'] ?? null],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Status kekosongan jawatan telah dikemas kini.',
        ]);
    }

    public function storeCandidate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'recruitment_requisition_id' => [
                'required',
                'integer',
                Rule::exists('recruitment_requisitions', 'id')
                    ->where(fn ($query) => $query->whereIn('status', ['approved', 'published'])),
            ],
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:190',
                Rule::unique('recruitment_candidates', 'email')
                    ->where('recruitment_requisition_id', $request->integer('recruitment_requisition_id')),
            ],
            'phone' => ['required', 'string', 'max:40'],
            'nric' => ['nullable', 'string', 'max:30'],
            'current_company' => ['nullable', 'string', 'max:150'],
            'current_position' => ['nullable', 'string', 'max:150'],
            'expected_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'source' => ['required', Rule::in([
                'direct',
                'referral',
                'job_portal',
                'social_media',
                'agency',
                'career_fair',
                'other',
            ])],
            'owner_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'screening_notes' => ['nullable', 'string', 'max:5000'],
            'resume' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:5120'],
        ]);
        $candidate = DB::transaction(function () use ($request, $validated) {
            $candidate = RecruitmentCandidate::query()->create([
                ...collect($validated)->except('resume')->all(),
                'candidate_number' => $this->nextNumber('CAN'),
                'stage' => 'applied',
                'applied_at' => now(),
                'owner_user_id' => $validated['owner_user_id']
                    ?? $request->user()->getAuthIdentifier(),
            ]);

            if ($request->hasFile('resume')) {
                $this->storeDocument(
                    $request,
                    $candidate,
                    $request->file('resume'),
                    'resume',
                );
            }

            return $candidate;
        });
        AuditLogger::record(
            $request,
            'recruitment.candidate_created',
            'recruitment_candidates',
            $candidate->getKey(),
            newValues: $candidate->only([
                'candidate_number',
                'recruitment_requisition_id',
                'name',
                'email',
                'source',
                'stage',
            ]),
        );

        return redirect()
            ->route('recruitment.show', $candidate)
            ->with('toast', [
                'type' => 'success',
                'message' => 'Calon telah ditambah ke saluran pengambilan.',
            ]);
    }

    public function updateCandidate(
        Request $request,
        RecruitmentCandidate $candidate,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:190',
                Rule::unique('recruitment_candidates', 'email')
                    ->where('recruitment_requisition_id', $candidate->recruitment_requisition_id)
                    ->ignore($candidate),
            ],
            'phone' => ['required', 'string', 'max:40'],
            'nric' => ['nullable', 'string', 'max:30'],
            'current_company' => ['nullable', 'string', 'max:150'],
            'current_position' => ['nullable', 'string', 'max:150'],
            'expected_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'source' => ['required', Rule::in([
                'direct',
                'referral',
                'job_portal',
                'social_media',
                'agency',
                'career_fair',
                'other',
            ])],
            'owner_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'screening_notes' => ['nullable', 'string', 'max:5000'],
            'rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
        ]);
        $old = $candidate->only(array_keys($validated));
        $candidate->update($validated);
        AuditLogger::record(
            $request,
            'recruitment.candidate_updated',
            'recruitment_candidates',
            $candidate->getKey(),
            oldValues: $old,
            newValues: $candidate->only(array_keys($validated)),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Profil dan saringan calon telah dikemas kini.',
        ]);
    }

    public function updateCandidateStage(
        Request $request,
        RecruitmentCandidate $candidate,
    ): RedirectResponse {
        $validated = $request->validate([
            'stage' => [
                'required',
                Rule::in([
                    'applied',
                    'screening',
                    'shortlisted',
                    'interview',
                    'offer',
                    'rejected',
                    'withdrawn',
                ]),
            ],
            'reason' => [
                Rule::requiredIf(in_array($request->input('stage'), ['rejected', 'withdrawn'], true)),
                'nullable',
                'string',
                'max:3000',
            ],
        ]);
        $old = $candidate->stage;
        $candidate->update([
            'stage' => $validated['stage'],
            'rejection_reason' => $validated['stage'] === 'rejected'
                ? $validated['reason']
                : null,
            'withdrawal_reason' => $validated['stage'] === 'withdrawn'
                ? $validated['reason']
                : null,
        ]);
        AuditLogger::record(
            $request,
            'recruitment.candidate_stage_updated',
            'recruitment_candidates',
            $candidate->getKey(),
            oldValues: ['stage' => $old],
            newValues: [
                'stage' => $candidate->stage,
                'reason' => $validated['reason'] ?? null,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Peringkat calon telah dikemas kini.',
        ]);
    }

    public function uploadDocument(
        Request $request,
        RecruitmentCandidate $candidate,
    ): RedirectResponse {
        $validated = $request->validate([
            'document_type' => ['required', Rule::in([
                'resume',
                'cover_letter',
                'certificate',
                'identity',
                'reference',
                'offer_letter',
                'other',
            ])],
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:5120'],
        ]);
        $document = $this->storeDocument(
            $request,
            $candidate,
            $request->file('file'),
            $validated['document_type'],
        );
        AuditLogger::record(
            $request,
            'recruitment.document_uploaded',
            'recruitment_documents',
            $document->getKey(),
            newValues: $document->only([
                'recruitment_candidate_id',
                'document_type',
                'original_name',
                'mime_type',
                'size',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Dokumen calon telah dimuat naik secara persendirian.',
        ]);
    }

    public function downloadDocument(
        Request $request,
        RecruitmentCandidate $candidate,
        RecruitmentDocument $document,
    ) {
        abort_unless($document->recruitment_candidate_id === $candidate->getKey(), 404);
        abort_unless(
            RecruitmentAccess::canAccessCandidate($request->user(), $candidate),
            403,
        );
        abort_if(
            in_array($document->document_type, ['identity', 'offer_letter'], true)
            && ! $request->user()->hasPermission('recruitment.manage')
            && ! $request->user()->hasPermission('recruitment.approve'),
            403,
        );
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);

        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_name,
        );
    }

    public function deleteDocument(
        Request $request,
        RecruitmentCandidate $candidate,
        RecruitmentDocument $document,
    ): RedirectResponse {
        abort_unless($document->recruitment_candidate_id === $candidate->getKey(), 404);
        Storage::disk($document->disk)->delete($document->path);
        $old = $document->only(['document_type', 'original_name']);
        $document->delete();
        AuditLogger::record(
            $request,
            'recruitment.document_deleted',
            'recruitment_documents',
            $document->getKey(),
            oldValues: $old,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Dokumen calon telah dipadam.',
        ]);
    }

    public function scheduleInterview(
        Request $request,
        RecruitmentCandidate $candidate,
    ): RedirectResponse {
        $validated = $request->validate([
            'round' => ['required', 'integer', 'min:1', 'max:10'],
            'interview_type' => ['required', Rule::in([
                'phone',
                'video',
                'physical',
                'technical',
                'final',
            ])],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'location_or_link' => ['nullable', 'string', 'max:500'],
            'panel_user_ids' => ['required', 'array', 'min:1', 'max:10'],
            'panel_user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);
        $interview = $candidate->interviews()->create([
            ...$validated,
            'panel_user_ids' => array_map('intval', $validated['panel_user_ids']),
            'status' => 'scheduled',
            'created_by' => $request->user()->getAuthIdentifier(),
        ]);
        $candidate->update(['stage' => 'interview']);
        $this->notifyUsers(
            $validated['panel_user_ids'],
            'interview_scheduled',
            'Temu duga baharu dijadualkan',
            "{$candidate->name} · Pusingan {$interview->round} pada "
                .$interview->scheduled_at?->format('d/m/Y g:i A').'.',
            $candidate,
        );
        AuditLogger::record(
            $request,
            'recruitment.interview_scheduled',
            'recruitment_interviews',
            $interview->getKey(),
            newValues: $interview->only([
                'recruitment_candidate_id',
                'round',
                'interview_type',
                'scheduled_at',
                'panel_user_ids',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Temu duga telah dijadualkan dan panel dimaklumkan.',
        ]);
    }

    public function submitScorecard(
        Request $request,
        RecruitmentCandidate $candidate,
        RecruitmentInterview $interview,
    ): RedirectResponse {
        abort_unless($interview->recruitment_candidate_id === $candidate->getKey(), 404);
        $panelIds = array_map('intval', $interview->panel_user_ids ?? []);
        abort_unless(
            in_array((int) $request->user()->getAuthIdentifier(), $panelIds, true)
            || $request->user()->hasPermission('recruitment.manage'),
            403,
        );
        abort_if(in_array($interview->status, ['cancelled', 'no_show'], true), 422);
        $validated = $request->validate([
            'technical_score' => ['required', 'numeric', 'min:1', 'max:5'],
            'communication_score' => ['required', 'numeric', 'min:1', 'max:5'],
            'culture_score' => ['required', 'numeric', 'min:1', 'max:5'],
            'recommendation' => ['required', Rule::in(['strong_yes', 'yes', 'no', 'strong_no'])],
            'strengths' => ['required', 'string', 'max:3000'],
            'concerns' => ['nullable', 'string', 'max:3000'],
            'comments' => ['nullable', 'string', 'max:3000'],
        ]);
        $overall = round((
            (float) $validated['technical_score']
            + (float) $validated['communication_score']
            + (float) $validated['culture_score']
        ) / 3, 2);
        $scorecard = RecruitmentScorecard::query()->updateOrCreate(
            [
                'recruitment_interview_id' => $interview->getKey(),
                'panel_user_id' => $request->user()->getAuthIdentifier(),
            ],
            [
                ...$validated,
                'overall_score' => $overall,
                'submitted_at' => now(),
            ],
        );
        $scorecards = $interview->scorecards()->get();

        if ($scorecards->count() >= count($panelIds)) {
            $recommendationScore = (float) $scorecards
                ->avg(fn (RecruitmentScorecard $item) => match ($item->recommendation) {
                    'strong_yes' => 4,
                    'yes' => 3,
                    'no' => 2,
                    default => 1,
                });
            $recommendation = match (true) {
                $recommendationScore >= 3.5 => 'strong_yes',
                $recommendationScore >= 2.5 => 'yes',
                $recommendationScore >= 1.5 => 'no',
                default => 'strong_no',
            };
            $interview->update([
                'status' => 'completed',
                'overall_score' => round((float) $scorecards->avg('overall_score'), 2),
                'overall_recommendation' => $recommendation,
                'completed_at' => now(),
            ]);
        }
        AuditLogger::record(
            $request,
            'recruitment.scorecard_submitted',
            'recruitment_scorecards',
            $scorecard->getKey(),
            newValues: [
                'interview_id' => $interview->getKey(),
                'overall_score' => $overall,
                'recommendation' => $validated['recommendation'],
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Scorecard temu duga telah dihantar.',
        ]);
    }

    public function cancelInterview(
        Request $request,
        RecruitmentCandidate $candidate,
        RecruitmentInterview $interview,
    ): RedirectResponse {
        abort_unless($interview->recruitment_candidate_id === $candidate->getKey(), 404);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['cancelled', 'no_show'])],
            'notes' => ['required', 'string', 'max:3000'],
        ]);
        $old = $interview->status;
        $interview->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'],
        ]);
        AuditLogger::record(
            $request,
            'recruitment.interview_status_updated',
            'recruitment_interviews',
            $interview->getKey(),
            oldValues: ['status' => $old],
            newValues: $validated,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Status temu duga telah dikemas kini.',
        ]);
    }

    public function storeOffer(
        Request $request,
        RecruitmentCandidate $candidate,
    ): RedirectResponse {
        abort_if(
            $candidate->offers()->whereIn('status', [
                'draft',
                'pending_approval',
                'approved',
                'sent',
                'accepted',
            ])->exists(),
            422,
            'Calon ini mempunyai tawaran aktif.',
        );
        $validated = $request->validate([
            'position_name' => ['required', 'string', 'max:150'],
            'department_id' => ['nullable', 'integer'],
            'employment_type' => ['required', Rule::in([
                'permanent',
                'contract',
                'temporary',
                'internship',
            ])],
            'salary' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'probation_months' => ['required', 'integer', 'min:0', 'max:24'],
            'expiry_date' => ['required', 'date', 'after_or_equal:today', 'before:start_date'],
            'terms' => ['nullable', 'string', 'max:10000'],
        ]);
        $offer = DB::transaction(function () use ($request, $candidate, $validated) {
            $offer = $candidate->offers()->create([
                ...$validated,
                'offer_number' => $this->nextNumber('OFF'),
                'status' => 'draft',
                'created_by' => $request->user()->getAuthIdentifier(),
            ]);
            $candidate->update(['stage' => 'offer']);

            return $offer;
        });
        AuditLogger::record(
            $request,
            'recruitment.offer_created',
            'recruitment_offers',
            $offer->getKey(),
            newValues: $offer->only([
                'offer_number',
                'position_name',
                'employment_type',
                'salary',
                'start_date',
                'expiry_date',
                'status',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Draf tawaran pekerjaan telah disediakan.',
        ]);
    }

    public function changeOfferStatus(
        Request $request,
        RecruitmentCandidate $candidate,
        RecruitmentOffer $offer,
    ): RedirectResponse {
        abort_unless($offer->recruitment_candidate_id === $candidate->getKey(), 404);
        $validated = $request->validate([
            'action' => ['required', Rule::in([
                'submit',
                'approve',
                'reject',
                'send',
                'accept',
                'decline',
                'withdraw',
            ])],
            'notes' => ['nullable', 'string', 'max:3000'],
            'onboarding_template_id' => [
                'nullable',
                'integer',
                Rule::exists('onboarding_templates', 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],
        ]);
        $action = $validated['action'];

        if (in_array($action, ['approve', 'reject'], true)) {
            abort_unless($request->user()->hasPermission('recruitment.approve'), 403);

            if ((int) $offer->created_by === (int) $request->user()->getAuthIdentifier()) {
                throw ValidationException::withMessages([
                    'action' => 'Penyedia tawaran tidak boleh meluluskan atau menolak tawaran yang sama.',
                ]);
            }
        } else {
            abort_unless($request->user()->hasPermission('recruitment.manage'), 403);
        }

        $next = [
            'draft:submit' => 'pending_approval',
            'pending_approval:approve' => 'approved',
            'pending_approval:reject' => 'draft',
            'approved:send' => 'sent',
            'sent:accept' => 'accepted',
            'sent:decline' => 'declined',
            'approved:withdraw' => 'withdrawn',
            'sent:withdraw' => 'withdrawn',
        ][$offer->status.':'.$action] ?? null;

        if (! $next) {
            throw ValidationException::withMessages([
                'action' => 'Tindakan tawaran ini tidak sah untuk status semasa.',
            ]);
        }

        $old = $offer->status;
        DB::transaction(function () use (
            $request,
            $candidate,
            $offer,
            $validated,
            $action,
            $next,
        ) {
            $offer->update([
                'status' => $next,
                'approval_notes' => in_array($action, ['approve', 'reject'], true)
                    ? ($validated['notes'] ?? null)
                    : $offer->approval_notes,
                'response_notes' => in_array($action, ['accept', 'decline'], true)
                    ? ($validated['notes'] ?? null)
                    : $offer->response_notes,
                'submitted_at' => $action === 'submit' ? now() : $offer->submitted_at,
                'approved_by' => $action === 'approve'
                    ? $request->user()->getAuthIdentifier()
                    : $offer->approved_by,
                'approved_at' => $action === 'approve' ? now() : $offer->approved_at,
                'sent_at' => $action === 'send' ? now() : $offer->sent_at,
                'responded_at' => in_array($action, ['accept', 'decline'], true)
                    ? now()
                    : $offer->responded_at,
            ]);

            if ($action === 'accept') {
                $candidate->update(['stage' => 'hired', 'hired_at' => now()]);
                $this->createOnboarding(
                    $request,
                    $candidate,
                    $offer,
                    isset($validated['onboarding_template_id'])
                        ? (int) $validated['onboarding_template_id']
                        : null,
                );
            } elseif ($action === 'decline') {
                $candidate->update([
                    'stage' => 'withdrawn',
                    'withdrawal_reason' => $validated['notes'] ?? 'Tawaran ditolak.',
                ]);
            }
        });
        AuditLogger::record(
            $request,
            "recruitment.offer_{$action}",
            'recruitment_offers',
            $offer->getKey(),
            oldValues: ['status' => $old],
            newValues: ['status' => $next, 'notes' => $validated['notes'] ?? null],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $action === 'accept'
                ? 'Tawaran diterima dan kes onboarding telah dijana.'
                : 'Status tawaran pekerjaan telah dikemas kini.',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = RecruitmentCandidate::query()->with('requisition:id,code,title');
        $this->scopeCandidatesForUser($query, $request->user());

        return response()->streamDownload(function () use ($query) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, [
                'No. Calon',
                'Nama',
                'E-mel',
                'Telefon',
                'Kekosongan',
                'Peringkat',
                'Sumber',
                'Rating',
                'Tarikh Mohon',
            ]);
            $query->orderBy('id')->chunkById(200, function ($candidates) use ($stream) {
                foreach ($candidates as $candidate) {
                    fputcsv($stream, [
                        $candidate->candidate_number,
                        $candidate->name,
                        $candidate->email,
                        $candidate->phone,
                        $candidate->requisition?->code.' · '.$candidate->requisition?->title,
                        $candidate->stage,
                        $candidate->source,
                        $candidate->rating,
                        $candidate->applied_at?->toDateString(),
                    ]);
                }
            });
            fclose($stream);
        }, 'laporan-pengambilan-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function readNotifications(Request $request): RedirectResponse
    {
        RecruitmentNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    private function validateRequisition(
        Request $request,
        ?RecruitmentRequisition $requisition = null,
    ): array {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('recruitment_requisitions', 'code')->ignore($requisition),
            ],
            'title' => ['required', 'string', 'max:150'],
            'department_id' => ['nullable', 'integer'],
            'position_name' => ['nullable', 'string', 'max:150'],
            'employment_type' => ['required', Rule::in([
                'permanent',
                'contract',
                'temporary',
                'internship',
            ])],
            'vacancies' => ['required', 'integer', 'min:1', 'max:999'],
            'hiring_manager_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'location' => ['nullable', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:10000'],
            'requirements' => ['required', 'string', 'max:10000'],
            'min_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'max_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'target_hire_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        if (
            isset($validated['min_salary'], $validated['max_salary'])
            && (float) $validated['max_salary'] < (float) $validated['min_salary']
        ) {
            throw ValidationException::withMessages([
                'max_salary' => 'Gaji maksimum mestilah sama atau melebihi gaji minimum.',
            ]);
        }

        return $validated;
    }

    private function scopeCandidatesForUser(Builder $query, User $user): void
    {
        if (
            $user->hasPermission('recruitment.manage')
            || $user->hasPermission('recruitment.approve')
        ) {
            return;
        }

        $userId = (int) $user->getAuthIdentifier();
        $query->where(function (Builder $query) use ($userId) {
            $query
                ->where('owner_user_id', $userId)
                ->orWhereHas(
                    'requisition',
                    fn (Builder $query) => $query->where(
                        'hiring_manager_user_id',
                        $userId,
                    ),
                )
                ->orWhereHas(
                    'interviews',
                    fn (Builder $query) => $query->whereJsonContains(
                        'panel_user_ids',
                        $userId,
                    ),
                );
        });
    }

    private function nextNumber(string $prefix): string
    {
        $date = now()->format('Ym');
        $model = $prefix === 'OFF' ? RecruitmentOffer::class : RecruitmentCandidate::class;
        $column = $prefix === 'OFF' ? 'offer_number' : 'candidate_number';
        $last = $model::query()
            ->where($column, 'like', "{$prefix}-{$date}-%")
            ->lockForUpdate()
            ->orderByDesc($column)
            ->value($column);
        $sequence = $last ? ((int) Str::afterLast($last, '-') + 1) : 1;

        return sprintf('%s-%s-%04d', $prefix, $date, $sequence);
    }

    private function storeDocument(
        Request $request,
        RecruitmentCandidate $candidate,
        $file,
        string $type,
    ): RecruitmentDocument {
        $path = $file->storeAs(
            "private/recruitment/{$candidate->getKey()}",
            Str::uuid().'.'.$file->getClientOriginalExtension(),
            'local',
        );

        return $candidate->documents()->create([
            'document_type' => $type,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->getAuthIdentifier(),
        ]);
    }

    private function createOnboarding(
        Request $request,
        RecruitmentCandidate $candidate,
        RecruitmentOffer $offer,
        ?int $templateId,
    ): OnboardingCase {
        $existing = $candidate->onboardingCase;

        if ($existing) {
            return $existing;
        }

        $template = $templateId
            ? OnboardingTemplate::query()
                ->whereKey($templateId)
                ->where('is_active', true)
                ->with('tasks')
                ->first()
            : $this->matchingOnboardingTemplate($offer);
        $case = $candidate->onboardingCase()->create([
            'recruitment_offer_id' => $offer->getKey(),
            'onboarding_template_id' => $template?->getKey(),
            'manager_user_id' => $candidate->requisition?->hiring_manager_user_id,
            'start_date' => $offer->start_date,
            'status' => 'pending',
            'created_by' => $request->user()->getAuthIdentifier(),
        ]);
        $template?->tasks->each(function ($task) use ($case, $offer) {
            $case->tasks()->create([
                'title' => $task->title,
                'description' => $task->description,
                'category' => $task->category,
                'assignee_role' => $task->assignee_role,
                'assignee_user_id' => $task->assignee_role === 'supervisor'
                    ? $case->manager_user_id
                    : null,
                'due_date' => Carbon::parse($offer->start_date)
                    ->addDays($task->due_offset_days),
                'is_required' => $task->is_required,
                'status' => 'pending',
                'sort_order' => $task->sort_order,
            ]);
        });

        return $case;
    }

    private function matchingOnboardingTemplate(RecruitmentOffer $offer): ?OnboardingTemplate
    {
        return OnboardingTemplate::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($offer) {
                $query->whereNull('department_id')
                    ->orWhere('department_id', $offer->department_id);
            })
            ->where(function (Builder $query) use ($offer) {
                $query->whereNull('position_name')
                    ->orWhere('position_name', $offer->position_name);
            })
            ->with('tasks')
            ->orderByRaw(
                'CASE WHEN department_id = ? AND position_name = ? THEN 1
                    WHEN department_id = ? AND position_name IS NULL THEN 2
                    WHEN department_id IS NULL AND position_name = ? THEN 3
                    ELSE 4 END',
                [
                    $offer->department_id,
                    $offer->position_name,
                    $offer->department_id,
                    $offer->position_name,
                ],
            )
            ->first();
    }

    private function serializeOnboarding(OnboardingCase $case): array
    {
        $tasks = $case->tasks;
        $completed = $tasks->whereIn('status', ['completed', 'waived'])->count();

        return [
            'id' => $case->getKey(),
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
            'manager_user_id' => $case->manager_user_id,
            'buddy_user_id' => $case->buddy_user_id,
            'start_date' => $case->start_date?->toDateString(),
            'status' => $case->status,
            'notes' => $case->notes,
            'progress' => $tasks->count() > 0
                ? (int) round(($completed / $tasks->count()) * 100)
                : 0,
            'tasks' => $tasks->map(fn ($task) => [
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

    private function notifyRequisitionParticipants(
        RecruitmentRequisition $requisition,
        string $type,
        string $title,
        string $message,
    ): void {
        $userIds = collect([$requisition->hiring_manager_user_id])
            ->merge(
                User::query()
                    ->with('roleAssignments')
                    ->get()
                    ->filter(fn (User $user) => $user->hasPermission('recruitment.manage'))
                    ->pluck('id'),
            )
            ->filter()
            ->unique()
            ->all();
        $this->notifyUsers($userIds, $type, $title, $message, requisition: $requisition);
    }

    private function notifyUsers(
        array $userIds,
        string $type,
        string $title,
        string $message,
        ?RecruitmentCandidate $candidate = null,
        ?RecruitmentRequisition $requisition = null,
    ): void {
        collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->each(fn (int $userId) => RecruitmentNotification::query()->create([
                'user_id' => $userId,
                'recruitment_candidate_id' => $candidate?->getKey(),
                'recruitment_requisition_id' => $requisition?->getKey(),
                'type' => $type,
                'title' => $title,
                'message' => $message,
            ]));
    }

    private function departments()
    {
        return DB::connection('ibco')
            ->table('xdepartment')
            ->where('rcd_enable', 1)
            ->orderBy('description')
            ->get(['id', 'description as name']);
    }

    private function positionNames()
    {
        return DB::connection('ibco')
            ->table('maklumatjawatan')
            ->where('rcd_enable', 1)
            ->whereNotNull('jawatan')
            ->where('jawatan', '<>', '')
            ->distinct()
            ->orderBy('jawatan')
            ->pluck('jawatan');
    }

    private function recruitmentUsers()
    {
        return User::query()
            ->with('roleAssignments')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->hasRole(UserRole::HrAdmin)
                || $user->hasRole(UserRole::Supervisor))
            ->values()
            ->map(fn (User $user) => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roleValues(),
            ]);
    }
}
