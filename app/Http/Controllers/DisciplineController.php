<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\ComplaintCategory;
use App\Models\DisciplineAppeal;
use App\Models\DisciplineAttachment;
use App\Models\DisciplineCase;
use App\Models\DisciplineCaseEvent;
use App\Models\DisciplineCaseMember;
use App\Models\DisciplineNotification;
use App\Models\DocumentTemplate;
use App\Models\HrDocument;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DisciplineController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(DisciplineCase::STATUSES)],
            'severity' => ['nullable', Rule::in(ComplaintCategory::SEVERITIES)],
            'category_id' => ['nullable', 'integer', 'exists:complaint_categories,id'],
            'case_id' => ['nullable', 'integer', 'exists:discipline_cases,id'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $base = $this->visibleQuery($request);
        $hasGovernanceAccess = $request->user()->hasPermission('discipline.manage')
            || $request->user()->hasPermission('discipline.approve');
        $filtered = (clone $base)
            ->with([
                'category:id,code,name',
                'investigator:id,name,email',
                'members:id,discipline_case_id,user_id,conflict_declared,has_conflict,recused_at',
            ])
            ->when($search !== '', function (Builder $query) use (
                $request,
                $search,
                $hasGovernanceAccess,
            ) {
                $query->where(function (Builder $query) use (
                    $request,
                    $search,
                    $hasGovernanceAccess,
                ) {
                    $query->where('case_number', 'like', "%{$search}%");
                    if ($hasGovernanceAccess) {
                        $query
                            ->orWhere('title', 'like', "%{$search}%")
                            ->orWhere('subject_name', 'like', "%{$search}%")
                            ->orWhere('complainant_name', 'like', "%{$search}%");

                        return;
                    }
                    $query->orWhere(fn (Builder $query) => $query
                        ->whereHas('members', fn (Builder $query) => $query
                            ->where('user_id', $request->user()->getAuthIdentifier())
                            ->where('conflict_declared', true)
                            ->where('has_conflict', false)
                            ->whereNull('recused_at'))
                        ->where(fn (Builder $query) => $query
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('subject_name', 'like', "%{$search}%")
                            ->orWhere(fn (Builder $query) => $query
                                ->where('complainant_name', 'like', "%{$search}%")
                                ->where('identity_protected', false))));
                });
            })
            ->when(
                filled($filters['status'] ?? null),
                fn (Builder $query) => $query->where('status', $filters['status']),
            )
            ->when(
                filled($filters['severity'] ?? null),
                fn (Builder $query) => $query->where('severity', $filters['severity']),
            )
            ->when(
                filled($filters['category_id'] ?? null),
                fn (Builder $query) => $query->where('complaint_category_id', $filters['category_id']),
            );
        $cases = $filtered->latest()->paginate(20)->withQueryString();
        $selectedId = (int) ($filters['case_id']
            ?? (collect($cases->items())->first()?->getKey() ?? 0));
        $selected = $selectedId > 0
            ? (clone $base)
                ->with([
                    'category', 'complainant:id,name,email', 'subject:id,name,email',
                    'investigator:id,name,email', 'hrDocument:id,reference_number,status,category',
                    'members.user:id,name,email', 'events.creator:id,name',
                    'attachments', 'responses.user:id,name', 'appeals.appellant:id,name',
                ])
                ->whereKey($selectedId)
                ->first()
            : null;
        $notifications = DisciplineNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->latest()
            ->limit(20)
            ->get();
        $now = now();

        return Inertia::render('Discipline/Index', [
            'cases' => $cases->through(fn (DisciplineCase $case) => $this->listPayload($request, $case)),
            'selectedCase' => $selected ? $this->detailPayload($request, $selected) : null,
            'filters' => [
                'search' => $search,
                'status' => (string) ($filters['status'] ?? ''),
                'severity' => (string) ($filters['severity'] ?? ''),
                'category_id' => (string) ($filters['category_id'] ?? ''),
            ],
            'categories' => ComplaintCategory::query()->orderBy('name')->get(),
            'officers' => User::query()
                ->with('roleAssignments')
                ->orderBy('name')
                ->get()
                ->filter(fn (User $user) => $user->hasPermission('discipline.investigate'))
                ->map(fn (User $user) => $user->only(['id', 'name', 'email']))
                ->values(),
            'statistics' => [
                'total' => (clone $base)->count(),
                'new' => (clone $base)->whereIn('status', ['submitted', 'triage'])->count(),
                'investigation' => (clone $base)->where('status', 'investigation')->count(),
                'decision' => (clone $base)->whereIn('status', ['show_cause_pending', 'show_cause', 'decision', 'appeal'])->count(),
                'overdue' => (clone $base)->open()
                    ->whereNotNull('target_completion_date')
                    ->whereDate('target_completion_date', '<', $now->toDateString())
                    ->count(),
            ],
            'permissions' => [
                'manage' => $request->user()->hasPermission('discipline.manage'),
                'investigate' => $request->user()->hasPermission('discipline.investigate'),
                'approve' => $request->user()->hasPermission('discipline.approve'),
            ],
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

    public function readNotifications(Request $request): RedirectResponse
    {
        DisciplineNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    public function triage(Request $request, DisciplineCase $case): RedirectResponse
    {
        $this->authorizeManage($request);
        if (! in_array($case->status, ['submitted', 'triage'], true)) {
            throw ValidationException::withMessages(['status' => 'Kes ini telah melepasi peringkat triage.']);
        }
        $validated = $request->validate([
            'action' => ['required', Rule::in(['accept', 'dismiss'])],
            'severity' => ['required', Rule::in(ComplaintCategory::SEVERITIES)],
            'triage_notes' => ['required', 'string', 'min:5', 'max:5000'],
            'investigator_user_id' => [
                Rule::requiredIf($request->input('action') === 'accept'),
                'nullable', 'integer', 'exists:users,id',
            ],
            'allegation_summary' => [
                Rule::requiredIf($request->input('action') === 'accept'),
                'nullable', 'string', 'max:10000',
            ],
            'target_completion_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);
        $accepted = $validated['action'] === 'accept';
        $investigator = $accepted
            ? User::query()->with('roleAssignments')->findOrFail($validated['investigator_user_id'])
            : null;
        if ($investigator) {
            $this->assertEligibleOfficer($case, $investigator);
        }

        DB::transaction(function () use ($request, $case, $validated, $accepted, $investigator) {
            $oldStatus = $case->status;
            $case->update($accepted
                ? [
                    'status' => 'investigation',
                    'severity' => $validated['severity'],
                    'triage_notes' => $validated['triage_notes'],
                    'triaged_by' => $request->user()->getAuthIdentifier(),
                    'triaged_at' => now(),
                    'investigator_user_id' => $investigator?->getKey(),
                    'investigation_started_at' => now(),
                    'target_completion_date' => $validated['target_completion_date']
                        ?? today()->addDays($case->category?->sla_days ?? 30),
                    'allegation_summary' => $validated['allegation_summary'],
                    'updated_by' => $request->user()->getAuthIdentifier(),
                ]
                : [
                    'status' => 'dismissed',
                    'severity' => $validated['severity'],
                    'triage_notes' => $validated['triage_notes'],
                    'triaged_by' => $request->user()->getAuthIdentifier(),
                    'triaged_at' => now(),
                    'closed_by' => $request->user()->getAuthIdentifier(),
                    'closed_at' => now(),
                    'closure_reason' => $validated['triage_notes'],
                    'updated_by' => $request->user()->getAuthIdentifier(),
                ]);
            if ($accepted && $investigator) {
                DisciplineCaseMember::query()->updateOrCreate(
                    [
                        'discipline_case_id' => $case->getKey(),
                        'user_id' => $investigator->getKey(),
                    ],
                    [
                        'role' => 'investigator',
                        'conflict_declared' => false,
                        'has_conflict' => null,
                        'conflict_notes' => null,
                        'conflict_declared_at' => null,
                        'recused_at' => null,
                        'assigned_by' => $request->user()->getAuthIdentifier(),
                    ],
                );
                $this->notify(
                    $investigator->getKey(),
                    $case,
                    'investigation_assigned',
                    'Kes siasatan ditugaskan',
                    "Anda ditugaskan kepada {$case->case_number}. Lengkapkan deklarasi konflik sebelum memulakan siasatan.",
                );
            }
            $this->event(
                $case,
                $accepted ? 'triage_accepted' : 'triage_dismissed',
                $accepted ? 'Aduan diterima untuk siasatan' : 'Aduan ditutup selepas triage',
                $accepted
                    ? 'Aduan melepasi triage dan telah ditugaskan untuk siasatan.'
                    : 'HR menutup aduan selepas penilaian awal.',
                $request,
                true,
                false,
            );
            AuditLogger::record(
                $request,
                $accepted ? 'discipline.triage_accepted' : 'discipline.triage_dismissed',
                'discipline_cases',
                $case->getKey(),
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => $case->status,
                    'severity' => $case->severity,
                    'investigator_user_id' => $case->investigator_user_id,
                ],
            );
        });
        $this->notify(
            (int) $case->complainant_user_id,
            $case,
            $accepted ? 'triage_accepted' : 'triage_dismissed',
            $accepted ? 'Aduan diterima untuk siasatan' : 'Keputusan triage aduan',
            $accepted
                ? "Aduan {$case->case_number} telah diterima untuk siasatan sulit."
                : "Aduan {$case->case_number} ditutup selepas penilaian awal.",
        );

        return $this->success($accepted ? 'Kes telah dibuka untuk siasatan.' : 'Aduan telah ditutup selepas triage.');
    }

    public function addMember(Request $request, DisciplineCase $case): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->authorizeVisible($request, $case);
        if (! in_array($case->status, ['investigation', 'show_cause_pending'], true)) {
            throw ValidationException::withMessages(['status' => 'Pasukan hanya boleh diubah semasa siasatan.']);
        }
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', Rule::in(['investigator', 'panel', 'advisor'])],
        ]);
        $officer = User::query()->with('roleAssignments')->findOrFail($validated['user_id']);
        $this->assertEligibleOfficer($case, $officer);
        $member = DisciplineCaseMember::query()->updateOrCreate(
            [
                'discipline_case_id' => $case->getKey(),
                'user_id' => $officer->getKey(),
            ],
            [
                'role' => $validated['role'],
                'conflict_declared' => false,
                'has_conflict' => null,
                'conflict_notes' => null,
                'conflict_declared_at' => null,
                'recused_at' => null,
                'assigned_by' => $request->user()->getAuthIdentifier(),
            ],
        );
        $this->notify(
            $officer->getKey(),
            $case,
            'case_member_assigned',
            'Pelantikan pasukan siasatan',
            "Anda dilantik sebagai {$member->role} bagi {$case->case_number}. Deklarasi konflik diperlukan.",
        );
        AuditLogger::record(
            $request,
            'discipline.member_assigned',
            'discipline_case_members',
            $member->getKey(),
            newValues: $member->only(['discipline_case_id', 'user_id', 'role']),
        );

        return $this->success('Pegawai telah ditambah dan diminta membuat deklarasi konflik.');
    }

    public function declareConflict(
        Request $request,
        DisciplineCase $case,
        DisciplineCaseMember $member,
    ): RedirectResponse {
        abort_unless($member->discipline_case_id === $case->getKey(), 404);
        abort_unless((int) $member->user_id === (int) $request->user()->getAuthIdentifier(), 403);
        abort_if($member->recused_at, 403);
        $validated = $request->validate([
            'has_conflict' => ['required', 'boolean'],
            'conflict_notes' => [
                Rule::requiredIf($request->boolean('has_conflict')),
                'nullable', 'string', 'max:5000',
            ],
        ]);
        $hasConflict = (bool) $validated['has_conflict'];
        DB::transaction(function () use ($request, $case, $member, $validated, $hasConflict) {
            $member->update([
                'conflict_declared' => true,
                'has_conflict' => $hasConflict,
                'conflict_notes' => $validated['conflict_notes'] ?? null,
                'conflict_declared_at' => now(),
                'recused_at' => $hasConflict ? now() : null,
            ]);
            if ($hasConflict && (int) $case->investigator_user_id === (int) $member->user_id) {
                $case->update([
                    'investigator_user_id' => null,
                    'updated_by' => $request->user()->getAuthIdentifier(),
                ]);
            }
            AuditLogger::record(
                $request,
                'discipline.conflict_declared',
                'discipline_case_members',
                $member->getKey(),
                newValues: [
                    'has_conflict' => $hasConflict,
                    'recused' => $hasConflict,
                ],
            );
        });
        if ($hasConflict) {
            $this->notifyPermissionUsers(
                'discipline.manage',
                $case,
                'member_recused',
                'Konflik kepentingan diisytiharkan',
                "Seorang pegawai menarik diri daripada {$case->case_number}. Pengganti perlu ditetapkan.",
            );
        }

        return $this->success(
            $hasConflict
                ? 'Konflik direkodkan dan anda telah digugurkan daripada kes.'
                : 'Deklarasi tiada konflik telah direkodkan.',
        );
    }

    public function recuseMember(
        Request $request,
        DisciplineCase $case,
        DisciplineCaseMember $member,
    ): RedirectResponse {
        $this->authorizeManage($request);
        abort_unless($member->discipline_case_id === $case->getKey(), 404);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:3000'],
        ]);
        $member->update([
            'recused_at' => now(),
            'conflict_declared' => true,
            'has_conflict' => true,
            'conflict_notes' => $validated['reason'],
            'conflict_declared_at' => $member->conflict_declared_at ?? now(),
        ]);
        if ((int) $case->investigator_user_id === (int) $member->user_id) {
            $case->update(['investigator_user_id' => null, 'updated_by' => $request->user()->getAuthIdentifier()]);
        }
        AuditLogger::record(
            $request,
            'discipline.member_recused',
            'discipline_case_members',
            $member->getKey(),
            newValues: ['recused_at' => $member->recused_at, 'reason_recorded' => true],
        );

        return $this->success('Pegawai telah digugurkan daripada kes.');
    }

    public function addEvent(Request $request, DisciplineCase $case): RedirectResponse
    {
        $this->authorizeInvestigator($request, $case);
        if ($case->status !== 'investigation') {
            throw ValidationException::withMessages(['status' => 'Catatan siasatan hanya boleh ditambah semasa siasatan aktif.']);
        }
        $validated = $request->validate([
            'event_type' => ['required', Rule::in([
                'interview', 'evidence_review', 'meeting', 'site_visit',
                'correspondence', 'other',
            ])],
            'title' => ['required', 'string', 'max:220'],
            'details' => ['nullable', 'string', 'max:20000'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'visible_to_complainant' => ['required', 'boolean'],
            'visible_to_subject' => ['required', 'boolean'],
        ]);
        $event = DisciplineCaseEvent::query()->create([
            'discipline_case_id' => $case->getKey(),
            ...$validated,
            'created_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'discipline.investigation_event_added',
            'discipline_case_events',
            $event->getKey(),
            newValues: $event->only([
                'discipline_case_id', 'event_type', 'occurred_at',
                'visible_to_complainant', 'visible_to_subject',
            ]),
        );

        return $this->success('Catatan siasatan telah ditambah.');
    }

    public function uploadAttachment(Request $request, DisciplineCase $case): RedirectResponse
    {
        $this->authorizeInvestigator($request, $case);
        $validated = $request->validate([
            'attachment_context' => ['required', Rule::in([
                'investigation', 'statement', 'show_cause', 'appeal', 'decision',
            ])],
            'attachment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx', 'max:15360'],
            'visible_to_complainant' => ['required', 'boolean'],
            'visible_to_subject' => ['required', 'boolean'],
        ]);
        $file = $request->file('attachment');
        $path = $file->store("discipline/{$case->getKey()}", 'local');
        $attachment = DisciplineAttachment::query()->create([
            'discipline_case_id' => $case->getKey(),
            'uploaded_by' => $request->user()->getAuthIdentifier(),
            'attachment_context' => $validated['attachment_context'],
            'original_name' => $file->getClientOriginalName(),
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'visible_to_complainant' => $validated['visible_to_complainant'],
            'visible_to_subject' => $validated['visible_to_subject'],
        ]);
        AuditLogger::record(
            $request,
            'discipline.attachment_uploaded',
            'discipline_attachments',
            $attachment->getKey(),
            newValues: $attachment->only([
                'discipline_case_id', 'attachment_context', 'mime_type', 'size',
                'visible_to_complainant', 'visible_to_subject',
            ]),
        );

        return $this->success('Bukti siasatan telah disimpan dalam storan persendirian.');
    }

    public function deleteAttachment(
        Request $request,
        DisciplineCase $case,
        DisciplineAttachment $attachment,
    ): RedirectResponse {
        $this->authorizeInvestigator($request, $case);
        abort_unless($attachment->discipline_case_id === $case->getKey(), 404);
        if ($attachment->attachment_context === 'complaint') {
            throw ValidationException::withMessages(['attachment' => 'Bukti asal pengadu tidak boleh dipadam.']);
        }
        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachmentId = $attachment->getKey();
        $attachment->delete();
        AuditLogger::record(
            $request,
            'discipline.attachment_deleted',
            'discipline_attachments',
            $attachmentId,
            oldValues: ['case_id' => $case->getKey(), 'context' => $attachment->attachment_context],
        );

        return $this->success('Lampiran siasatan telah dipadam.');
    }

    public function downloadAttachment(
        Request $request,
        DisciplineCase $case,
        DisciplineAttachment $attachment,
    ): HttpResponse {
        if (
            $request->user()->hasPermission('discipline.manage')
            || $request->user()->hasPermission('discipline.approve')
        ) {
            $this->authorizeVisible($request, $case);
        } else {
            $this->authorizeInvestigator($request, $case);
        }
        abort_unless($attachment->discipline_case_id === $case->getKey(), 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
            ['Cache-Control' => 'private, no-store'],
        );
    }

    public function submitFinding(Request $request, DisciplineCase $case): RedirectResponse
    {
        $this->authorizeInvestigator($request, $case);
        if ($case->status !== 'investigation') {
            throw ValidationException::withMessages(['status' => 'Dapatan hanya boleh dihantar bagi siasatan aktif.']);
        }
        $validated = $request->validate([
            'finding_outcome' => ['required', Rule::in(DisciplineCase::FINDING_OUTCOMES)],
            'finding_summary' => ['required', 'string', 'min:20', 'max:20000'],
            'recommended_action' => ['nullable', 'string', 'max:10000'],
        ]);
        $needsShowCause = in_array(
            $validated['finding_outcome'],
            ['substantiated', 'partially_substantiated'],
            true,
        ) && $case->category?->requires_show_cause && $case->subject_user_id;
        $case->update([
            ...$validated,
            'status' => $needsShowCause ? 'show_cause_pending' : 'decision',
            'finding_submitted_by' => $request->user()->getAuthIdentifier(),
            'finding_submitted_at' => now(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        $this->event(
            $case,
            'finding_submitted',
            'Dapatan siasatan diserahkan',
            'Laporan siasatan diserahkan kepada HR dan pihak berkuasa membuat keputusan.',
            $request,
            false,
            false,
        );
        $this->notifyPermissionUsers(
            $needsShowCause ? 'discipline.manage' : 'discipline.approve',
            $case,
            'finding_submitted',
            'Dapatan siasatan diterima',
            $needsShowCause
                ? "Kes {$case->case_number} memerlukan surat tunjuk sebab."
                : "Kes {$case->case_number} sedia untuk keputusan.",
        );
        AuditLogger::record(
            $request,
            'discipline.finding_submitted',
            'discipline_cases',
            $case->getKey(),
            oldValues: ['status' => 'investigation'],
            newValues: [
                'status' => $case->status,
                'finding_outcome' => $case->finding_outcome,
            ],
        );

        return $this->success('Dapatan siasatan telah dihantar.');
    }

    public function issueShowCause(Request $request, DisciplineCase $case): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->authorizeVisible($request, $case);
        if ($case->status !== 'show_cause_pending' || ! $case->subject_user_id) {
            throw ValidationException::withMessages(['status' => 'Kes ini tidak boleh dikeluarkan arahan tunjuk sebab.']);
        }
        $validated = $request->validate([
            'due_at' => ['required', 'date', 'after:now'],
            'create_hr_document' => ['required', 'boolean'],
        ]);
        $document = DB::transaction(function () use ($request, $case, $validated) {
            $case->update([
                'status' => 'show_cause',
                'show_cause_due_at' => $validated['due_at'],
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
            $document = $validated['create_hr_document']
                ? $this->createDisciplineDocument($request, $case->fresh(), 'show_cause')
                : null;
            if ($document) {
                $case->update(['hr_document_id' => $document->getKey()]);
            }
            $this->event(
                $case,
                'show_cause_issued',
                'Arahan tunjuk sebab dikeluarkan',
                'Pekerja diminta mengemukakan representasi sebelum tarikh akhir.',
                $request,
                false,
                true,
            );

            return $document;
        });
        $this->notify(
            (int) $case->subject_user_id,
            $case,
            'show_cause_issued',
            'Tindakan diperlukan: jawapan tunjuk sebab',
            "Sila jawab kes {$case->case_number} sebelum ".Carbon::parse($validated['due_at'])->format('d/m/Y H:i').'.',
        );
        AuditLogger::record(
            $request,
            'discipline.show_cause_issued',
            'discipline_cases',
            $case->getKey(),
            oldValues: ['status' => 'show_cause_pending'],
            newValues: [
                'status' => 'show_cause',
                'show_cause_due_at' => $case->show_cause_due_at,
                'hr_document_id' => $document?->getKey(),
            ],
        );

        return $this->success(
            $document
                ? 'Arahan tunjuk sebab dikeluarkan dan draf Dokumen HR dijana.'
                : 'Arahan tunjuk sebab telah dikeluarkan.',
        );
    }

    public function decide(Request $request, DisciplineCase $case): RedirectResponse
    {
        $this->authorizeDecisionMaker($request, $case);
        if ($case->status !== 'decision' || ! $case->finding_submitted_at) {
            throw ValidationException::withMessages(['status' => 'Kes ini belum sedia untuk keputusan.']);
        }
        $validated = $request->validate([
            'decision_outcome' => ['required', Rule::in(DisciplineCase::DECISION_OUTCOMES)],
            'decision_notes' => ['required', 'string', 'min:10', 'max:20000'],
            'effective_date' => ['nullable', 'date', 'after_or_equal:today'],
            'create_hr_document' => ['required', 'boolean'],
        ]);
        $noAction = $validated['decision_outcome'] === 'no_action';
        $document = DB::transaction(function () use ($request, $case, $validated, $noAction) {
            $case->update([
                'status' => $noAction ? 'closed' : 'decision',
                'decision_outcome' => $validated['decision_outcome'],
                'decision_notes' => $validated['decision_notes'],
                'decided_by' => $request->user()->getAuthIdentifier(),
                'decided_at' => now(),
                'effective_date' => $validated['effective_date'] ?? today(),
                'appeal_deadline' => $noAction || ! $case->subject_user_id
                    ? null
                    : today()->addDays($case->category?->appeal_days ?? 14),
                'closed_by' => $noAction ? $request->user()->getAuthIdentifier() : null,
                'closed_at' => $noAction ? now() : null,
                'closure_reason' => $noAction ? 'Tiada tindakan tatatertib diputuskan.' : null,
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
            $document = $validated['create_hr_document'] && ! $noAction
                ? $this->createDisciplineDocument(
                    $request,
                    $case->fresh(),
                    $validated['decision_outcome'] === 'termination' ? 'termination' : 'warning',
                )
                : null;
            if ($document) {
                $case->update(['hr_document_id' => $document->getKey()]);
            }
            $this->event(
                $case,
                'decision_recorded',
                'Keputusan tatatertib direkodkan',
                $noAction
                    ? 'Kes ditutup tanpa tindakan tatatertib.'
                    : 'Keputusan dikeluarkan dan tempoh rayuan telah bermula.',
                $request,
                true,
                true,
            );

            return $document;
        });
        if ($case->subject_user_id) {
            $this->notify(
                (int) $case->subject_user_id,
                $case,
                'decision_recorded',
                'Keputusan kes tatatertib',
                $noAction
                    ? "Kes {$case->case_number} ditutup tanpa tindakan."
                    : "Keputusan {$case->case_number} telah direkodkan. Rayuan boleh dibuat sebelum {$case->appeal_deadline?->format('d/m/Y')}.",
            );
        }
        if ($case->complainant_user_id) {
            $this->notify(
                (int) $case->complainant_user_id,
                $case,
                'case_decided',
                'Kes aduan telah diputuskan',
                "Kes {$case->case_number} telah diputuskan mengikut proses dalaman.",
            );
        }
        AuditLogger::record(
            $request,
            'discipline.decision_recorded',
            'discipline_cases',
            $case->getKey(),
            oldValues: ['status' => 'decision', 'decision_outcome' => null],
            newValues: [
                'status' => $case->status,
                'decision_outcome' => $case->decision_outcome,
                'appeal_deadline' => $case->appeal_deadline,
                'hr_document_id' => $document?->getKey(),
            ],
        );

        return $this->success('Keputusan tatatertib telah direkodkan.');
    }

    public function proceedWithoutResponse(
        Request $request,
        DisciplineCase $case,
    ): RedirectResponse {
        $this->authorizeManage($request);
        $this->authorizeVisible($request, $case);
        if (
            $case->status !== 'show_cause'
            || ! $case->show_cause_due_at
            || ! $case->show_cause_due_at->isPast()
        ) {
            throw ValidationException::withMessages([
                'status' => 'Kes hanya boleh diteruskan selepas tempoh jawapan tamat.',
            ]);
        }
        if ($case->responses()->where('response_type', 'show_cause')->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Jawapan pekerja telah diterima dan perlu dipertimbangkan.',
            ]);
        }
        $case->update([
            'status' => 'decision',
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        $this->event(
            $case,
            'show_cause_expired',
            'Tempoh jawapan tamat',
            'Tiada jawapan diterima dalam tempoh yang ditetapkan. Kes diteruskan untuk keputusan berdasarkan rekod sedia ada.',
            $request,
            false,
            true,
        );
        $this->notifyPermissionUsers(
            'discipline.approve',
            $case,
            'decision_required',
            'Kes sedia untuk keputusan',
            "Tempoh jawapan {$case->case_number} telah tamat tanpa respons.",
        );
        AuditLogger::record(
            $request,
            'discipline.show_cause_expired',
            'discipline_cases',
            $case->getKey(),
            oldValues: ['status' => 'show_cause'],
            newValues: ['status' => 'decision', 'response_received' => false],
        );

        return $this->success('Kes diteruskan untuk keputusan tanpa jawapan pekerja.');
    }

    public function reviewAppeal(
        Request $request,
        DisciplineCase $case,
        DisciplineAppeal $appeal,
    ): RedirectResponse {
        $this->authorizeAppealReviewer($request, $case);
        abort_unless($appeal->discipline_case_id === $case->getKey(), 404);
        if ($case->status !== 'appeal' || $appeal->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Rayuan ini tidak lagi menunggu semakan.']);
        }
        $validated = $request->validate([
            'outcome' => ['required', Rule::in(['upheld', 'varied', 'overturned'])],
            'decision_notes' => ['required', 'string', 'min:10', 'max:20000'],
            'revised_outcome' => [
                Rule::requiredIf($request->input('outcome') === 'varied'),
                'nullable', Rule::in(DisciplineCase::DECISION_OUTCOMES),
            ],
        ]);
        $revised = match ($validated['outcome']) {
            'overturned' => 'no_action',
            'varied' => $validated['revised_outcome'],
            default => $case->decision_outcome,
        };
        DB::transaction(function () use ($request, $case, $appeal, $validated, $revised) {
            $appeal->update([
                'status' => $validated['outcome'],
                'reviewed_by' => $request->user()->getAuthIdentifier(),
                'reviewed_at' => now(),
                'decision_notes' => $validated['decision_notes'],
                'revised_outcome' => $revised,
            ]);
            $case->update([
                'status' => 'closed',
                'decision_outcome' => $revised,
                'closed_by' => $request->user()->getAuthIdentifier(),
                'closed_at' => now(),
                'closure_reason' => 'Rayuan selesai: '.$validated['outcome'],
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
            $this->event(
                $case,
                'appeal_decided',
                'Keputusan rayuan direkodkan',
                'Proses rayuan selesai dan kes ditutup.',
                $request,
                false,
                true,
            );
        });
        $this->notify(
            (int) $appeal->appellant_user_id,
            $case,
            'appeal_decided',
            'Keputusan rayuan',
            "Rayuan bagi {$case->case_number} telah diputuskan: {$validated['outcome']}.",
        );
        AuditLogger::record(
            $request,
            'discipline.appeal_decided',
            'discipline_appeals',
            $appeal->getKey(),
            oldValues: ['status' => 'pending'],
            newValues: ['status' => $appeal->status, 'revised_outcome' => $revised],
        );

        return $this->success('Rayuan telah diputuskan dan kes ditutup.');
    }

    public function close(Request $request, DisciplineCase $case): RedirectResponse
    {
        $this->authorizeManage($request);
        $this->authorizeVisible($request, $case);
        if ($case->status !== 'decision' || ! $case->decided_at) {
            throw ValidationException::withMessages(['status' => 'Hanya kes berkeputusan boleh ditutup.']);
        }
        if ($case->appeals()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['status' => 'Kes mempunyai rayuan yang belum diputuskan.']);
        }
        if ($case->appeal_deadline && ! $case->appeal_deadline->isBefore(today())) {
            throw ValidationException::withMessages(['status' => 'Tempoh rayuan masih aktif.']);
        }
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:5000'],
        ]);
        $case->update([
            'status' => 'closed',
            'closed_by' => $request->user()->getAuthIdentifier(),
            'closed_at' => now(),
            'closure_reason' => $validated['reason'],
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        $this->event(
            $case,
            'case_closed',
            'Kes ditutup',
            'Kes ditutup selepas tempoh rayuan berakhir.',
            $request,
            true,
            true,
        );
        AuditLogger::record(
            $request,
            'discipline.case_closed',
            'discipline_cases',
            $case->getKey(),
            oldValues: ['status' => 'decision'],
            newValues: ['status' => 'closed'],
        );

        return $this->success('Kes telah ditutup dan dikunci daripada tindakan lanjut.');
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->visibleQuery($request);
        if (
            ! $request->user()->hasPermission('discipline.manage')
            && ! $request->user()->hasPermission('discipline.approve')
        ) {
            $query->whereHas('members', fn (Builder $query) => $query
                ->where('user_id', $request->user()->getAuthIdentifier())
                ->where('conflict_declared', true)
                ->where('has_conflict', false)
                ->whereNull('recused_at'));
        }
        $cases = $query
            ->with('category:id,name')
            ->orderBy('created_at')
            ->get();
        AuditLogger::record(
            $request,
            'discipline.report_exported',
            'discipline_cases',
            'csv',
            newValues: ['records' => $cases->count()],
        );

        return response()->streamDownload(function () use ($cases) {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, [
                'No. Kes', 'Kategori', 'Tajuk', 'Subjek', 'Jabatan',
                'Tahap', 'Status', 'Tarikh Aduan', 'Sasaran Selesai',
                'Dapatan', 'Keputusan', 'Tarikh Tutup',
            ]);
            foreach ($cases as $case) {
                fputcsv($stream, [
                    $case->case_number,
                    $case->category?->name,
                    $case->title,
                    $case->subject_name,
                    $case->subject_department_name,
                    $case->severity,
                    $case->status,
                    $case->created_at?->format('Y-m-d'),
                    $case->target_completion_date?->format('Y-m-d'),
                    $case->finding_outcome,
                    $case->decision_outcome,
                    $case->closed_at?->format('Y-m-d'),
                ]);
            }
            fclose($stream);
        }, 'laporan-disiplin-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function visibleQuery(Request $request): Builder
    {
        $user = $request->user();
        $query = DisciplineCase::query();
        if ($user->hasPermission('discipline.manage') || $user->hasPermission('discipline.approve')) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query
            ->where('investigator_user_id', $user->getAuthIdentifier())
            ->orWhereHas('members', fn (Builder $query) => $query
                ->where('user_id', $user->getAuthIdentifier())
                ->whereNull('recused_at')));
    }

    private function authorizeVisible(Request $request, DisciplineCase $case): void
    {
        abort_unless($this->visibleQuery($request)->whereKey($case)->exists(), 403);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->hasPermission('discipline.manage'), 403);
    }

    private function authorizeInvestigator(Request $request, DisciplineCase $case): void
    {
        abort_unless($request->user()->hasPermission('discipline.investigate'), 403);
        $member = DisciplineCaseMember::query()
            ->where('discipline_case_id', $case->getKey())
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('conflict_declared', true)
            ->where('has_conflict', false)
            ->whereNull('recused_at')
            ->exists();
        abort_unless($member, 403);
    }

    private function authorizeDecisionMaker(Request $request, DisciplineCase $case): void
    {
        abort_unless($request->user()->hasPermission('discipline.approve'), 403);
        $userId = (int) $request->user()->getAuthIdentifier();
        abort_if($userId === (int) $case->complainant_user_id, 403);
        abort_if($userId === (int) $case->subject_user_id, 403);
        abort_if($case->members()->where('user_id', $userId)->whereNull('recused_at')->exists(), 403);
    }

    private function authorizeAppealReviewer(Request $request, DisciplineCase $case): void
    {
        $this->authorizeDecisionMaker($request, $case);
        abort_if(
            (int) $case->decided_by === (int) $request->user()->getAuthIdentifier(),
            403,
        );
    }

    private function assertEligibleOfficer(DisciplineCase $case, User $officer): void
    {
        if (! $officer->hasPermission('discipline.investigate')) {
            throw ValidationException::withMessages(['user_id' => 'Pegawai tidak mempunyai kebenaran siasatan.']);
        }
        if (in_array((int) $officer->getKey(), [
            (int) $case->complainant_user_id,
            (int) $case->subject_user_id,
        ], true)) {
            throw ValidationException::withMessages(['user_id' => 'Pengadu atau subjek kes tidak boleh dilantik sebagai pegawai.']);
        }
    }

    private function createDisciplineDocument(
        Request $request,
        DisciplineCase $case,
        string $category,
    ): ?HrDocument {
        $template = DocumentTemplate::query()
            ->where('category', $category)
            ->where('is_active', true)
            ->first();
        if (! $template || ! $case->subject_user_id || ! $case->subject_employee_id) {
            return null;
        }
        $approverId = $template->approver_user_id
            ?? User::query()
                ->with('roleAssignments')
                ->get()
                ->first(fn (User $user) => $user->hasPermission('documents.approve'))
                ?->getKey();

        return HrDocument::query()->create([
            'document_template_id' => $template->getKey(),
            'template_code' => $template->code,
            'template_name' => $template->name,
            'category' => $template->category,
            'employee_user_id' => $case->subject_user_id,
            'employee_id' => $case->subject_employee_id,
            'employee_number' => $case->subject_employee_number,
            'employee_name' => $case->subject_name,
            'employee_email' => $case->subject_email,
            'department_id' => $case->subject_department_id,
            'department_name' => $case->subject_department_name,
            'position_name' => $case->subject_position_name,
            'source_type' => 'discipline',
            'source_id' => $case->getKey(),
            'subject' => $template->subject_template,
            'body' => $template->body_template,
            'custom_variables' => [
                'case_number' => $case->case_number,
                'allegation_summary' => $case->allegation_summary ?? '',
                'response_due_date' => $case->show_cause_due_at?->format('d/m/Y') ?? '',
                'disciplinary_outcome' => $case->decision_outcome ?? '',
            ],
            'template_snapshot' => $template->only([
                'code', 'name', 'category', 'subject_template', 'body_template',
                'available_variables', 'sequence_key', 'requires_approval',
                'acknowledgement_required', 'default_validity_months',
                'confidentiality',
            ]),
            'status' => 'draft',
            'approval_required' => $template->requires_approval,
            'approver_user_id' => $approverId,
            'effective_date' => $case->effective_date,
            'acknowledgement_required' => $template->acknowledgement_required,
            'confidentiality' => 'restricted',
            'internal_notes' => "Dijana daripada kes {$case->case_number}.",
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
    }

    private function event(
        DisciplineCase $case,
        string $type,
        string $title,
        string $details,
        Request $request,
        bool $complainant,
        bool $subject,
    ): DisciplineCaseEvent {
        return DisciplineCaseEvent::query()->create([
            'discipline_case_id' => $case->getKey(),
            'event_type' => $type,
            'title' => $title,
            'details' => $details,
            'occurred_at' => now(),
            'visible_to_complainant' => $complainant,
            'visible_to_subject' => $subject,
            'created_by' => $request->user()->getAuthIdentifier(),
        ]);
    }

    private function notify(
        int $userId,
        DisciplineCase $case,
        string $type,
        string $title,
        string $message,
    ): void {
        if ($userId <= 0) {
            return;
        }
        DisciplineNotification::query()->create([
            'user_id' => $userId,
            'discipline_case_id' => $case->getKey(),
            'type' => $type,
            'title' => $title,
            'message' => $message,
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
            ->each(fn (User $user) => $this->notify(
                $user->getKey(),
                $case,
                $type,
                $title,
                $message,
            ));
    }

    private function listPayload(Request $request, DisciplineCase $case): array
    {
        $hasGovernanceAccess = $request->user()->hasPermission('discipline.manage')
            || $request->user()->hasPermission('discipline.approve');
        $currentMember = $case->members->first(fn (DisciplineCaseMember $member) =>
            (int) $member->user_id === (int) $request->user()->getAuthIdentifier()
            && $member->recused_at === null);
        $detailsLocked = ! $hasGovernanceAccess
            && (! $currentMember
                || ! $currentMember->conflict_declared
                || $currentMember->has_conflict !== false);
        $canSeeIdentity = $hasGovernanceAccess
            || ! $case->identity_protected;

        return [
            ...$case->only([
                'id', 'case_number', 'severity', 'status',
                'target_completion_date', 'created_at',
            ]),
            'title' => $detailsLocked ? 'Butiran dikunci sehingga deklarasi konflik' : $case->title,
            'subject_name' => $detailsLocked ? 'Butiran dikunci' : $case->subject_name,
            'category' => $case->category?->name,
            'complainant_name' => $detailsLocked
                ? 'Butiran dikunci'
                : ($canSeeIdentity ? $case->complainant_name : 'Identiti Dilindungi'),
            'investigator' => $case->investigator?->name,
        ];
    }

    private function detailPayload(Request $request, DisciplineCase $case): array
    {
        $hasGovernanceAccess = $request->user()->hasPermission('discipline.manage')
            || $request->user()->hasPermission('discipline.approve');
        $currentMember = $case->members->first(fn (DisciplineCaseMember $member) =>
            (int) $member->user_id === (int) $request->user()->getAuthIdentifier()
            && $member->recused_at === null);
        $detailsLocked = ! $hasGovernanceAccess
            && (! $currentMember
                || ! $currentMember->conflict_declared
                || $currentMember->has_conflict !== false);
        $canSeeIdentity = $hasGovernanceAccess
            || ! $case->identity_protected;

        return [
            ...$case->only([
                'id', 'case_number', 'complaint_category_id', 'identity_protected',
                'severity', 'confidentiality', 'status', 'target_completion_date',
                'created_at',
            ]),
            'title' => $detailsLocked
                ? 'Butiran dikunci sehingga deklarasi konflik'
                : $case->title,
            'incident_at' => $detailsLocked ? null : $case->incident_at,
            'incident_location' => $detailsLocked ? null : $case->incident_location,
            'subject_user_id' => $detailsLocked ? null : $case->subject_user_id,
            'subject_employee_number' => $detailsLocked ? null : $case->subject_employee_number,
            'subject_email' => $detailsLocked ? null : $case->subject_email,
            'subject_department_name' => $detailsLocked ? null : $case->subject_department_name,
            'subject_position_name' => $detailsLocked ? null : $case->subject_position_name,
            'description' => $detailsLocked ? null : $case->description,
            'requested_resolution' => $detailsLocked ? null : $case->requested_resolution,
            'triage_notes' => $detailsLocked ? null : $case->triage_notes,
            'allegation_summary' => $detailsLocked ? null : $case->allegation_summary,
            'finding_outcome' => $detailsLocked ? null : $case->finding_outcome,
            'finding_summary' => $detailsLocked ? null : $case->finding_summary,
            'recommended_action' => $detailsLocked ? null : $case->recommended_action,
            'finding_submitted_at' => $detailsLocked ? null : $case->finding_submitted_at,
            'show_cause_due_at' => $hasGovernanceAccess ? $case->show_cause_due_at : null,
            'show_cause_expired' => $hasGovernanceAccess
                && $case->show_cause_due_at?->isPast(),
            'decision_outcome' => $hasGovernanceAccess ? $case->decision_outcome : null,
            'decision_notes' => $hasGovernanceAccess ? $case->decision_notes : null,
            'decided_by' => $hasGovernanceAccess ? $case->decided_by : null,
            'decided_at' => $hasGovernanceAccess ? $case->decided_at : null,
            'effective_date' => $hasGovernanceAccess ? $case->effective_date : null,
            'appeal_deadline' => $hasGovernanceAccess ? $case->appeal_deadline : null,
            'appeal_expired' => $hasGovernanceAccess
                && (! $case->appeal_deadline || $case->appeal_deadline->isBefore(today())),
            'closed_at' => $case->closed_at,
            'closure_reason' => $hasGovernanceAccess ? $case->closure_reason : null,
            'details_locked' => $detailsLocked,
            'complainant_user_id' => $canSeeIdentity ? $case->complainant_user_id : null,
            'complainant_name' => $detailsLocked
                ? 'Dikunci sehingga deklarasi konflik'
                : ($canSeeIdentity ? $case->complainant_name : 'Identiti Dilindungi'),
            'complainant_email' => ! $detailsLocked && $canSeeIdentity ? $case->complainant_email : null,
            'complainant_employee_number' => ! $detailsLocked && $canSeeIdentity ? $case->complainant_employee_number : null,
            'complainant_department_name' => ! $detailsLocked && $canSeeIdentity ? $case->complainant_department_name : null,
            'subject_name' => $detailsLocked ? 'Butiran dikunci' : $case->subject_name,
            'category' => $case->category?->only([
                'id', 'code', 'name', 'sla_days', 'appeal_days',
                'requires_show_cause',
            ]),
            'members' => $case->members->map(fn (DisciplineCaseMember $member) => [
                ...$member->only([
                    'id', 'user_id', 'role', 'conflict_declared', 'has_conflict',
                    'conflict_declared_at', 'recused_at',
                ]),
                'conflict_notes' => $hasGovernanceAccess
                    || (int) $member->user_id === (int) $request->user()->getAuthIdentifier()
                    ? $member->conflict_notes
                    : null,
                'user' => $member->user?->only(['id', 'name', 'email']),
                'is_current_user' => (int) $member->user_id === (int) $request->user()->getAuthIdentifier(),
            ]),
            'events' => $detailsLocked ? [] : $case->events->sortByDesc('occurred_at')->values()->map(fn (DisciplineCaseEvent $event) => [
                ...$event->only([
                    'id', 'event_type', 'title', 'details', 'occurred_at',
                    'visible_to_complainant', 'visible_to_subject',
                ]),
                'creator' => $event->creator?->name,
            ]),
            'attachments' => $detailsLocked ? [] : $case->attachments->sortByDesc('created_at')->values()->map(fn (DisciplineAttachment $attachment) => $attachment->only([
                'id', 'attachment_context', 'original_name', 'mime_type', 'size',
                'visible_to_complainant', 'visible_to_subject', 'created_at',
            ])),
            'responses' => $detailsLocked || ! $hasGovernanceAccess ? [] : $case->responses->map(fn ($response) => [
                ...$response->only(['id', 'response_type', 'statement', 'submitted_at']),
                'user' => $response->user?->only(['id', 'name']),
            ]),
            'appeals' => $detailsLocked || ! $hasGovernanceAccess ? [] : $case->appeals->map(fn (DisciplineAppeal $appeal) => [
                ...$appeal->only([
                    'id', 'grounds', 'desired_outcome', 'status', 'reviewed_at',
                    'decision_notes', 'revised_outcome',
                ]),
                'appellant' => $appeal->appellant?->only(['id', 'name']),
            ]),
            'hr_document' => $hasGovernanceAccess ? $case->hrDocument?->only([
                'id', 'reference_number', 'status', 'category',
            ]) : null,
        ];
    }

    private function success(string $message): RedirectResponse
    {
        return back()->with('toast', ['type' => 'success', 'message' => $message]);
    }
}
