<?php

namespace App\Http\Controllers;

use App\Models\DocumentTemplate;
use App\Models\HrDocument;
use App\Models\HrDocumentAttachment;
use App\Models\HrDocumentNotification;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\DocumentReferenceGenerator;
use App\Support\HrDocumentRenderer;
use App\Support\TrainingEmployeeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HrDocumentController extends Controller
{
    public function __construct(
        private readonly TrainingEmployeeResolver $employees,
        private readonly DocumentReferenceGenerator $references,
        private readonly HrDocumentRenderer $renderer,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([...HrDocument::STATUSES, 'expired'])],
            'category' => ['nullable', Rule::in(DocumentTemplate::CATEGORIES)],
            'expiry' => ['nullable', Rule::in(['all', '30_days', 'expired', 'none'])],
        ]);
        $search = trim($validated['search'] ?? '');
        $status = $validated['status'] ?? '';
        $category = $validated['category'] ?? '';
        $expiry = $validated['expiry'] ?? 'all';
        $query = $this->visibleQuery($request->user())
            ->with([
                'approver:id,name,email',
                'creator:id,name,email',
                'attachments:id,hr_document_id,attachment_type,original_name,mime_type,size,visible_to_employee,created_at',
            ])
            ->when($search !== '', fn (Builder $query) => $query
                ->where(function (Builder $query) use ($search) {
                    $query->where('reference_number', 'like', "%{$search}%")
                        ->orWhere('employee_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('template_name', 'like', "%{$search}%");
                }))
            ->when($category !== '', fn (Builder $query) => $query->where('category', $category))
            ->when($status !== '', function (Builder $query) use ($status) {
                if ($status === 'expired') {
                    $query->whereIn('status', ['issued', 'acknowledged'])
                        ->whereDate('expiry_date', '<', today());
                } else {
                    $query->where('status', $status);
                }
            })
            ->when($expiry === '30_days', fn (Builder $query) => $query
                ->whereIn('status', ['issued', 'acknowledged'])
                ->whereBetween('expiry_date', [today(), today()->addDays(30)]))
            ->when($expiry === 'expired', fn (Builder $query) => $query
                ->whereIn('status', ['issued', 'acknowledged'])
                ->whereDate('expiry_date', '<', today()))
            ->when($expiry === 'none', fn (Builder $query) => $query->whereNull('expiry_date'));

        $documents = $query
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (HrDocument $document) => $this->payload($document));
        $base = $this->visibleQuery($request->user());
        $notifications = HrDocumentNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->latest()
            ->limit(20)
            ->get();

        return Inertia::render('HrDocuments/Index', [
            'documents' => $documents,
            'templates' => DocumentTemplate::query()
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get([
                    'id', 'code', 'name', 'category', 'subject_template',
                    'body_template', 'available_variables', 'requires_approval',
                    'approver_user_id',
                    'acknowledgement_required', 'default_validity_months',
                    'confidentiality',
                ]),
            'employees' => $request->user()->hasPermission('documents.manage')
                ? $this->employees->linkedOptions()
                : [],
            'approvers' => User::query()
                ->with('roleAssignments')
                ->orderBy('name')
                ->get()
                ->filter(fn (User $user) => $user->hasPermission('documents.approve'))
                ->values()
                ->map(fn (User $user) => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
            'filters' => [
                'search' => $search,
                'status' => $status,
                'category' => $category,
                'expiry' => $expiry,
            ],
            'categories' => DocumentTemplate::CATEGORIES,
            'sources' => HrDocument::SOURCES,
            'statistics' => [
                'total' => (clone $base)->count(),
                'draft' => (clone $base)->where('status', 'draft')->count(),
                'pending' => (clone $base)->where('status', 'pending_approval')->count(),
                'issued' => (clone $base)->whereIn('status', ['issued', 'acknowledged'])->count(),
                'expiring' => (clone $base)
                    ->whereIn('status', ['issued', 'acknowledged'])
                    ->whereBetween('expiry_date', [today(), today()->addDays(30)])
                    ->count(),
                'expired' => (clone $base)
                    ->whereIn('status', ['issued', 'acknowledged'])
                    ->whereDate('expiry_date', '<', today())
                    ->count(),
            ],
            'canManage' => $request->user()->hasPermission('documents.manage'),
            'canApprove' => $request->user()->hasPermission('documents.approve'),
            'notifications' => $notifications->map(fn (HrDocumentNotification $notification) => [
                'id' => $notification->getKey(),
                'document_id' => $notification->hr_document_id,
                'type' => $notification->type,
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
        HrDocumentNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    public function store(Request $request): RedirectResponse
    {
        $expiryRules = ['nullable', 'date'];
        if ($request->filled('effective_date')) {
            $expiryRules[] = 'after_or_equal:effective_date';
        }
        $validated = $request->validate([
            'document_template_id' => ['required', 'integer', 'exists:document_templates,id'],
            'employee_user_id' => ['required', 'integer', 'exists:users,id'],
            'source_type' => ['required', Rule::in(HrDocument::SOURCES)],
            'source_id' => ['nullable', 'integer', 'min:1'],
            'effective_date' => ['nullable', 'date'],
            'expiry_date' => $expiryRules,
            'signatory_name' => ['nullable', 'string', 'max:180'],
            'signatory_position' => ['nullable', 'string', 'max:180'],
            'approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'custom_variables' => ['nullable', 'array', 'max:30'],
            'custom_variables.*' => ['nullable', 'string', 'max:500'],
        ]);
        $template = DocumentTemplate::query()
            ->where('is_active', true)
            ->findOrFail($validated['document_template_id']);
        $employee = $this->employees->forUser((int) $validated['employee_user_id']);
        if (! $employee) {
            throw ValidationException::withMessages([
                'employee_user_id' => 'Akaun ini tidak dipautkan kepada rekod pekerja aktif.',
            ]);
        }
        $employeeOption = $this->employees->linkedOptions()
            ->firstWhere('user_id', (int) $validated['employee_user_id']);
        $approverId = $validated['approver_user_id'] ?? $template->approver_user_id;
        $this->assertApprover($approverId);
        $expiryDate = $validated['expiry_date'] ?? null;
        if (! $expiryDate && $validated['effective_date'] && $template->default_validity_months) {
            $expiryDate = Carbon::parse($validated['effective_date'])
                ->addMonths($template->default_validity_months)
                ->toDateString();
        }

        $document = HrDocument::query()->create([
            'document_template_id' => $template->getKey(),
            'template_code' => $template->code,
            'template_name' => $template->name,
            'category' => $template->category,
            'employee_user_id' => $validated['employee_user_id'],
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->name,
            'employee_email' => $employeeOption['email'] ?? null,
            'department_id' => $employee->department_id,
            'department_name' => $employee->department_name,
            'position_name' => $employee->position_name,
            'source_type' => $validated['source_type'],
            'source_id' => $validated['source_id'] ?? null,
            'subject' => $template->subject_template,
            'body' => $template->body_template,
            'custom_variables' => collect($validated['custom_variables'] ?? [])
                ->filter(fn ($value, $key) => is_string($key)
                    && preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $key)
                    && filled($value))
                ->map(fn ($value) => (string) $value)
                ->all(),
            'template_snapshot' => $template->only([
                'code', 'name', 'category', 'subject_template', 'body_template',
                'available_variables', 'sequence_key', 'requires_approval',
                'acknowledgement_required', 'default_validity_months',
                'confidentiality',
            ]),
            'signatory_name' => $validated['signatory_name'] ?? null,
            'signatory_position' => $validated['signatory_position'] ?? null,
            'internal_notes' => $validated['internal_notes'] ?? null,
            'status' => 'draft',
            'approval_required' => $template->requires_approval,
            'approver_user_id' => $approverId,
            'effective_date' => $validated['effective_date'] ?? null,
            'expiry_date' => $expiryDate,
            'acknowledgement_required' => $template->acknowledgement_required,
            'confidentiality' => $template->confidentiality,
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'hr_document.created',
            'hr_documents',
            $document->getKey(),
            newValues: $document->only([
                'template_code', 'employee_id', 'category', 'source_type',
                'approval_required', 'approver_user_id', 'status',
            ]),
        );

        return $this->success('Draf dokumen HR telah dijana.');
    }

    public function update(Request $request, HrDocument $document): RedirectResponse
    {
        $this->authorizeManage($request, $document);
        if (! in_array($document->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Hanya dokumen draf atau ditolak boleh dikemas kini.',
            ]);
        }
        $expiryRules = ['nullable', 'date'];
        if ($request->filled('effective_date')) {
            $expiryRules[] = 'after_or_equal:effective_date';
        }
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:20000'],
            'source_type' => ['required', Rule::in(HrDocument::SOURCES)],
            'source_id' => ['nullable', 'integer', 'min:1'],
            'effective_date' => ['nullable', 'date'],
            'expiry_date' => $expiryRules,
            'signatory_name' => ['nullable', 'string', 'max:180'],
            'signatory_position' => ['nullable', 'string', 'max:180'],
            'approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'acknowledgement_required' => ['required', 'boolean'],
            'confidentiality' => [
                'required', Rule::in(DocumentTemplate::CONFIDENTIALITY_LEVELS),
            ],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $this->assertApprover($validated['approver_user_id'] ?? null);
        $old = $document->only([
            'subject', 'body', 'source_type', 'source_id', 'effective_date',
            'expiry_date', 'signatory_name', 'signatory_position',
            'approver_user_id', 'acknowledgement_required', 'confidentiality',
            'internal_notes', 'status',
        ]);
        $document->update([
            ...$validated,
            'status' => 'draft',
            'rejected_by' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'hr_document.updated',
            'hr_documents',
            $document->getKey(),
            oldValues: $old,
            newValues: $document->only(array_keys($old)),
        );

        return $this->success('Draf dokumen HR telah dikemas kini.');
    }

    public function submit(Request $request, HrDocument $document): RedirectResponse
    {
        $this->authorizeManage($request, $document);
        if ($document->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Hanya dokumen draf boleh dihantar untuk kelulusan.',
            ]);
        }
        if ($document->approval_required && ! $document->approver_user_id) {
            $hasDefaultApprover = User::query()
                ->with('roleAssignments')
                ->get()
                ->contains(fn (User $user) => $user->hasPermission('documents.approve'));
            if (! $hasDefaultApprover) {
                throw ValidationException::withMessages([
                    'approver_user_id' => 'Tetapkan pelulus dokumen sebelum menghantar draf.',
                ]);
            }
        }
        $oldStatus = $document->status;
        $document->update([
            'status' => $document->approval_required ? 'pending_approval' : 'approved',
            'submitted_by' => $request->user()->getAuthIdentifier(),
            'submitted_at' => now(),
            'approved_by' => $document->approval_required
                ? null
                : $request->user()->getAuthIdentifier(),
            'approved_at' => $document->approval_required ? null : now(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        if ($document->approval_required) {
            $this->notifyApprovers($document);
        }
        AuditLogger::record(
            $request,
            'hr_document.submitted',
            'hr_documents',
            $document->getKey(),
            oldValues: ['status' => $oldStatus],
            newValues: [
                'status' => $document->status,
                'approver_user_id' => $document->approver_user_id,
            ],
        );

        return $this->success(
            $document->approval_required
                ? 'Dokumen telah dihantar untuk kelulusan.'
                : 'Dokumen sedia untuk dikeluarkan.',
        );
    }

    public function review(Request $request, HrDocument $document): RedirectResponse
    {
        $this->authorizeApproval($request, $document);
        abort_if(
            in_array(
                (int) $request->user()->getAuthIdentifier(),
                array_filter([(int) $document->created_by, (int) $document->submitted_by]),
                true,
            ),
            403,
            'Penyedia atau penghantar dokumen tidak boleh meluluskan dokumen yang sama.',
        );
        if ($document->status !== 'pending_approval') {
            throw ValidationException::withMessages([
                'status' => 'Dokumen ini tidak lagi menunggu kelulusan.',
            ]);
        }
        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'notes' => [
                Rule::requiredIf($request->input('action') === 'reject'),
                'nullable', 'string', 'max:5000',
            ],
        ]);
        $approved = $validated['action'] === 'approve';
        $oldStatus = $document->status;
        $document->update($approved
            ? [
                'status' => 'approved',
                'approved_by' => $request->user()->getAuthIdentifier(),
                'approved_at' => now(),
                'approval_notes' => $validated['notes'] ?? null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]
            : [
                'status' => 'rejected',
                'rejected_by' => $request->user()->getAuthIdentifier(),
                'rejected_at' => now(),
                'rejection_reason' => $validated['notes'],
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
        $this->notifyCreator(
            $document,
            $approved ? 'document_approved' : 'document_rejected',
            $approved ? 'Dokumen HR diluluskan' : 'Dokumen HR ditolak',
            $approved
                ? "Dokumen untuk {$document->employee_name} telah diluluskan dan sedia dikeluarkan."
                : "Dokumen untuk {$document->employee_name} telah ditolak untuk pembetulan.",
        );
        AuditLogger::record(
            $request,
            $approved ? 'hr_document.approved' : 'hr_document.rejected',
            'hr_documents',
            $document->getKey(),
            oldValues: ['status' => $oldStatus],
            newValues: [
                'status' => $document->status,
                'notes' => $validated['notes'] ?? null,
            ],
        );

        return $this->success(
            $approved ? 'Dokumen telah diluluskan.' : 'Dokumen telah ditolak.',
        );
    }

    public function issue(Request $request, HrDocument $document): RedirectResponse
    {
        $this->authorizeManage($request, $document);
        if ($document->status !== 'approved') {
            throw ValidationException::withMessages([
                'status' => 'Hanya dokumen yang diluluskan boleh dikeluarkan.',
            ]);
        }
        $old = $document->only(['status', 'reference_number', 'issued_at', 'expiry_date']);
        DB::transaction(function () use ($request, $document) {
            $snapshot = $document->template_snapshot ?? [];
            $reference = $this->references->next(
                (string) ($snapshot['sequence_key'] ?? 'DEFAULT'),
                (int) $request->user()->getAuthIdentifier(),
            );
            $expiry = $document->expiry_date;
            if (! $expiry && ! empty($snapshot['default_validity_months'])) {
                $expiry = ($document->effective_date ?? today())
                    ->copy()
                    ->addMonths((int) $snapshot['default_validity_months']);
            }
            $document->update([
                'reference_number' => $reference,
                'status' => 'issued',
                'issued_by' => $request->user()->getAuthIdentifier(),
                'issued_at' => now(),
                'effective_date' => $document->effective_date ?? today(),
                'expiry_date' => $expiry,
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
            if ($document->employee_user_id) {
                HrDocumentNotification::query()->create([
                    'user_id' => $document->employee_user_id,
                    'hr_document_id' => $document->getKey(),
                    'type' => 'document_issued',
                    'title' => 'Dokumen HR baharu',
                    'message' => "{$document->reference_number} telah dikeluarkan untuk perhatian anda.",
                ]);
            }
        });
        AuditLogger::record(
            $request,
            'hr_document.issued',
            'hr_documents',
            $document->getKey(),
            oldValues: $old,
            newValues: $document->only([
                'status', 'reference_number', 'issued_at', 'effective_date',
                'expiry_date', 'acknowledgement_required',
            ]),
        );

        return $this->success('Dokumen telah dikeluarkan dan pekerja dimaklumkan.');
    }

    public function void(Request $request, HrDocument $document): RedirectResponse
    {
        $this->authorizeManage($request, $document);
        if (! in_array($document->status, ['issued', 'acknowledged'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Hanya dokumen yang telah dikeluarkan boleh dibatalkan.',
            ]);
        }
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:5000'],
        ]);
        $oldStatus = $document->status;
        $document->update([
            'status' => 'voided',
            'voided_by' => $request->user()->getAuthIdentifier(),
            'voided_at' => now(),
            'void_reason' => $validated['reason'],
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        if ($document->employee_user_id) {
            HrDocumentNotification::query()->create([
                'user_id' => $document->employee_user_id,
                'hr_document_id' => $document->getKey(),
                'type' => 'document_voided',
                'title' => 'Dokumen HR dibatalkan',
                'message' => "Dokumen {$document->reference_number} telah dibatalkan. Hubungi HR untuk maklumat lanjut.",
            ]);
        }
        AuditLogger::record(
            $request,
            'hr_document.voided',
            'hr_documents',
            $document->getKey(),
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'voided', 'reason' => $validated['reason']],
        );

        return $this->success('Dokumen telah dibatalkan dan dikekalkan dalam Audit Trail.');
    }

    public function renew(Request $request, HrDocument $document): RedirectResponse
    {
        $this->authorizeManage($request, $document);
        if (! in_array($document->status, ['issued', 'acknowledged'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Hanya dokumen yang telah dikeluarkan boleh diperbaharui.',
            ]);
        }
        $newDocument = $document->replicate([
            'reference_number', 'status', 'submitted_by', 'submitted_at',
            'approved_by', 'approved_at', 'approval_notes', 'rejected_by',
            'rejected_at', 'rejection_reason', 'issued_by', 'issued_at',
            'acknowledged_by', 'acknowledged_at', 'acknowledgement_ip',
            'voided_by', 'voided_at', 'void_reason', 'created_at', 'updated_at',
        ]);
        $newDocument->forceFill([
            'status' => 'draft',
            'reference_number' => null,
            'effective_date' => null,
            'expiry_date' => null,
            'supersedes_document_id' => $document->getKey(),
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ])->save();
        AuditLogger::record(
            $request,
            'hr_document.renewal_created',
            'hr_documents',
            $newDocument->getKey(),
            newValues: [
                'supersedes_document_id' => $document->getKey(),
                'employee_id' => $newDocument->employee_id,
                'status' => 'draft',
            ],
        );

        return $this->success('Draf pembaharuan dokumen telah dicipta.');
    }

    public function uploadAttachment(
        Request $request,
        HrDocument $document,
    ): RedirectResponse {
        $this->authorizeManage($request, $document);
        if ($document->status === 'voided') {
            throw ValidationException::withMessages([
                'attachment' => 'Lampiran tidak boleh ditambah pada dokumen yang dibatalkan.',
            ]);
        }
        $validated = $request->validate([
            'attachment_type' => [
                'required', Rule::in(['supporting', 'final_copy', 'signed_copy']),
            ],
            'attachment' => [
                'required', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240',
            ],
            'visible_to_employee' => ['required', 'boolean'],
        ]);
        if (
            $validated['attachment_type'] === 'supporting'
            && $validated['visible_to_employee']
        ) {
            throw ValidationException::withMessages([
                'visible_to_employee' => 'Lampiran sokongan dalaman tidak boleh dipaparkan kepada pekerja.',
            ]);
        }
        $attachment = $this->storeAttachment(
            $document,
            $request->file('attachment'),
            $validated['attachment_type'],
            (bool) $validated['visible_to_employee'],
            (int) $request->user()->getAuthIdentifier(),
        );
        AuditLogger::record(
            $request,
            'hr_document.attachment_uploaded',
            'hr_document_attachments',
            $attachment->getKey(),
            newValues: $attachment->only([
                'hr_document_id', 'attachment_type', 'original_name',
                'size', 'visible_to_employee',
            ]),
        );

        return $this->success('Lampiran dokumen telah dimuat naik.');
    }

    public function deleteAttachment(
        Request $request,
        HrDocument $document,
        HrDocumentAttachment $attachment,
    ): RedirectResponse {
        $this->authorizeManage($request, $document);
        abort_unless($attachment->hr_document_id === $document->getKey(), 404);
        if (! in_array($document->status, ['draft', 'rejected'], true)) {
            throw ValidationException::withMessages([
                'attachment' => 'Lampiran hanya boleh dibuang semasa dokumen masih draf.',
            ]);
        }
        $old = $attachment->only([
            'hr_document_id', 'attachment_type', 'original_name',
            'size', 'visible_to_employee',
        ]);
        Storage::disk($attachment->disk)->delete($attachment->path);
        $id = $attachment->getKey();
        $attachment->delete();
        AuditLogger::record(
            $request,
            'hr_document.attachment_deleted',
            'hr_document_attachments',
            $id,
            oldValues: $old,
        );

        return $this->success('Lampiran dokumen telah dibuang.');
    }

    public function downloadPdf(Request $request, HrDocument $document): HttpResponse
    {
        $this->authorizeView($request, $document);
        if (in_array($document->status, ['draft', 'rejected'], true)
            && ! $request->user()->hasPermission('documents.manage')) {
            abort(403);
        }
        $name = Str::slug($document->reference_number ?? "draf-{$document->id}")
            .'.pdf';

        return response($this->renderer->pdf($document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function downloadAttachment(
        Request $request,
        HrDocument $document,
        HrDocumentAttachment $attachment,
    ): HttpResponse {
        $this->authorizeView($request, $document);
        abort_unless($attachment->hr_document_id === $document->getKey(), 404);

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
            ['Cache-Control' => 'private, no-store'],
        );
    }

    public function export(Request $request): StreamedResponse
    {
        $documents = $this->visibleQuery($request->user())
            ->latest()
            ->get();
        AuditLogger::record(
            $request,
            'hr_document.report_exported',
            'hr_documents',
            'export-'.now()->format('YmdHis'),
            newValues: ['records' => $documents->count()],
        );

        return response()->streamDownload(function () use ($documents) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'Rujukan', 'Pekerja', 'No. Pekerja', 'Jabatan', 'Jawatan',
                'Kategori', 'Subjek', 'Status', 'Tarikh Keluar',
                'Tarikh Kuat Kuasa', 'Tarikh Tamat', 'Perakuan',
            ]);
            foreach ($documents as $document) {
                fputcsv($handle, [
                    $this->csvCell($document->reference_number),
                    $this->csvCell($document->employee_name),
                    $this->csvCell($document->employee_number),
                    $this->csvCell($document->department_name),
                    $this->csvCell($document->position_name),
                    $this->csvCell($document->category),
                    $this->csvCell($this->renderer->renderText($document->subject, $document)),
                    $this->csvCell($document->displayStatus()),
                    $document->issued_at?->format('Y-m-d'),
                    $document->effective_date?->toDateString(),
                    $document->expiry_date?->toDateString(),
                    $document->acknowledged_at?->format('Y-m-d H:i:s'),
                ]);
            }
            fclose($handle);
        }, 'laporan-dokumen-hr-'.now()->format('Ymd').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function visibleQuery(User $user): Builder
    {
        $query = HrDocument::query();
        if ($user->hasPermission('documents.manage')) {
            return $query;
        }

        return $query
            ->where('status', 'pending_approval')
            ->where(fn (Builder $query) => $query
                ->where('approver_user_id', $user->getAuthIdentifier())
                ->orWhereNull('approver_user_id'));
    }

    private function authorizeManage(Request $request, HrDocument $document): void
    {
        abort_unless($request->user()->hasPermission('documents.manage'), 403);
    }

    private function authorizeApproval(Request $request, HrDocument $document): void
    {
        $user = $request->user();
        abort_unless($user->hasPermission('documents.approve'), 403);

        if ($document->approver_user_id) {
            abort_unless(
                $document->approver_user_id === $user->getAuthIdentifier(),
                403,
            );
        }
    }

    private function authorizeView(Request $request, HrDocument $document): void
    {
        $user = $request->user();
        if ($user->hasPermission('documents.manage')) {
            return;
        }

        abort_unless(
            $user->hasPermission('documents.approve')
                && (
                    $document->approver_user_id === $user->getAuthIdentifier()
                    || $document->approver_user_id === null
                ),
            403,
        );
    }

    private function assertApprover(?int $approverId): void
    {
        if (! $approverId) {
            return;
        }
        $approver = User::query()->findOrFail($approverId);
        if (! $approver->hasPermission('documents.approve')) {
            throw ValidationException::withMessages([
                'approver_user_id' => 'Pengguna ini tidak mempunyai permission kelulusan dokumen.',
            ]);
        }
    }

    private function notifyApprovers(HrDocument $document): void
    {
        $approvers = $document->approver_user_id
            ? User::query()->whereKey($document->approver_user_id)->get()
            : User::query()
                ->with('roleAssignments')
                ->get()
                ->filter(fn (User $user) => $user->hasPermission('documents.approve'));

        foreach ($approvers as $approver) {
            HrDocumentNotification::query()->create([
                'user_id' => $approver->getKey(),
                'hr_document_id' => $document->getKey(),
                'type' => 'approval_required',
                'title' => 'Dokumen HR menunggu kelulusan',
                'message' => "Dokumen {$document->template_name} untuk {$document->employee_name} menunggu kelulusan.",
            ]);
        }
    }

    private function notifyCreator(
        HrDocument $document,
        string $type,
        string $title,
        string $message,
    ): void {
        if (! $document->created_by) {
            return;
        }
        HrDocumentNotification::query()->create([
            'user_id' => $document->created_by,
            'hr_document_id' => $document->getKey(),
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);
    }

    private function storeAttachment(
        HrDocument $document,
        UploadedFile $file,
        string $type,
        bool $visible,
        int $userId,
    ): HrDocumentAttachment {
        $disk = 'local';
        $path = $file->storeAs(
            "hr-documents/{$document->getKey()}",
            Str::uuid().'.'.$file->getClientOriginalExtension(),
            $disk,
        );

        return $document->attachments()->create([
            'attachment_type' => $type,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'visible_to_employee' => $visible,
            'uploaded_by' => $userId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(HrDocument $document): array
    {
        return [
            ...$document->toArray(),
            'display_status' => $document->displayStatus(),
            'rendered_subject' => $this->renderer->renderText($document->subject, $document),
            'rendered_body' => $this->renderer->renderText($document->body, $document),
            'days_to_expiry' => $document->expiry_date
                ? today()->diffInDays($document->expiry_date, false)
                : null,
        ];
    }

    private function success(string $message): RedirectResponse
    {
        return back()->with('toast', ['type' => 'success', 'message' => $message]);
    }

    private function csvCell(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $value) ? "'{$value}" : $value;
    }
}
