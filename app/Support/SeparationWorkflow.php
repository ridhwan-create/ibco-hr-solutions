<?php

namespace App\Support;

use App\Models\ClearanceTask;
use App\Models\DocumentTemplate;
use App\Models\ExitInterview;
use App\Models\FinalSettlement;
use App\Models\HrDocument;
use App\Models\SeparationCase;
use App\Models\SeparationNotification;
use App\Models\SeparationTemplate;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SeparationWorkflow
{
    public function __construct(private readonly TrainingEmployeeResolver $employees) {}

    /**
     * @return array<string, mixed>
     */
    public function employeeSnapshot(int $userId): array
    {
        $employee = $this->employees->forUser($userId);
        $option = $this->employees->linkedOptions()->firstWhere('user_id', $userId);
        if (! $employee || ! $option) {
            throw ValidationException::withMessages([
                'employee_user_id' => 'Akaun ini tidak dipautkan kepada rekod pekerja aktif.',
            ]);
        }

        return [
            'employee_user_id' => $userId,
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->name,
            'employee_email' => $option['email'] ?? null,
            'department_id' => $employee->department_id,
            'department_name' => $employee->department_name,
            'position_name' => $employee->position_name,
        ];
    }

    public function nextCaseNumber(): string
    {
        return DB::transaction(function () {
            $year = (int) now()->format('Y');
            $sequence = DB::table('separation_sequences')
                ->where('year', $year)
                ->lockForUpdate()
                ->first();
            if (! $sequence) {
                DB::table('separation_sequences')->insert([
                    'year' => $year,
                    'next_number' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $number = 1;
            } else {
                $number = (int) $sequence->next_number;
                DB::table('separation_sequences')
                    ->where('year', $year)
                    ->update([
                        'next_number' => $number + 1,
                        'updated_at' => now(),
                    ]);
            }

            return sprintf('SEP/%d/%05d', $year, $number);
        });
    }

    public function initializeClearance(SeparationCase $case, int $actorId): void
    {
        $case->loadMissing('template.items');
        if (! $case->template || $case->template->items->isEmpty()) {
            throw ValidationException::withMessages([
                'template' => 'Template clearance tidak mempunyai tugasan. Lengkapkan Tetapan Clearance dahulu.',
            ]);
        }

        DB::transaction(function () use ($case, $actorId) {
            $lastDay = $case->approved_last_day ?? $case->proposed_last_day;
            foreach ($case->template->items as $item) {
                $assignee = match ($item->owner_type) {
                    'employee' => $case->employee_user_id,
                    'supervisor' => $case->supervisor_user_id,
                    'hr' => $actorId,
                    default => $item->assignee_user_id,
                };
                ClearanceTask::query()->firstOrCreate(
                    [
                        'separation_case_id' => $case->getKey(),
                        'clearance_template_item_id' => $item->getKey(),
                    ],
                    [
                        'title' => $item->title,
                        'description' => $item->description,
                        'owner_type' => $item->owner_type,
                        'assigned_user_id' => $assignee,
                        'is_mandatory' => $item->is_mandatory,
                        'employee_action_required' => $item->employee_action_required,
                        'evidence_required' => $item->evidence_required,
                        'due_date' => $lastDay
                            ? Carbon::parse($lastDay)->addDays($item->due_offset_days)->toDateString()
                            : null,
                        'status' => 'pending',
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ],
                );
            }
            if ($case->template->exit_interview_required) {
                ExitInterview::query()->firstOrCreate([
                    'separation_case_id' => $case->getKey(),
                ]);
            }
            if ($case->template->final_settlement_required) {
                FinalSettlement::query()->firstOrCreate([
                    'separation_case_id' => $case->getKey(),
                ]);
            }
            $case->update([
                'status' => 'clearance',
                'approval_stage' => null,
                'clearance_started_at' => now(),
                'clearance_due_date' => $lastDay,
                'updated_by' => $actorId,
            ]);

            $recipients = $case->tasks()
                ->whereNotNull('assigned_user_id')
                ->pluck('assigned_user_id')
                ->push($case->employee_user_id)
                ->filter()
                ->unique();
            foreach ($recipients as $userId) {
                $this->notify(
                    (int) $userId,
                    $case,
                    'clearance_started',
                    'Proses clearance dimulakan',
                    "Kes {$case->case_number} telah diluluskan dan tugasan clearance kini dibuka.",
                );
            }
        });
    }

    public function createDocument(
        SeparationCase $case,
        string $kind,
        int $actorId,
    ): HrDocument {
        $code = $kind === 'clearance' ? 'EMP-CLEARANCE' : 'EMP-RESIGN-ACK';
        $template = DocumentTemplate::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();
        if (! $template) {
            throw ValidationException::withMessages([
                'document' => "Template dokumen {$code} belum tersedia. Jalankan SeparationClearanceSeeder.",
            ]);
        }
        if ($kind === 'clearance' && $case->status !== 'completed') {
            throw ValidationException::withMessages([
                'document' => 'Pengesahan clearance hanya boleh dijana selepas kes ditutup.',
            ]);
        }
        $column = $kind === 'clearance'
            ? 'clearance_document_id'
            : 'acceptance_document_id';
        if ($case->{$column}) {
            return HrDocument::query()->findOrFail($case->{$column});
        }
        $approver = $template->approver_user_id ?? $case->hr_approver_user_id;
        if ($approver && ! User::query()->find($approver)?->hasPermission('documents.approve')) {
            $approver = null;
        }
        $document = HrDocument::query()->create([
            'document_template_id' => $template->getKey(),
            'template_code' => $template->code,
            'template_name' => $template->name,
            'category' => $template->category,
            'employee_user_id' => $case->employee_user_id,
            'employee_id' => $case->employee_id,
            'employee_number' => $case->employee_number,
            'employee_name' => $case->employee_name,
            'employee_email' => $case->employee_email,
            'department_id' => $case->department_id,
            'department_name' => $case->department_name,
            'position_name' => $case->position_name,
            'source_type' => 'separation',
            'source_id' => $case->getKey(),
            'subject' => $template->subject_template,
            'body' => $template->body_template,
            'template_snapshot' => $template->only([
                'code', 'name', 'category', 'subject_template', 'body_template',
                'available_variables', 'sequence_key', 'requires_approval',
                'acknowledgement_required', 'default_validity_months',
                'confidentiality',
            ]),
            'custom_variables' => [
                'case_number' => $case->case_number,
                'separation_type' => $case->separation_type,
                'last_working_date' => ($case->approved_last_day ?? $case->proposed_last_day)?->toDateString(),
            ],
            'status' => 'draft',
            'approval_required' => $template->requires_approval,
            'approver_user_id' => $approver,
            'effective_date' => $case->approved_last_day ?? $case->proposed_last_day,
            'acknowledgement_required' => $template->acknowledgement_required,
            'confidentiality' => $template->confidentiality,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
        $case->update([$column => $document->getKey(), 'updated_by' => $actorId]);

        return $document;
    }

    public function notify(
        int $userId,
        SeparationCase $case,
        string $type,
        string $title,
        string $message,
    ): void {
        SeparationNotification::query()->create([
            'user_id' => $userId,
            'separation_case_id' => $case->getKey(),
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);
    }

    public function defaultHrApprover(
        ?SeparationTemplate $template = null,
        ?int $excludeUserId = null,
    ): ?int
    {
        if ($template?->approver_user_id
            && $template->approver_user_id !== $excludeUserId
            && User::query()->find($template->approver_user_id)?->hasPermission('separation.approve')) {
            return $template->approver_user_id;
        }

        return User::query()
            ->with('roleAssignments')
            ->orderBy('id')
            ->get()
            ->first(fn (User $user) => $user->getKey() !== $excludeUserId
                && $user->hasPermission('separation.approve'))
            ?->getKey();
    }
}
