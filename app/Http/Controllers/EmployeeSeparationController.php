<?php

namespace App\Http\Controllers;

use App\Models\ClearanceTask;
use App\Models\ExitInterview;
use App\Models\HandoverItem;
use App\Models\PerformanceSupervisorAssignment;
use App\Models\SeparationAttachment;
use App\Models\SeparationCase;
use App\Models\SeparationNotification;
use App\Models\SeparationTemplate;
use App\Support\AuditLogger;
use App\Support\SeparationWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class EmployeeSeparationController extends Controller
{
    public function __construct(private readonly SeparationWorkflow $workflow) {}

    public function index(Request $request): Response
    {
        $userId = (int) $request->user()->getAuthIdentifier();
        $employee = null;
        try {
            $employee = $this->workflow->employeeSnapshot($userId);
        } catch (ValidationException) {
            // The page remains available so HR can resolve a missing employee link.
        }
        $cases = SeparationCase::query()
            ->forEmployee($userId)
            ->with([
                'template:id,name,exit_interview_required,final_settlement_required',
                'tasks' => fn ($query) => $query
                    ->with('assignee:id,name,email')
                    ->orderBy('due_date')
                    ->orderBy('id'),
                'tasks.attachments' => fn ($query) => $query
                    ->where(fn ($query) => $query
                        ->where('visible_to_employee', true)
                        ->orWhere('uploaded_by', $userId)),
                'assets' => fn ($query) => $query->orderBy('asset_name'),
                'handovers' => fn ($query) => $query
                    ->with('recipient:id,name,email')
                    ->orderBy('due_date'),
                'interview',
                'settlement',
                'acceptanceDocument:id,reference_number,status',
                'clearanceDocument:id,reference_number,status',
            ])
            ->latest()
            ->get()
            ->map(fn (SeparationCase $case) => $this->payload($case));
        $notifications = SeparationNotification::query()
            ->where('user_id', $userId)
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('EmployeeSelfService/Separation', [
            'employeeLinked' => $employee !== null,
            'employee' => $employee,
            'templates' => SeparationTemplate::query()
                ->where('is_active', true)
                ->where('employee_can_apply', true)
                ->orderBy('name')
                ->get([
                    'id', 'name', 'description', 'separation_type',
                    'minimum_notice_days',
                ]),
            'cases' => $cases,
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
        $validated = $request->validate([
            'separation_template_id' => ['required', 'integer', 'exists:separation_templates,id'],
            'reason_category' => ['nullable', 'string', 'max:80'],
            'reason_details' => ['required', 'string', 'min:10', 'max:10000'],
            'proposed_last_day' => ['required', 'date', 'after:today'],
        ]);
        $userId = (int) $request->user()->getAuthIdentifier();
        $template = SeparationTemplate::query()
            ->where('is_active', true)
            ->where('employee_can_apply', true)
            ->findOrFail($validated['separation_template_id']);
        $hasOpenCase = SeparationCase::query()
            ->forEmployee($userId)
            ->whereNotIn('status', ['completed', 'rejected', 'cancelled'])
            ->exists();
        if ($hasOpenCase) {
            throw ValidationException::withMessages([
                'separation_template_id' => 'Anda sudah mempunyai satu kes pengakhiran yang masih aktif.',
            ]);
        }
        $snapshot = $this->workflow->employeeSnapshot($userId);
        $supervisorId = null;
        if (Schema::hasTable('performance_supervisor_assignments') && $snapshot['department_id']) {
            $supervisorId = PerformanceSupervisorAssignment::query()
                ->where('department_id', $snapshot['department_id'])
                ->where('is_active', true)
                ->value('supervisor_user_id');
        }
        if ((int) $supervisorId === $userId) {
            $supervisorId = null;
        }
        $noticeDate = today();
        $proposed = Carbon::parse($validated['proposed_last_day']);
        $served = $noticeDate->diffInDays($proposed, false);
        if ($served < 1) {
            throw ValidationException::withMessages([
                'proposed_last_day' => 'Tarikh akhir mestilah selepas tarikh notis.',
            ]);
        }
        $hrApproverId = $this->workflow->defaultHrApprover($template, $userId);
        if (! $hrApproverId) {
            throw ValidationException::withMessages([
                'separation_template_id' => 'Tiada pelulus HR bebas tersedia. Hubungi HR untuk menetapkan pelulus.',
            ]);
        }
        $case = SeparationCase::query()->create([
            ...$snapshot,
            'case_number' => $this->workflow->nextCaseNumber(),
            'separation_template_id' => $template->getKey(),
            'separation_type' => $template->separation_type ?? 'resignation',
            'initiated_by_employee' => true,
            'reason_category' => $validated['reason_category'] ?? null,
            'reason_details' => $validated['reason_details'],
            'notice_submitted_date' => $noticeDate,
            'proposed_last_day' => $proposed,
            'notice_days_required' => $template->minimum_notice_days,
            'notice_days_served' => max(0, $served),
            'notice_shortfall_days' => max(0, $template->minimum_notice_days - $served),
            'status' => 'pending_approval',
            'approval_stage' => $supervisorId ? 'supervisor' : 'hr',
            'supervisor_user_id' => $supervisorId,
            'hr_approver_user_id' => $hrApproverId,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
        if ($supervisorId) {
            $this->workflow->notify(
                (int) $supervisorId,
                $case,
                'supervisor_approval_required',
                'Notis berhenti menunggu semakan',
                "Kes {$case->case_number} memerlukan semakan penyelia.",
            );
        } elseif ($case->hr_approver_user_id) {
            $this->workflow->notify(
                (int) $case->hr_approver_user_id,
                $case,
                'hr_approval_required',
                'Notis berhenti menunggu kelulusan HR',
                "Kes {$case->case_number} memerlukan kelulusan HR.",
            );
        }
        AuditLogger::record(
            $request,
            'separation.employee_submitted',
            'separation_cases',
            $case->getKey(),
            newValues: $case->only([
                'case_number', 'employee_id', 'separation_type',
                'proposed_last_day', 'notice_days_required',
                'notice_days_served', 'notice_shortfall_days', 'status',
                'approval_stage', 'supervisor_user_id', 'hr_approver_user_id',
            ]),
        );

        return $this->success('Notis berhenti telah dihantar untuk semakan.');
    }

    public function cancel(
        Request $request,
        SeparationCase $case,
    ): RedirectResponse {
        $this->authorizeEmployee($request, $case);
        if ($case->status !== 'pending_approval' || $case->supervisor_decision) {
            throw ValidationException::withMessages([
                'status' => 'Permohonan tidak lagi boleh dibatalkan sendiri. Hubungi HR.',
            ]);
        }
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
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
            'separation.employee_cancelled',
            'separation_cases',
            $case->getKey(),
            newValues: ['status' => 'cancelled', 'reason' => $validated['reason']],
        );

        return $this->success('Permohonan telah dibatalkan.');
    }

    public function submitTask(
        Request $request,
        SeparationCase $case,
        ClearanceTask $task,
    ): RedirectResponse {
        $this->authorizeEmployee($request, $case);
        abort_unless($task->separation_case_id === $case->getKey(), 404);
        abort_unless($task->employee_action_required || $task->owner_type === 'employee', 403);
        if (! in_array($task->status, ['pending', 'in_progress', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Tugasan ini tidak lagi boleh dihantar.',
            ]);
        }
        $validated = $request->validate([
            'submission_notes' => ['required', 'string', 'min:3', 'max:5000'],
        ]);
        if ($task->evidence_required && ! $task->attachments()->exists()) {
            throw ValidationException::withMessages([
                'attachment' => 'Muat naik bukti sebelum menghantar tugasan ini.',
            ]);
        }
        $task->update([
            'status' => 'submitted',
            'submission_notes' => $validated['submission_notes'],
            'submitted_by' => $request->user()->getAuthIdentifier(),
            'submitted_at' => now(),
            'review_notes' => null,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        if ($task->assigned_user_id && $task->assigned_user_id !== $request->user()->getAuthIdentifier()) {
            $this->workflow->notify(
                (int) $task->assigned_user_id,
                $case,
                'clearance_task_submitted',
                'Tugasan clearance sedia disemak',
                "{$task->title} bagi {$case->case_number} telah dihantar oleh pekerja.",
            );
        }
        AuditLogger::record(
            $request,
            'separation.employee_task_submitted',
            'clearance_tasks',
            $task->getKey(),
            newValues: $task->only(['separation_case_id', 'title', 'status', 'submitted_at']),
        );

        return $this->success('Tugasan telah dihantar untuk pengesahan.');
    }

    public function uploadAttachment(
        Request $request,
        SeparationCase $case,
        ClearanceTask $task,
    ): RedirectResponse {
        $this->authorizeEmployee($request, $case);
        abort_unless($task->separation_case_id === $case->getKey(), 404);
        abort_unless($task->employee_action_required || $task->owner_type === 'employee', 403);
        $validated = $request->validate([
            'attachment' => ['required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png,xlsx', 'max:10240'],
        ]);
        $file = $request->file('attachment');
        $path = $file->store("separations/{$case->getKey()}/tasks/{$task->getKey()}", 'local');
        $attachment = SeparationAttachment::query()->create([
            'separation_case_id' => $case->getKey(),
            'clearance_task_id' => $task->getKey(),
            'context' => 'task_evidence',
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'visible_to_employee' => true,
            'uploaded_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'separation.employee_attachment_uploaded',
            'separation_attachments',
            $attachment->getKey(),
            newValues: $attachment->only([
                'separation_case_id', 'clearance_task_id', 'original_name', 'size',
            ]),
        );

        return $this->success('Bukti tugasan telah dimuat naik.');
    }

    public function storeHandover(
        Request $request,
        SeparationCase $case,
    ): RedirectResponse {
        $this->authorizeEmployee($request, $case);
        abort_unless(in_array($case->status, ['clearance', 'final_review'], true), 422);
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'min:5', 'max:10000'],
            'recipient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ]);
        $handover = $case->handovers()->create([
            ...$validated,
            'recipient_user_id' => $validated['recipient_user_id'] ?? $case->supervisor_user_id,
            'status' => 'pending',
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'separation.handover_created',
            'handover_items',
            $handover->getKey(),
            newValues: $handover->only([
                'separation_case_id', 'title', 'recipient_user_id', 'due_date', 'status',
            ]),
        );

        return $this->success('Item serahan tugas telah ditambah.');
    }

    public function submitHandover(
        Request $request,
        SeparationCase $case,
        HandoverItem $handover,
    ): RedirectResponse {
        $this->authorizeEmployee($request, $case);
        abort_unless($handover->separation_case_id === $case->getKey(), 404);
        abort_unless(in_array($handover->status, ['pending', 'rejected'], true), 422);
        $validated = $request->validate([
            'submission_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $handover->update([
            'status' => 'submitted',
            'submission_notes' => $validated['submission_notes'] ?? null,
            'submitted_by' => $request->user()->getAuthIdentifier(),
            'submitted_at' => now(),
            'review_notes' => null,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        if ($handover->recipient_user_id) {
            $this->workflow->notify(
                (int) $handover->recipient_user_id,
                $case,
                'handover_submitted',
                'Serahan tugas menunggu pengesahan',
                "{$handover->title} bagi {$case->case_number} sedia disemak.",
            );
        }
        AuditLogger::record(
            $request,
            'separation.handover_submitted',
            'handover_items',
            $handover->getKey(),
            newValues: ['status' => 'submitted', 'submitted_at' => $handover->submitted_at],
        );

        return $this->success('Serahan tugas telah dihantar.');
    }

    public function submitInterview(
        Request $request,
        SeparationCase $case,
    ): RedirectResponse {
        $this->authorizeEmployee($request, $case);
        abort_unless(in_array($case->status, ['clearance', 'final_review'], true), 422);
        $interview = ExitInterview::query()
            ->where('separation_case_id', $case->getKey())
            ->firstOrFail();
        if ($interview->employee_submitted_at) {
            throw ValidationException::withMessages([
                'interview' => 'Maklum balas exit interview telah dihantar.',
            ]);
        }
        $validated = $request->validate([
            'primary_reason' => ['required', 'string', 'max:100'],
            'employment_experience_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'manager_support_rating' => ['required', 'integer', 'min:1', 'max:5'],
            'would_recommend' => ['required', 'boolean'],
            'positive_feedback' => ['nullable', 'string', 'max:10000'],
            'improvement_feedback' => ['nullable', 'string', 'max:10000'],
            'additional_feedback' => ['nullable', 'string', 'max:10000'],
        ]);
        $interview->update([...$validated, 'employee_submitted_at' => now()]);
        AuditLogger::record(
            $request,
            'separation.exit_interview_submitted',
            'exit_interviews',
            $interview->getKey(),
            newValues: [
                'separation_case_id' => $case->getKey(),
                'primary_reason' => $interview->primary_reason,
                'employment_experience_rating' => $interview->employment_experience_rating,
                'manager_support_rating' => $interview->manager_support_rating,
                'employee_submitted_at' => $interview->employee_submitted_at,
            ],
        );

        return $this->success('Maklum balas exit interview telah dihantar kepada HR.');
    }

    public function downloadAttachment(
        Request $request,
        SeparationCase $case,
        SeparationAttachment $attachment,
    ): HttpResponse {
        $this->authorizeEmployee($request, $case);
        abort_unless($attachment->separation_case_id === $case->getKey(), 404);
        abort_unless(
            $attachment->visible_to_employee
                || $attachment->uploaded_by === $request->user()->getAuthIdentifier(),
            403,
        );

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
            ['Cache-Control' => 'private, no-store'],
        );
    }

    public function readNotifications(Request $request): RedirectResponse
    {
        SeparationNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SeparationCase $case): array
    {
        $data = $case->toArray();
        if ($case->settlement?->status !== 'verified') {
            $data['settlement'] = $case->settlement
                ? ['status' => $case->settlement->status]
                : null;
        } else {
            $data['settlement'] = [
                'status' => 'verified',
                'net_amount' => $case->settlement->net_amount,
                'verified_at' => $case->settlement->verified_at?->toIso8601String(),
            ];
        }
        if ($case->interview) {
            $data['interview'] = collect($case->interview->toArray())
                ->except(['hr_private_notes'])
                ->all();
        }

        return $data;
    }

    private function authorizeEmployee(Request $request, SeparationCase $case): void
    {
        abort_unless(
            $case->employee_user_id === $request->user()->getAuthIdentifier(),
            403,
        );
    }

    private function success(string $message): RedirectResponse
    {
        return back()->with('toast', ['type' => 'success', 'message' => $message]);
    }
}
