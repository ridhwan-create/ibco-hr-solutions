<?php

namespace App\Http\Controllers;

use App\Models\ClearanceTask;
use App\Models\ExitInterview;
use App\Models\FinalSettlement;
use App\Models\HandoverItem;
use App\Models\SeparationAsset;
use App\Models\SeparationAttachment;
use App\Models\SeparationCase;
use App\Models\SeparationNotification;
use App\Models\SeparationTemplate;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\SeparationWorkflow;
use App\Support\TrainingEmployeeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SeparationController extends Controller
{
    public function __construct(
        private readonly SeparationWorkflow $workflow,
        private readonly TrainingEmployeeResolver $employees,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(SeparationCase::STATUSES)],
            'type' => ['nullable', Rule::in(SeparationTemplate::TYPES)],
            'case_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $search = trim($validated['search'] ?? '');
        $status = $validated['status'] ?? '';
        $type = $validated['type'] ?? '';
        $base = $this->visibleQuery($request->user());
        $query = (clone $base)
            ->with(['template:id,name', 'supervisor:id,name', 'hrApprover:id,name'])
            ->when($search !== '', fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query
                    ->where('case_number', 'like', "%{$search}%")
                    ->orWhere('employee_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhere('department_name', 'like', "%{$search}%")))
            ->when($status !== '', fn (Builder $query) => $query->where('status', $status))
            ->when($type !== '', fn (Builder $query) => $query->where('separation_type', $type));
        $cases = $query
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SeparationCase $case) => $this->listPayload($case));
        $selectedId = (int) ($validated['case_id'] ?? ($cases->items()[0]['id'] ?? 0));
        $selected = $selectedId
            ? $this->visibleQuery($request->user())
                ->whereKey($selectedId)
                ->with($this->caseRelations())
                ->first()
            : null;
        $notifications = SeparationNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('Separations/Index', [
            'cases' => $cases,
            'selectedCase' => $selected ? $this->casePayload($selected, $request->user()) : null,
            'templates' => SeparationTemplate::query()
                ->where('is_active', true)
                ->withCount('items')
                ->orderBy('name')
                ->get([
                    'id', 'name', 'description', 'separation_type',
                    'minimum_notice_days', 'exit_interview_required',
                    'final_settlement_required', 'approver_user_id',
                ]),
            'employees' => $request->user()->hasPermission('separation.manage')
                ? $this->employees->linkedOptions()
                : [],
            'supervisors' => $this->usersWithPermission('separation.supervise'),
            'approvers' => $this->usersWithPermission('separation.approve'),
            'clearanceUsers' => $this->usersWithPermission('separation.clearance'),
            'types' => SeparationTemplate::TYPES,
            'filters' => ['search' => $search, 'status' => $status, 'type' => $type],
            'statistics' => [
                'total' => (clone $base)->count(),
                'pending' => (clone $base)->where('status', 'pending_approval')->count(),
                'clearance' => (clone $base)->whereIn('status', ['approved', 'clearance', 'final_review'])->count(),
                'overdue' => (clone $base)
                    ->whereIn('status', ['clearance', 'final_review'])
                    ->whereDate('clearance_due_date', '<', today())
                    ->count(),
                'completed' => (clone $base)->where('status', 'completed')->count(),
            ],
            'permissions' => [
                'manage' => $request->user()->hasPermission('separation.manage'),
                'supervise' => $request->user()->hasPermission('separation.supervise'),
                'approve' => $request->user()->hasPermission('separation.approve'),
                'clearance' => $request->user()->hasPermission('separation.clearance'),
            ],
            'notifications' => $notifications->map(fn ($notification) => [
                'id' => $notification->getKey(),
                'case_id' => $notification->separation_case_id,
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
        abort_unless($request->user()->hasPermission('separation.manage'), 403);
        $validated = $request->validate([
            'separation_template_id' => ['required', 'integer', 'exists:separation_templates,id'],
            'employee_user_id' => ['required', 'integer', 'exists:users,id'],
            'separation_type' => ['required', Rule::in(SeparationTemplate::TYPES)],
            'reason_category' => ['nullable', 'string', 'max:80'],
            'reason_details' => ['required', 'string', 'min:5', 'max:10000'],
            'notice_submitted_date' => ['required', 'date'],
            'proposed_last_day' => ['required', 'date', 'after_or_equal:notice_submitted_date'],
            'supervisor_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'hr_approver_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);
        $template = SeparationTemplate::query()
            ->where('is_active', true)
            ->findOrFail($validated['separation_template_id']);
        $approver = User::query()->findOrFail($validated['hr_approver_user_id']);
        abort_unless($approver->hasPermission('separation.approve'), 422);
        if ((int) $validated['hr_approver_user_id'] === $request->user()->getAuthIdentifier()) {
            throw ValidationException::withMessages([
                'hr_approver_user_id' => 'Pencipta kes tidak boleh menjadi pelulus HR bagi kes yang sama.',
            ]);
        }
        if ((int) $validated['hr_approver_user_id'] === (int) $validated['employee_user_id']) {
            throw ValidationException::withMessages([
                'hr_approver_user_id' => 'Pekerja yang terlibat tidak boleh meluluskan kesnya sendiri.',
            ]);
        }
        if ($validated['supervisor_user_id']) {
            $supervisor = User::query()->findOrFail($validated['supervisor_user_id']);
            abort_unless($supervisor->hasPermission('separation.supervise'), 422);
        }
        $snapshot = $this->workflow->employeeSnapshot((int) $validated['employee_user_id']);
        $hasOpenCase = SeparationCase::query()
            ->forEmployee((int) $validated['employee_user_id'])
            ->whereNotIn('status', ['completed', 'rejected', 'cancelled'])
            ->exists();
        if ($hasOpenCase) {
            throw ValidationException::withMessages([
                'employee_user_id' => 'Pekerja ini sudah mempunyai satu kes pengakhiran yang masih aktif.',
            ]);
        }
        $notice = Carbon::parse($validated['notice_submitted_date']);
        $lastDay = Carbon::parse($validated['proposed_last_day']);
        $served = max(0, $notice->diffInDays($lastDay, false));
        $case = SeparationCase::query()->create([
            ...$snapshot,
            'case_number' => $this->workflow->nextCaseNumber(),
            'separation_template_id' => $template->getKey(),
            'separation_type' => $validated['separation_type'],
            'initiated_by_employee' => false,
            'reason_category' => $validated['reason_category'] ?? null,
            'reason_details' => $validated['reason_details'],
            'notice_submitted_date' => $notice,
            'proposed_last_day' => $lastDay,
            'notice_days_required' => $template->minimum_notice_days,
            'notice_days_served' => $served,
            'notice_shortfall_days' => max(0, $template->minimum_notice_days - $served),
            'status' => 'draft',
            'supervisor_user_id' => $validated['supervisor_user_id'] ?? null,
            'hr_approver_user_id' => $validated['hr_approver_user_id'],
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'separation.case_created',
            'separation_cases',
            $case->getKey(),
            newValues: $case->only([
                'case_number', 'employee_id', 'separation_type',
                'proposed_last_day', 'status', 'supervisor_user_id',
                'hr_approver_user_id',
            ]),
        );

        return $this->success('Draf kes pengakhiran telah dicipta.');
    }

    public function submit(Request $request, SeparationCase $case): RedirectResponse
    {
        $this->authorizeManage($request, $case);
        if ($case->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya kes draf boleh dihantar untuk kelulusan.',
            ]);
        }
        if (! $case->hr_approver_user_id) {
            throw ValidationException::withMessages([
                'hr_approver_user_id' => 'Tetapkan pelulus HR sebelum menghantar kes.',
            ]);
        }
        if ($case->hr_approver_user_id === $case->created_by) {
            throw ValidationException::withMessages([
                'hr_approver_user_id' => 'Pencipta kes tidak boleh meluluskan kesnya sendiri.',
            ]);
        }
        $stage = $case->supervisor_user_id ? 'supervisor' : 'hr';
        $case->update([
            'status' => 'pending_approval',
            'approval_stage' => $stage,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        $recipient = $stage === 'supervisor'
            ? $case->supervisor_user_id
            : $case->hr_approver_user_id;
        $this->workflow->notify(
            (int) $recipient,
            $case,
            "{$stage}_approval_required",
            'Kes pengakhiran menunggu kelulusan',
            "Kes {$case->case_number} memerlukan tindakan anda.",
        );
        AuditLogger::record(
            $request,
            'separation.case_submitted',
            'separation_cases',
            $case->getKey(),
            oldValues: ['status' => 'draft'],
            newValues: ['status' => 'pending_approval', 'approval_stage' => $stage],
        );

        return $this->success('Kes telah dihantar untuk kelulusan.');
    }

    public function supervisorReview(
        Request $request,
        SeparationCase $case,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user->hasPermission('separation.supervise'), 403);
        abort_unless(
            $case->supervisor_user_id === $user->getAuthIdentifier()
                || $user->hasPermission('separation.manage'),
            403,
        );
        if ($case->status !== 'pending_approval' || $case->approval_stage !== 'supervisor') {
            throw ValidationException::withMessages([
                'status' => 'Kes ini tidak lagi menunggu semakan penyelia.',
            ]);
        }
        $validated = $request->validate([
            'action' => ['required', Rule::in(['support', 'reject'])],
            'notes' => [Rule::requiredIf($request->input('action') === 'reject'), 'nullable', 'string', 'max:5000'],
        ]);
        $supported = $validated['action'] === 'support';
        $case->update([
            'supervisor_decision' => $supported ? 'supported' : 'rejected',
            'supervisor_notes' => $validated['notes'] ?? null,
            'supervisor_decided_by' => $user->getAuthIdentifier(),
            'supervisor_decided_at' => now(),
            'status' => $supported ? 'pending_approval' : 'rejected',
            'approval_stage' => $supported ? 'hr' : null,
            'updated_by' => $user->getAuthIdentifier(),
        ]);
        $recipient = $supported ? $case->hr_approver_user_id : $case->employee_user_id;
        if ($recipient) {
            $this->workflow->notify(
                (int) $recipient,
                $case,
                $supported ? 'hr_approval_required' : 'separation_rejected',
                $supported ? 'Kes menunggu kelulusan Pengurus HR' : 'Notis pengakhiran ditolak',
                $supported
                    ? "Kes {$case->case_number} telah disokong penyelia."
                    : "Kes {$case->case_number} telah ditolak oleh penyelia.",
            );
        }
        AuditLogger::record(
            $request,
            $supported ? 'separation.supervisor_supported' : 'separation.supervisor_rejected',
            'separation_cases',
            $case->getKey(),
            newValues: [
                'supervisor_decision' => $case->supervisor_decision,
                'status' => $case->status,
                'approval_stage' => $case->approval_stage,
            ],
        );

        return $this->success($supported ? 'Kes telah disokong dan dihantar kepada Pengurus HR.' : 'Kes telah ditolak.');
    }

    public function hrReview(Request $request, SeparationCase $case): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission('separation.approve'), 403);
        if ($case->hr_approver_user_id) {
            abort_unless(
                $case->hr_approver_user_id === $user->getAuthIdentifier(),
                403,
            );
        }
        if (! $case->initiated_by_employee && $case->created_by === $user->getAuthIdentifier()) {
            abort(403, 'Pencipta kes tidak boleh meluluskan kesnya sendiri.');
        }
        if ($case->status !== 'pending_approval' || $case->approval_stage !== 'hr') {
            throw ValidationException::withMessages([
                'status' => 'Kes ini tidak lagi menunggu kelulusan Pengurus HR.',
            ]);
        }
        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'approved_last_day' => [Rule::requiredIf($request->input('action') === 'approve'), 'nullable', 'date'],
            'notice_waived' => ['required', 'boolean'],
            'waiver_notes' => [Rule::requiredIf((bool) $request->boolean('notice_waived')), 'nullable', 'string', 'max:5000'],
            'notes' => [Rule::requiredIf($request->input('action') === 'reject'), 'nullable', 'string', 'max:5000'],
        ]);
        $approved = $validated['action'] === 'approve';
        if (! $approved) {
            $case->update([
                'hr_decision' => 'rejected',
                'hr_notes' => $validated['notes'],
                'hr_decided_by' => $user->getAuthIdentifier(),
                'hr_decided_at' => now(),
                'status' => 'rejected',
                'approval_stage' => null,
                'updated_by' => $user->getAuthIdentifier(),
            ]);
            if ($case->employee_user_id) {
                $this->workflow->notify(
                    (int) $case->employee_user_id,
                    $case,
                    'separation_rejected',
                    'Notis pengakhiran ditolak',
                    "Kes {$case->case_number} tidak diluluskan oleh HR.",
                );
            }
            AuditLogger::record(
                $request,
                'separation.hr_rejected',
                'separation_cases',
                $case->getKey(),
                newValues: ['status' => 'rejected', 'hr_notes' => $validated['notes']],
            );

            return $this->success('Kes telah ditolak.');
        }
        $lastDay = Carbon::parse($validated['approved_last_day']);
        $notice = $case->notice_submitted_date;
        $served = max(0, $notice->diffInDays($lastDay, false));
        DB::transaction(function () use ($request, $case, $validated, $lastDay, $served) {
            $case->update([
                'approved_last_day' => $lastDay,
                'notice_days_served' => $served,
                'notice_shortfall_days' => max(0, $case->notice_days_required - $served),
                'notice_waived' => (bool) $validated['notice_waived'],
                'waiver_notes' => $validated['waiver_notes'] ?? null,
                'hr_decision' => 'approved',
                'hr_notes' => $validated['notes'] ?? null,
                'hr_decided_by' => $request->user()->getAuthIdentifier(),
                'hr_decided_at' => now(),
                'status' => 'approved',
                'approval_stage' => null,
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
            $this->workflow->initializeClearance(
                $case,
                (int) $request->user()->getAuthIdentifier(),
            );
        });
        AuditLogger::record(
            $request,
            'separation.hr_approved',
            'separation_cases',
            $case->getKey(),
            newValues: $case->fresh()->only([
                'approved_last_day', 'notice_days_served',
                'notice_shortfall_days', 'notice_waived', 'status',
                'clearance_started_at', 'clearance_due_date',
            ]),
        );

        return $this->success('Kes diluluskan dan checklist clearance telah dijana.');
    }

    public function cancel(Request $request, SeparationCase $case): RedirectResponse
    {
        $this->authorizeManage($request, $case);
        abort_if(in_array($case->status, ['completed', 'cancelled'], true), 422);
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:5000'],
        ]);
        $oldStatus = $case->status;
        $case->update([
            'status' => 'cancelled',
            'approval_stage' => null,
            'cancelled_by' => $request->user()->getAuthIdentifier(),
            'cancelled_at' => now(),
            'cancellation_reason' => $validated['reason'],
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'separation.case_cancelled',
            'separation_cases',
            $case->getKey(),
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'cancelled', 'reason' => $validated['reason']],
        );

        return $this->success('Kes telah dibatalkan dan dikekalkan dalam Audit Trail.');
    }

    public function taskAction(
        Request $request,
        SeparationCase $case,
        ClearanceTask $task,
    ): RedirectResponse {
        $this->authorizeCaseView($request, $case);
        abort_unless($task->separation_case_id === $case->getKey(), 404);
        $user = $request->user();
        $canManage = $user->hasPermission('separation.manage');
        $isAssignee = $task->assigned_user_id === $user->getAuthIdentifier();
        abort_unless($canManage || ($isAssignee && $user->hasPermission('separation.clearance')), 403);
        $validated = $request->validate([
            'action' => ['required', Rule::in(['assign', 'start', 'complete', 'reject', 'waive', 'reopen'])],
            'assigned_user_id' => [Rule::requiredIf($request->input('action') === 'assign'), 'nullable', 'integer', 'exists:users,id'],
            'notes' => [Rule::requiredIf(in_array($request->input('action'), ['reject', 'waive'], true)), 'nullable', 'string', 'max:5000'],
        ]);
        $action = $validated['action'];
        if (in_array($action, ['assign', 'waive', 'reopen'], true) && ! $canManage) {
            abort(403);
        }
        if ($action === 'assign') {
            $assignee = User::query()->findOrFail($validated['assigned_user_id']);
            abort_unless($assignee->hasPermission('separation.clearance'), 422);
            $task->update([
                'assigned_user_id' => $assignee->getKey(),
                'updated_by' => $user->getAuthIdentifier(),
            ]);
            $this->workflow->notify(
                (int) $assignee->getKey(),
                $case,
                'clearance_task_assigned',
                'Tugasan clearance ditugaskan',
                "{$task->title} bagi {$case->case_number} telah ditugaskan kepada anda.",
            );
        } elseif ($action === 'start') {
            abort_unless(in_array($task->status, ['pending', 'rejected'], true), 422);
            $task->update(['status' => 'in_progress', 'updated_by' => $user->getAuthIdentifier()]);
        } elseif ($action === 'complete') {
            abort_unless(in_array($task->status, ['pending', 'in_progress', 'submitted', 'rejected'], true), 422);
            if ($task->evidence_required && ! $task->attachments()->exists()) {
                throw ValidationException::withMessages([
                    'attachment' => 'Bukti wajib dimuat naik sebelum tugasan diselesaikan.',
                ]);
            }
            $task->update([
                'status' => 'completed',
                'review_notes' => $validated['notes'] ?? null,
                'completed_by' => $user->getAuthIdentifier(),
                'completed_at' => now(),
                'waived_by' => null,
                'waived_at' => null,
                'waiver_reason' => null,
                'updated_by' => $user->getAuthIdentifier(),
            ]);
        } elseif ($action === 'reject') {
            abort_unless($task->status === 'submitted', 422);
            $task->update([
                'status' => 'rejected',
                'review_notes' => $validated['notes'],
                'updated_by' => $user->getAuthIdentifier(),
            ]);
            if ($case->employee_user_id) {
                $this->workflow->notify(
                    (int) $case->employee_user_id,
                    $case,
                    'clearance_task_rejected',
                    'Tugasan clearance perlu diperbetulkan',
                    "{$task->title} telah dikembalikan untuk pembetulan.",
                );
            }
        } elseif ($action === 'waive') {
            $task->update([
                'status' => 'waived',
                'waived_by' => $user->getAuthIdentifier(),
                'waived_at' => now(),
                'waiver_reason' => $validated['notes'],
                'updated_by' => $user->getAuthIdentifier(),
            ]);
        } else {
            $task->update([
                'status' => 'pending',
                'completed_by' => null,
                'completed_at' => null,
                'waived_by' => null,
                'waived_at' => null,
                'waiver_reason' => null,
                'review_notes' => null,
                'updated_by' => $user->getAuthIdentifier(),
            ]);
        }
        $this->moveToFinalReviewWhenReady($case, (int) $user->getAuthIdentifier());
        AuditLogger::record(
            $request,
            "separation.clearance_task_{$action}",
            'clearance_tasks',
            $task->getKey(),
            newValues: $task->fresh()->only([
                'separation_case_id', 'assigned_user_id', 'status',
                'completed_by', 'completed_at', 'waived_by', 'waived_at',
            ]),
        );

        return $this->success('Tugasan clearance telah dikemas kini.');
    }

    public function uploadAttachment(
        Request $request,
        SeparationCase $case,
        ?ClearanceTask $task = null,
    ): RedirectResponse {
        $this->authorizeCaseView($request, $case);
        if ($task) {
            abort_unless($task->separation_case_id === $case->getKey(), 404);
        }
        $user = $request->user();
        abort_unless(
            $user->hasPermission('separation.manage')
                || ($task && $task->assigned_user_id === $user->getAuthIdentifier()),
            403,
        );
        $validated = $request->validate([
            'context' => ['required', Rule::in(['supporting', 'task_evidence', 'asset', 'handover', 'final_settlement'])],
            'attachment' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,xlsx', 'max:10240'],
            'visible_to_employee' => ['required', 'boolean'],
        ]);
        $file = $request->file('attachment');
        $path = $file->store(
            "separations/{$case->getKey()}/".($task ? "tasks/{$task->getKey()}" : 'case'),
            'local',
        );
        $attachment = SeparationAttachment::query()->create([
            'separation_case_id' => $case->getKey(),
            'clearance_task_id' => $task?->getKey(),
            'context' => $validated['context'],
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'visible_to_employee' => (bool) $validated['visible_to_employee'],
            'uploaded_by' => $user->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'separation.attachment_uploaded',
            'separation_attachments',
            $attachment->getKey(),
            newValues: $attachment->only([
                'separation_case_id', 'clearance_task_id', 'context',
                'original_name', 'size', 'visible_to_employee',
            ]),
        );

        return $this->success('Lampiran telah dimuat naik.');
    }

    public function downloadAttachment(
        Request $request,
        SeparationCase $case,
        SeparationAttachment $attachment,
    ): HttpResponse {
        $this->authorizeCaseView($request, $case);
        abort_unless($attachment->separation_case_id === $case->getKey(), 404);
        abort_unless(
            $this->canAccessAttachment($request->user(), $case, $attachment),
            403,
        );

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
            ['Cache-Control' => 'private, no-store'],
        );
    }

    public function storeAsset(Request $request, SeparationCase $case): RedirectResponse
    {
        $this->authorizeManage($request, $case);
        abort_unless(in_array($case->status, ['clearance', 'final_review'], true), 422);
        if ($case->settlement()->where('status', 'verified')->exists()) {
            throw ValidationException::withMessages([
                'asset_name' => 'Aset tidak boleh ditambah selepas final settlement disahkan.',
            ]);
        }
        $validated = $request->validate([
            'asset_type' => ['required', 'string', 'max:60'],
            'asset_name' => ['required', 'string', 'max:180'],
            'asset_tag' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:120'],
            'issued_date' => ['nullable', 'date'],
            'expected_return_date' => ['nullable', 'date'],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $asset = $case->assets()->create([
            ...$validated,
            'estimated_value' => $validated['estimated_value'] ?? 0,
            'status' => 'pending',
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'separation.asset_added',
            'separation_assets',
            $asset->getKey(),
            newValues: $asset->only([
                'separation_case_id', 'asset_type', 'asset_name',
                'asset_tag', 'serial_number', 'status', 'estimated_value',
            ]),
        );

        return $this->success('Aset telah ditambah pada rekod clearance.');
    }

    public function updateAsset(
        Request $request,
        SeparationCase $case,
        SeparationAsset $asset,
    ): RedirectResponse {
        $this->authorizeManage($request, $case);
        abort_unless($asset->separation_case_id === $case->getKey(), 404);
        if ($case->settlement()->where('status', 'verified')->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Aset tidak boleh diubah selepas final settlement disahkan.',
            ]);
        }
        $validated = $request->validate([
            'status' => ['required', Rule::in(SeparationAsset::STATUSES)],
            'return_condition' => ['nullable', Rule::in(['good', 'fair', 'damaged', 'not_returned'])],
            'charge_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $resolved = in_array($validated['status'], ['returned', 'damaged', 'lost', 'waived'], true);
        $asset->update([
            ...$validated,
            'charge_amount' => $validated['charge_amount'] ?? 0,
            'returned_at' => in_array($validated['status'], ['returned', 'damaged'], true) ? now() : null,
            'verified_by' => $resolved ? $request->user()->getAuthIdentifier() : null,
            'verified_at' => $resolved ? now() : null,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        $this->syncAssetDeduction($case);
        $this->moveToFinalReviewWhenReady($case, (int) $request->user()->getAuthIdentifier());
        AuditLogger::record(
            $request,
            'separation.asset_updated',
            'separation_assets',
            $asset->getKey(),
            newValues: $asset->only([
                'status', 'return_condition', 'charge_amount', 'verified_by', 'verified_at',
            ]),
        );

        return $this->success('Status aset telah dikemas kini.');
    }

    public function reviewHandover(
        Request $request,
        SeparationCase $case,
        HandoverItem $handover,
    ): RedirectResponse {
        $this->authorizeCaseView($request, $case);
        abort_unless($handover->separation_case_id === $case->getKey(), 404);
        $user = $request->user();
        abort_unless(
            $user->hasPermission('separation.manage')
                || $handover->recipient_user_id === $user->getAuthIdentifier(),
            403,
        );
        $validated = $request->validate([
            'action' => ['required', Rule::in(['accept', 'reject', 'waive'])],
            'notes' => [Rule::requiredIf($request->input('action') === 'reject'), 'nullable', 'string', 'max:5000'],
        ]);
        if ($validated['action'] === 'waive' && ! $user->hasPermission('separation.manage')) {
            abort(403);
        }
        if ($validated['action'] !== 'waive') {
            abort_unless($handover->status === 'submitted', 422);
        }
        $status = match ($validated['action']) {
            'accept' => 'accepted',
            'reject' => 'rejected',
            default => 'waived',
        };
        $handover->update([
            'status' => $status,
            'review_notes' => $validated['notes'] ?? null,
            'reviewed_by' => $user->getAuthIdentifier(),
            'reviewed_at' => now(),
            'updated_by' => $user->getAuthIdentifier(),
        ]);
        if ($status === 'rejected' && $case->employee_user_id) {
            $this->workflow->notify(
                (int) $case->employee_user_id,
                $case,
                'handover_rejected',
                'Serahan tugas perlu diperbetulkan',
                "{$handover->title} telah dikembalikan untuk pembetulan.",
            );
        }
        $this->moveToFinalReviewWhenReady($case, (int) $user->getAuthIdentifier());
        AuditLogger::record(
            $request,
            "separation.handover_{$status}",
            'handover_items',
            $handover->getKey(),
            newValues: ['status' => $status, 'reviewed_by' => $handover->reviewed_by],
        );

        return $this->success('Serahan tugas telah disemak.');
    }

    public function updateInterview(
        Request $request,
        SeparationCase $case,
    ): RedirectResponse {
        $this->authorizeManage($request, $case);
        $interview = ExitInterview::query()
            ->where('separation_case_id', $case->getKey())
            ->firstOrFail();
        $validated = $request->validate([
            'interviewer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'scheduled_at' => ['nullable', 'date'],
            'hr_private_notes' => ['nullable', 'string', 'max:10000'],
            'completed' => ['required', 'boolean'],
        ]);
        if ($validated['completed'] && ! $interview->employee_submitted_at) {
            throw ValidationException::withMessages([
                'completed' => 'Pekerja belum menghantar maklum balas exit interview.',
            ]);
        }
        $interview->update([
            'interviewer_user_id' => $validated['interviewer_user_id'] ?? $request->user()->getAuthIdentifier(),
            'scheduled_at' => $validated['scheduled_at'] ?? $interview->scheduled_at,
            'hr_private_notes' => $validated['hr_private_notes'] ?? null,
            'completed_at' => $validated['completed'] ? now() : null,
            'completed_by' => $validated['completed'] ? $request->user()->getAuthIdentifier() : null,
        ]);
        $this->moveToFinalReviewWhenReady($case, (int) $request->user()->getAuthIdentifier());
        AuditLogger::record(
            $request,
            'separation.exit_interview_updated',
            'exit_interviews',
            $interview->getKey(),
            newValues: $interview->only([
                'separation_case_id', 'interviewer_user_id',
                'scheduled_at', 'employee_submitted_at', 'completed_at',
            ]),
        );

        return $this->success('Exit interview telah dikemas kini.');
    }

    public function updateSettlement(
        Request $request,
        SeparationCase $case,
    ): RedirectResponse {
        $this->authorizeManage($request, $case);
        $settlement = FinalSettlement::query()
            ->where('separation_case_id', $case->getKey())
            ->firstOrFail();
        abort_if($settlement->status === 'verified', 422);
        $validated = $request->validate([
            'salary_due' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'leave_encashment' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'gratuity' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'claims_due' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'other_payments' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'notice_deduction' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'loan_deduction' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'other_deductions' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'submit' => ['required', 'boolean'],
        ]);
        $assetDeduction = (float) $case->assets()->sum('charge_amount');
        $payments = collect(['salary_due', 'leave_encashment', 'gratuity', 'claims_due', 'other_payments'])
            ->sum(fn ($field) => (float) $validated[$field]);
        $deductions = collect(['notice_deduction', 'loan_deduction', 'other_deductions'])
            ->sum(fn ($field) => (float) $validated[$field]) + $assetDeduction;
        $settlement->update([
            ...collect($validated)->except('submit')->all(),
            'asset_deduction' => $assetDeduction,
            'net_amount' => round($payments - $deductions, 2),
            'status' => $validated['submit'] ? 'pending_verification' : 'draft',
            'prepared_by' => $request->user()->getAuthIdentifier(),
            'prepared_at' => now(),
            'verified_by' => null,
            'verified_at' => null,
        ]);
        AuditLogger::record(
            $request,
            $validated['submit'] ? 'separation.settlement_submitted' : 'separation.settlement_saved',
            'final_settlements',
            $settlement->getKey(),
            newValues: $settlement->only([
                'separation_case_id', 'net_amount', 'asset_deduction',
                'status', 'prepared_by', 'prepared_at',
            ]),
        );

        return $this->success($validated['submit'] ? 'Final settlement dihantar untuk pengesahan.' : 'Draf final settlement disimpan.');
    }

    public function verifySettlement(
        Request $request,
        SeparationCase $case,
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('separation.approve'), 403);
        $this->authorizeCaseView($request, $case);
        $settlement = FinalSettlement::query()
            ->where('separation_case_id', $case->getKey())
            ->firstOrFail();
        abort_unless($settlement->status === 'pending_verification', 422);
        abort_if($settlement->prepared_by === $request->user()->getAuthIdentifier(), 403);
        $settlement->update([
            'status' => 'verified',
            'verified_by' => $request->user()->getAuthIdentifier(),
            'verified_at' => now(),
        ]);
        $this->moveToFinalReviewWhenReady($case, (int) $request->user()->getAuthIdentifier());
        AuditLogger::record(
            $request,
            'separation.settlement_verified',
            'final_settlements',
            $settlement->getKey(),
            newValues: $settlement->only([
                'separation_case_id', 'net_amount', 'status',
                'verified_by', 'verified_at',
            ]),
        );

        return $this->success('Final settlement telah disahkan.');
    }

    public function generateDocument(
        Request $request,
        SeparationCase $case,
    ): RedirectResponse {
        $this->authorizeManage($request, $case);
        $validated = $request->validate([
            'kind' => ['required', Rule::in(['acceptance', 'clearance'])],
        ]);
        $document = $this->workflow->createDocument(
            $case,
            $validated['kind'],
            (int) $request->user()->getAuthIdentifier(),
        );
        AuditLogger::record(
            $request,
            'separation.hr_document_created',
            'hr_documents',
            $document->getKey(),
            newValues: [
                'separation_case_id' => $case->getKey(),
                'kind' => $validated['kind'],
                'template_code' => $document->template_code,
                'status' => $document->status,
            ],
        );

        return redirect()->route('hr-documents.index', ['search' => $case->employee_name])
            ->with('toast', [
                'type' => 'success',
                'message' => 'Draf surat telah dijana. Lengkapkan kelulusan melalui Dokumen & Surat HR.',
            ]);
    }

    public function complete(Request $request, SeparationCase $case): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('separation.approve'), 403);
        $this->authorizeCaseView($request, $case);
        abort_if(
            (int) $case->created_by === (int) $request->user()->getAuthIdentifier(),
            403,
            'Pencipta kes tidak boleh menutup kes yang sama.',
        );
        abort_unless(in_array($case->status, ['clearance', 'final_review'], true), 422);
        $case->loadMissing('template', 'interview', 'settlement');
        $blockers = $this->completionBlockers($case);
        if ($blockers !== []) {
            throw ValidationException::withMessages(['status' => implode(' ', $blockers)]);
        }
        $validated = $request->validate([
            'eligible_for_rehire' => ['required', 'boolean'],
            'closure_notes' => ['required', 'string', 'min:3', 'max:10000'],
        ]);
        $case->update([
            'status' => 'completed',
            'eligible_for_rehire' => (bool) $validated['eligible_for_rehire'],
            'closure_notes' => $validated['closure_notes'],
            'completed_by' => $request->user()->getAuthIdentifier(),
            'completed_at' => now(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        if ($case->employee_user_id) {
            $this->workflow->notify(
                (int) $case->employee_user_id,
                $case,
                'separation_completed',
                'Clearance selesai',
                "Kes {$case->case_number} telah ditutup. Dokumen akhir akan tersedia melalui Dokumen Saya selepas dikeluarkan.",
            );
        }
        AuditLogger::record(
            $request,
            'separation.case_completed',
            'separation_cases',
            $case->getKey(),
            newValues: $case->only([
                'status', 'eligible_for_rehire', 'completed_by', 'completed_at',
            ]),
        );

        return $this->success('Clearance lengkap dan kes pengakhiran telah ditutup.');
    }

    public function readNotifications(Request $request): RedirectResponse
    {
        SeparationNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    public function export(Request $request): StreamedResponse
    {
        $cases = $this->visibleQuery($request->user())
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn ($query) => $query
                    ->whereIn('status', ['completed', 'waived']),
            ])
            ->latest()
            ->get();
        AuditLogger::record(
            $request,
            'separation.report_exported',
            'separation_cases',
            'export-'.now()->format('YmdHis'),
            newValues: ['records' => $cases->count()],
        );

        return response()->streamDownload(function () use ($cases) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'No. Kes', 'No. Pekerja', 'Nama', 'Jabatan', 'Jawatan',
                'Jenis', 'Tarikh Notis', 'Tarikh Akhir Diluluskan', 'Status',
                'Tugasan Selesai', 'Jumlah Tugasan', 'Layak Diambil Semula',
            ]);
            foreach ($cases as $case) {
                fputcsv($handle, [
                    $this->csvCell($case->case_number),
                    $this->csvCell($case->employee_number),
                    $this->csvCell($case->employee_name),
                    $this->csvCell($case->department_name),
                    $this->csvCell($case->position_name),
                    $this->csvCell($case->separation_type),
                    $case->notice_submitted_date?->toDateString(),
                    $case->approved_last_day?->toDateString(),
                    $this->csvCell($case->status),
                    $case->completed_tasks_count,
                    $case->tasks_count,
                    $case->eligible_for_rehire === null ? '' : ($case->eligible_for_rehire ? 'Ya' : 'Tidak'),
                ]);
            }
            fclose($handle);
        }, 'laporan-berhenti-clearance-'.now()->format('Ymd').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function visibleQuery(User $user): Builder
    {
        $query = SeparationCase::query();
        if ($user->hasPermission('separation.manage')) {
            return $query;
        }

        return $query->where(fn (Builder $query) => $query
            ->where('supervisor_user_id', $user->getAuthIdentifier())
            ->orWhere('hr_approver_user_id', $user->getAuthIdentifier())
            ->orWhereHas('tasks', fn (Builder $query) => $query
                ->where('assigned_user_id', $user->getAuthIdentifier()))
            ->orWhereHas('handovers', fn (Builder $query) => $query
                ->where('recipient_user_id', $user->getAuthIdentifier())));
    }

    private function authorizeManage(Request $request, SeparationCase $case): void
    {
        abort_unless($request->user()->hasPermission('separation.manage'), 403);
    }

    private function authorizeCaseView(Request $request, SeparationCase $case): void
    {
        abort_unless(
            $this->visibleQuery($request->user())->whereKey($case->getKey())->exists(),
            403,
        );
    }

    /**
     * @return array<int, string>
     */
    private function caseRelations(): array
    {
        return [
            'template:id,name,exit_interview_required,final_settlement_required',
            'supervisor:id,name,email',
            'hrApprover:id,name,email',
            'tasks' => fn ($query) => $query
                ->with(['assignee:id,name,email', 'attachments'])
                ->orderBy('due_date')
                ->orderBy('id'),
            'attachments',
            'assets' => fn ($query) => $query->orderBy('asset_name'),
            'handovers' => fn ($query) => $query->with('recipient:id,name,email')->orderBy('due_date'),
            'interview.interviewer:id,name,email',
            'settlement',
            'acceptanceDocument:id,reference_number,status',
            'clearanceDocument:id,reference_number,status',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(SeparationCase $case): array
    {
        return [
            'id' => $case->getKey(),
            'case_number' => $case->case_number,
            'employee_name' => $case->employee_name,
            'employee_number' => $case->employee_number,
            'department_name' => $case->department_name,
            'separation_type' => $case->separation_type,
            'status' => $case->status,
            'approval_stage' => $case->approval_stage,
            'approved_last_day' => $case->approved_last_day?->toDateString(),
            'clearance_due_date' => $case->clearance_due_date?->toDateString(),
            'template' => $case->template?->name,
            'supervisor' => $case->supervisor?->name,
            'hr_approver' => $case->hrApprover?->name,
            'created_at' => $case->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function casePayload(SeparationCase $case, User $user): array
    {
        $data = $case->toArray();
        $data['completion_blockers'] = $this->completionBlockers($case);
        $canManage = $user->hasPermission('separation.manage');
        $canSeeSensitive = $canManage
            || $user->hasPermission('separation.approve')
            || $case->supervisor_user_id === $user->getAuthIdentifier();
        $data['tasks'] = $case->tasks->map(function (ClearanceTask $task) use ($user, $canManage, $canSeeSensitive) {
            $isAssignee = $task->assigned_user_id === $user->getAuthIdentifier();

            return [
                ...$task->toArray(),
                'attachments' => ($canSeeSensitive || $isAssignee)
                    ? $task->attachments->toArray()
                    : [],
                'can_act' => $canManage || $isAssignee,
            ];
        })->all();
        $data['attachments'] = $case->attachments
            ->filter(fn (SeparationAttachment $attachment) => $this->canAccessAttachment(
                $user,
                $case,
                $attachment,
            ))
            ->values()
            ->toArray();
        $data['handovers'] = $case->handovers->map(function (HandoverItem $handover) use ($user, $canManage) {
            return [
                ...$handover->toArray(),
                'can_review' => $canManage
                    || $handover->recipient_user_id === $user->getAuthIdentifier(),
            ];
        })->all();
        if (! $user->hasPermission('separation.manage') && ! $user->hasPermission('separation.approve')) {
            $data['settlement'] = $case->settlement
                ? ['status' => $case->settlement->status]
                : null;
            if ($case->interview) {
                $data['interview'] = collect($case->interview->toArray())
                    ->except([
                        'primary_reason', 'employment_experience_rating',
                        'manager_support_rating', 'would_recommend',
                        'positive_feedback', 'improvement_feedback',
                        'additional_feedback', 'hr_private_notes',
                    ])
                    ->all();
            }
        }
        $isAssignedSupervisor = $case->supervisor_user_id === $user->getAuthIdentifier();
        if (! $canManage && ! $user->hasPermission('separation.approve') && ! $isAssignedSupervisor) {
            $data['reason_category'] = null;
            $data['reason_details'] = 'Akses terhad kepada tugasan clearance yang ditugaskan.';
            $data['supervisor_notes'] = null;
            $data['hr_notes'] = null;
            $data['waiver_notes'] = null;
        }

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private function completionBlockers(SeparationCase $case): array
    {
        if (! in_array($case->status, ['clearance', 'final_review'], true)) {
            return [];
        }
        $case->loadMissing('template', 'interview', 'settlement');
        $blockers = [];
        $pendingTasks = $case->tasks()
            ->where('is_mandatory', true)
            ->whereNotIn('status', ['completed', 'waived'])
            ->count();
        if ($pendingTasks > 0) {
            $blockers[] = "{$pendingTasks} tugasan wajib belum selesai.";
        }
        $pendingAssets = $case->assets()->where('status', 'pending')->count();
        if ($pendingAssets > 0) {
            $blockers[] = "{$pendingAssets} aset belum diselesaikan.";
        }
        $pendingHandovers = $case->handovers()
            ->whereNotIn('status', ['accepted', 'waived'])
            ->count();
        if ($pendingHandovers > 0) {
            $blockers[] = "{$pendingHandovers} serahan tugas belum diterima.";
        }
        if ($case->template?->exit_interview_required && ! $case->interview?->completed_at) {
            $blockers[] = 'Exit interview belum lengkap.';
        }
        if ($case->template?->final_settlement_required && $case->settlement?->status !== 'verified') {
            $blockers[] = 'Final settlement belum disahkan.';
        }

        return $blockers;
    }

    private function moveToFinalReviewWhenReady(SeparationCase $case, int $actorId): void
    {
        if ($case->status === 'clearance' && $this->completionBlockers($case) === []) {
            $case->update(['status' => 'final_review', 'updated_by' => $actorId]);
        }
    }

    private function syncAssetDeduction(SeparationCase $case): void
    {
        $settlement = $case->settlement()->first();
        if (! $settlement || $settlement->status === 'verified') {
            return;
        }
        $assetDeduction = (float) $case->assets()->sum('charge_amount');
        $payments = collect([
            'salary_due', 'leave_encashment', 'gratuity',
            'claims_due', 'other_payments',
        ])->sum(fn (string $field) => (float) $settlement->{$field});
        $deductions = collect([
            'notice_deduction', 'loan_deduction', 'other_deductions',
        ])->sum(fn (string $field) => (float) $settlement->{$field}) + $assetDeduction;
        $settlement->update([
            'asset_deduction' => $assetDeduction,
            'net_amount' => round($payments - $deductions, 2),
        ]);
    }

    private function canAccessAttachment(
        User $user,
        SeparationCase $case,
        SeparationAttachment $attachment,
    ): bool {
        if ($user->hasPermission('separation.manage')
            || $user->hasPermission('separation.approve')
            || $case->supervisor_user_id === $user->getAuthIdentifier()) {
            return true;
        }
        if (! $attachment->clearance_task_id) {
            return false;
        }

        return ClearanceTask::query()
            ->whereKey($attachment->clearance_task_id)
            ->where('separation_case_id', $case->getKey())
            ->where('assigned_user_id', $user->getAuthIdentifier())
            ->exists();
    }

    /**
     * @return array<int, array{id: int, name: string, email: string}>
     */
    private function usersWithPermission(string $permission): array
    {
        return User::query()
            ->with('roleAssignments')
            ->orderBy('name')
            ->get()
            ->filter(fn (User $user) => $user->hasPermission($permission))
            ->map(fn (User $user) => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values()
            ->all();
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');
        if (preg_match('/^[=+\-@]/', $value)) {
            return "'{$value}";
        }

        return $value;
    }

    private function success(string $message): RedirectResponse
    {
        return back()->with('toast', ['type' => 'success', 'message' => $message]);
    }
}
