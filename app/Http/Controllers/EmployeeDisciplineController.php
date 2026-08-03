<?php

namespace App\Http\Controllers;

use App\Models\ComplaintCategory;
use App\Models\DisciplineAppeal;
use App\Models\DisciplineAttachment;
use App\Models\DisciplineCase;
use App\Models\DisciplineCaseEvent;
use App\Models\DisciplineNotification;
use App\Models\DisciplineResponse;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\TrainingEmployeeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmployeeDisciplineController extends Controller
{
    public function __construct(
        private readonly TrainingEmployeeResolver $employees,
    ) {}

    public function index(Request $request): Response
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $complaints = DisciplineCase::query()
            ->where('complainant_user_id', $userId)
            ->with([
                'category:id,code,name',
                'events' => fn ($query) => $query
                    ->where('visible_to_complainant', true)
                    ->with('creator:id,name')
                    ->latest('occurred_at'),
                'attachments' => fn ($query) => $query
                    ->where('visible_to_complainant', true)
                    ->latest(),
            ])
            ->latest()
            ->get()
            ->map(fn (DisciplineCase $case) => $this->complainantPayload($case));
        $subjectCases = DisciplineCase::query()
            ->where('subject_user_id', $userId)
            ->whereNotIn('status', [
                'submitted', 'triage', 'show_cause_pending', 'withdrawn', 'dismissed',
            ])
            ->with([
                'category:id,code,name',
                'events' => fn ($query) => $query
                    ->where('visible_to_subject', true)
                    ->with('creator:id,name')
                    ->latest('occurred_at'),
                'attachments' => fn ($query) => $query
                    ->where('visible_to_subject', true)
                    ->latest(),
                'responses' => fn ($query) => $query
                    ->where('user_id', $userId)
                    ->latest(),
                'appeals' => fn ($query) => $query
                    ->where('appellant_user_id', $userId)
                    ->latest(),
                'hrDocument:id,reference_number,status,category',
            ])
            ->latest()
            ->get()
            ->map(fn (DisciplineCase $case) => $this->subjectPayload($case));
        $notifications = DisciplineNotification::query()
            ->where('user_id', $userId)
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('EmployeeSelfService/Complaints', [
            'categories' => ComplaintCategory::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get([
                    'id', 'code', 'name', 'description', 'default_severity',
                    'allow_protected_identity',
                ]),
            'employees' => $this->employees->linkedOptions()
                ->reject(fn (array $employee) => (int) $employee['user_id'] === $userId)
                ->values(),
            'complaints' => $complaints,
            'subjectCases' => $subjectCases,
            'notifications' => $notifications->map(fn (DisciplineNotification $notification) => [
                'id' => $notification->getKey(),
                'case_id' => $notification->discipline_case_id,
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
            'complaint_category_id' => [
                'required', 'integer',
                Rule::exists('complaint_categories', 'id')->where('is_active', true),
            ],
            'subject_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'subject_name' => ['nullable', 'string', 'max:180', 'required_without:subject_user_id'],
            'title' => ['required', 'string', 'min:5', 'max:240'],
            'incident_at' => ['nullable', 'date', 'before_or_equal:now'],
            'incident_location' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:20000'],
            'requested_resolution' => ['nullable', 'string', 'max:5000'],
            'identity_protected' => ['required', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);
        $complainant = $this->employees->forUser($request->user());
        if (! $complainant) {
            throw ValidationException::withMessages([
                'employee' => 'Akaun anda belum dipautkan kepada rekod pekerja aktif.',
            ]);
        }
        $category = ComplaintCategory::query()->findOrFail($validated['complaint_category_id']);
        $subject = isset($validated['subject_user_id'])
            ? $this->employees->forUser((int) $validated['subject_user_id'])
            : null;
        if (isset($validated['subject_user_id']) && ! $subject) {
            throw ValidationException::withMessages([
                'subject_user_id' => 'Pekerja yang dipilih tidak mempunyai pautan aktif.',
            ]);
        }
        if ((int) ($validated['subject_user_id'] ?? 0) === (int) $request->user()->getAuthIdentifier()) {
            throw ValidationException::withMessages([
                'subject_user_id' => 'Aduan tidak boleh ditujukan kepada akaun sendiri.',
            ]);
        }
        $employeeOptions = $this->employees->linkedOptions();
        $complainantOption = $employeeOptions->firstWhere(
            'user_id',
            (int) $request->user()->getAuthIdentifier(),
        );
        $subjectOption = isset($validated['subject_user_id'])
            ? $employeeOptions->firstWhere('user_id', (int) $validated['subject_user_id'])
            : null;

        $case = DB::transaction(function () use (
            $request,
            $validated,
            $category,
            $complainant,
            $complainantOption,
            $subject,
            $subjectOption,
        ) {
            $case = DisciplineCase::query()->create([
                'case_number' => 'PENDING-'.Str::uuid(),
                'complaint_category_id' => $category->getKey(),
                'complainant_user_id' => $request->user()->getAuthIdentifier(),
                'complainant_employee_id' => $complainant->id,
                'complainant_employee_number' => $complainant->employee_number,
                'complainant_name' => $complainant->name,
                'complainant_email' => $complainantOption['email'] ?? null,
                'complainant_department_id' => $complainant->department_id,
                'complainant_department_name' => $complainant->department_name,
                'identity_protected' => $category->allow_protected_identity
                    && (bool) $validated['identity_protected'],
                'subject_user_id' => $validated['subject_user_id'] ?? null,
                'subject_employee_id' => $subject?->id,
                'subject_employee_number' => $subject?->employee_number,
                'subject_name' => $subject?->name ?? $validated['subject_name'],
                'subject_email' => $subjectOption['email'] ?? null,
                'subject_department_id' => $subject?->department_id,
                'subject_department_name' => $subject?->department_name,
                'subject_position_name' => $subject?->position_name,
                'title' => $validated['title'],
                'incident_at' => $validated['incident_at'] ?? null,
                'incident_location' => $validated['incident_location'] ?? null,
                'description' => $validated['description'],
                'requested_resolution' => $validated['requested_resolution'] ?? null,
                'severity' => $category->default_severity,
                'confidentiality' => 'restricted',
                'status' => 'submitted',
                'created_by' => $request->user()->getAuthIdentifier(),
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
            $case->update([
                'case_number' => sprintf('D&A/%s/%06d', now()->format('Y'), $case->getKey()),
            ]);
            DisciplineCaseEvent::query()->create([
                'discipline_case_id' => $case->getKey(),
                'event_type' => 'complaint_submitted',
                'title' => 'Aduan dihantar',
                'details' => 'Aduan diterima dan menunggu triage HR.',
                'occurred_at' => now(),
                'visible_to_complainant' => true,
                'visible_to_subject' => false,
                'created_by' => $request->user()->getAuthIdentifier(),
            ]);

            foreach ($request->file('attachments', []) as $file) {
                $this->storeAttachment($case, $file, $request->user()->getAuthIdentifier());
            }

            return $case;
        });
        $this->notifyPermissionUsers(
            'discipline.manage',
            $case,
            'complaint_submitted',
            'Aduan disiplin baharu',
            "Kes {$case->case_number} memerlukan triage sulit.",
        );
        AuditLogger::record(
            $request,
            'discipline.complaint_submitted',
            'discipline_cases',
            $case->getKey(),
            newValues: [
                'case_number' => $case->case_number,
                'category_id' => $case->complaint_category_id,
                'severity' => $case->severity,
                'identity_protected' => $case->identity_protected,
                'status' => $case->status,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => "Aduan {$case->case_number} telah dihantar secara sulit.",
        ]);
    }

    public function withdraw(Request $request, DisciplineCase $case): RedirectResponse
    {
        $this->authorizeComplainant($request, $case);
        if ($case->status !== 'submitted') {
            throw ValidationException::withMessages([
                'status' => 'Aduan hanya boleh ditarik balik sebelum proses triage bermula.',
            ]);
        }
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $case->update([
            'status' => 'withdrawn',
            'closure_reason' => $validated['reason'],
            'closed_by' => $request->user()->getAuthIdentifier(),
            'closed_at' => now(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        DisciplineCaseEvent::query()->create([
            'discipline_case_id' => $case->getKey(),
            'event_type' => 'complaint_withdrawn',
            'title' => 'Aduan ditarik balik',
            'details' => 'Aduan ditarik balik oleh pengadu.',
            'occurred_at' => now(),
            'visible_to_complainant' => true,
            'visible_to_subject' => false,
            'created_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'discipline.complaint_withdrawn',
            'discipline_cases',
            $case->getKey(),
            oldValues: ['status' => 'submitted'],
            newValues: ['status' => 'withdrawn'],
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Aduan telah ditarik balik.']);
    }

    public function submitResponse(Request $request, DisciplineCase $case): RedirectResponse
    {
        $this->authorizeSubject($request, $case);
        if ($case->status !== 'show_cause') {
            throw ValidationException::withMessages([
                'status' => 'Kes ini tidak sedang menunggu jawapan tunjuk sebab.',
            ]);
        }
        if ($case->show_cause_due_at?->isPast()) {
            throw ValidationException::withMessages([
                'status' => 'Tarikh akhir jawapan tunjuk sebab telah berlalu. Hubungi HR.',
            ]);
        }
        $validated = $request->validate([
            'statement' => ['required', 'string', 'min:20', 'max:20000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);
        DB::transaction(function () use ($request, $case, $validated) {
            DisciplineResponse::query()->updateOrCreate(
                [
                    'discipline_case_id' => $case->getKey(),
                    'user_id' => $request->user()->getAuthIdentifier(),
                    'response_type' => 'show_cause',
                ],
                [
                    'statement' => $validated['statement'],
                    'is_confidential' => true,
                    'submitted_at' => now(),
                    'created_by' => $request->user()->getAuthIdentifier(),
                ],
            );
            foreach ($request->file('attachments', []) as $file) {
                $this->storeAttachment(
                    $case,
                    $file,
                    $request->user()->getAuthIdentifier(),
                    'show_cause',
                    false,
                    true,
                );
            }
            $case->update([
                'status' => 'decision',
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
            DisciplineCaseEvent::query()->create([
                'discipline_case_id' => $case->getKey(),
                'event_type' => 'show_cause_response',
                'title' => 'Jawapan tunjuk sebab diterima',
                'details' => 'Jawapan pekerja diterima dan menunggu keputusan.',
                'occurred_at' => now(),
                'visible_to_complainant' => false,
                'visible_to_subject' => true,
                'created_by' => $request->user()->getAuthIdentifier(),
            ]);
        });
        $this->notifyPermissionUsers(
            'discipline.approve',
            $case,
            'response_received',
            'Jawapan tunjuk sebab diterima',
            "Kes {$case->case_number} sedia untuk keputusan tatatertib.",
        );
        AuditLogger::record(
            $request,
            'discipline.show_cause_response_submitted',
            'discipline_cases',
            $case->getKey(),
            oldValues: ['status' => 'show_cause'],
            newValues: ['status' => 'decision', 'response_received' => true],
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Jawapan tunjuk sebab telah dihantar.']);
    }

    public function appeal(Request $request, DisciplineCase $case): RedirectResponse
    {
        $this->authorizeSubject($request, $case);
        if ($case->status !== 'decision' || ! $case->decided_at) {
            throw ValidationException::withMessages([
                'status' => 'Kes ini belum mempunyai keputusan yang boleh dirayu.',
            ]);
        }
        if (! $case->appeal_deadline || $case->appeal_deadline->isBefore(today())) {
            throw ValidationException::withMessages([
                'status' => 'Tempoh rayuan bagi kes ini telah tamat.',
            ]);
        }
        if ($case->appeals()->where('appellant_user_id', $request->user()->getAuthIdentifier())->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Rayuan bagi kes ini telah dihantar.',
            ]);
        }
        $validated = $request->validate([
            'grounds' => ['required', 'string', 'min:20', 'max:20000'],
            'desired_outcome' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:10240'],
        ]);
        $appeal = DB::transaction(function () use ($request, $case, $validated) {
            $appeal = DisciplineAppeal::query()->create([
                'discipline_case_id' => $case->getKey(),
                'appellant_user_id' => $request->user()->getAuthIdentifier(),
                'grounds' => $validated['grounds'],
                'desired_outcome' => $validated['desired_outcome'] ?? null,
                'status' => 'pending',
            ]);
            foreach ($request->file('attachments', []) as $file) {
                $this->storeAttachment(
                    $case,
                    $file,
                    $request->user()->getAuthIdentifier(),
                    'appeal',
                    false,
                    true,
                );
            }
            $case->update([
                'status' => 'appeal',
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
            DisciplineCaseEvent::query()->create([
                'discipline_case_id' => $case->getKey(),
                'event_type' => 'appeal_submitted',
                'title' => 'Rayuan dihantar',
                'details' => 'Rayuan diterima dan menunggu panel semakan.',
                'occurred_at' => now(),
                'visible_to_complainant' => false,
                'visible_to_subject' => true,
                'created_by' => $request->user()->getAuthIdentifier(),
            ]);

            return $appeal;
        });
        $this->notifyPermissionUsers(
            'discipline.approve',
            $case,
            'appeal_submitted',
            'Rayuan tatatertib baharu',
            "Rayuan bagi {$case->case_number} memerlukan semakan bebas.",
        );
        AuditLogger::record(
            $request,
            'discipline.appeal_submitted',
            'discipline_appeals',
            $appeal->getKey(),
            newValues: ['case_number' => $case->case_number, 'status' => 'pending'],
        );

        return back()->with('toast', ['type' => 'success', 'message' => 'Rayuan telah dihantar untuk semakan.']);
    }

    public function downloadAttachment(
        Request $request,
        DisciplineCase $case,
        DisciplineAttachment $attachment,
    ): HttpResponse {
        abort_unless($attachment->discipline_case_id === $case->getKey(), 404);
        $isComplainant = (int) $case->complainant_user_id === (int) $request->user()->getAuthIdentifier();
        $isSubject = (int) $case->subject_user_id === (int) $request->user()->getAuthIdentifier();
        abort_unless(
            ($isComplainant && $attachment->visible_to_complainant)
                || ($isSubject && $attachment->visible_to_subject),
            403,
        );
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
            ['Cache-Control' => 'private, no-store'],
        );
    }

    public function readNotifications(Request $request): RedirectResponse
    {
        DisciplineNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    private function authorizeComplainant(Request $request, DisciplineCase $case): void
    {
        abort_unless(
            (int) $case->complainant_user_id === (int) $request->user()->getAuthIdentifier(),
            403,
        );
    }

    private function authorizeSubject(Request $request, DisciplineCase $case): void
    {
        abort_unless(
            (int) $case->subject_user_id === (int) $request->user()->getAuthIdentifier(),
            403,
        );
    }

    private function storeAttachment(
        DisciplineCase $case,
        mixed $file,
        int $userId,
        string $context = 'complaint',
        bool $visibleToComplainant = true,
        bool $visibleToSubject = false,
    ): DisciplineAttachment {
        $path = $file->store("discipline/{$case->getKey()}", 'local');

        return DisciplineAttachment::query()->create([
            'discipline_case_id' => $case->getKey(),
            'uploaded_by' => $userId,
            'attachment_context' => $context,
            'original_name' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'visible_to_complainant' => $visibleToComplainant,
            'visible_to_subject' => $visibleToSubject,
        ]);
    }

    private function notifyPermissionUsers(
        string $permission,
        DisciplineCase $case,
        string $type,
        string $title,
        string $message,
    ): void {
        User::query()
            ->with('roleAssignments')
            ->get()
            ->filter(fn (User $user) => $user->hasPermission($permission))
            ->each(fn (User $user) => DisciplineNotification::query()->create([
                'user_id' => $user->getKey(),
                'discipline_case_id' => $case->getKey(),
                'type' => $type,
                'title' => $title,
                'message' => $message,
            ]));
    }

    private function complainantPayload(DisciplineCase $case): array
    {
        return [
            ...$case->only([
                'id', 'case_number', 'title', 'subject_name', 'incident_at',
                'incident_location', 'description', 'requested_resolution',
                'identity_protected', 'severity', 'status', 'triage_notes',
                'finding_outcome', 'decision_outcome', 'created_at', 'closed_at',
                'closure_reason',
            ]),
            'category' => $case->category?->name,
            'events' => $case->events->map(fn (DisciplineCaseEvent $event) => [
                ...$event->only(['id', 'event_type', 'title', 'details', 'occurred_at']),
                'creator' => $event->creator?->name,
            ]),
            'attachments' => $case->attachments->map(fn (DisciplineAttachment $attachment) => $this->attachmentPayload($attachment)),
        ];
    }

    private function subjectPayload(DisciplineCase $case): array
    {
        return [
            ...$case->only([
                'id', 'case_number', 'title', 'severity', 'status',
                'allegation_summary', 'show_cause_due_at', 'decision_outcome',
                'decision_notes', 'decided_at', 'effective_date',
                'appeal_deadline', 'closed_at', 'closure_reason',
            ]),
            'category' => $case->category?->name,
            'events' => $case->events->map(fn (DisciplineCaseEvent $event) => [
                ...$event->only(['id', 'event_type', 'title', 'details', 'occurred_at']),
                'creator' => $event->creator?->name,
            ]),
            'attachments' => $case->attachments->map(fn (DisciplineAttachment $attachment) => $this->attachmentPayload($attachment)),
            'response' => $case->responses->first()?->only(['id', 'statement', 'submitted_at']),
            'appeal' => $case->appeals->first()?->only([
                'id', 'grounds', 'desired_outcome', 'status', 'reviewed_at',
                'decision_notes', 'revised_outcome',
            ]),
            'hr_document' => $case->hrDocument?->only([
                'id', 'reference_number', 'status', 'category',
            ]),
        ];
    }

    private function attachmentPayload(DisciplineAttachment $attachment): array
    {
        return $attachment->only([
            'id', 'attachment_context', 'original_name', 'mime_type', 'size',
            'created_at',
        ]);
    }
}
