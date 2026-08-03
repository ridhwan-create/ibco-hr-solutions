<?php

namespace App\Http\Controllers;

use App\Models\DocumentSequence;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\DocumentReferenceGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DocumentSettingsController extends Controller
{
    public function __construct(
        private readonly DocumentReferenceGenerator $references,
    ) {}

    public function index(): Response
    {
        return Inertia::render('DocumentSettings/Index', [
            'templates' => DocumentTemplate::query()
                ->with('approver:id,name,email')
                ->withCount('documents')
                ->orderBy('category')
                ->orderBy('name')
                ->get(),
            'sequences' => DocumentSequence::query()
                ->orderBy('name')
                ->get()
                ->map(fn (DocumentSequence $sequence) => [
                    ...$sequence->toArray(),
                    'preview' => $this->references->preview($sequence),
                ]),
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
            'categories' => DocumentTemplate::CATEGORIES,
            'confidentialityLevels' => DocumentTemplate::CONFIDENTIALITY_LEVELS,
            'systemVariables' => [
                'employee_name', 'employee_number', 'employee_email',
                'department_name', 'position_name', 'reference_number',
                'issue_date', 'effective_date', 'expiry_date',
                'signatory_name', 'signatory_position', 'company_name', 'today',
            ],
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $validated = $this->validateTemplate($request);
        $template = DocumentTemplate::query()->create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'sequence_key' => strtoupper($validated['sequence_key']),
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'document_template.created',
            'document_templates',
            $template->getKey(),
            newValues: $template->only([
                'code', 'name', 'category', 'sequence_key',
                'requires_approval', 'acknowledgement_required',
            ]),
        );

        return $this->success('Template surat HR telah ditambah.');
    }

    public function updateTemplate(
        Request $request,
        DocumentTemplate $template,
    ): RedirectResponse {
        $validated = $this->validateTemplate($request, $template);
        $old = $template->only([
            'code', 'name', 'category', 'subject_template', 'body_template',
            'sequence_key', 'requires_approval', 'approver_user_id',
            'acknowledgement_required', 'default_validity_months',
            'confidentiality', 'is_active',
        ]);
        $template->update([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'sequence_key' => strtoupper($validated['sequence_key']),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'document_template.updated',
            'document_templates',
            $template->getKey(),
            oldValues: $old,
            newValues: $template->only(array_keys($old)),
        );

        return $this->success('Template surat HR telah dikemas kini.');
    }

    public function toggleTemplate(
        Request $request,
        DocumentTemplate $template,
    ): RedirectResponse {
        if (
            ! $template->is_active
            && ! DocumentSequence::query()
                ->where('sequence_key', $template->sequence_key)
                ->where('is_active', true)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'sequence_key' => 'Aktifkan siri nombor rujukan template ini terlebih dahulu.',
            ]);
        }
        $old = $template->is_active;
        $template->update([
            'is_active' => ! $template->is_active,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            $template->is_active
                ? 'document_template.activated'
                : 'document_template.deactivated',
            'document_templates',
            $template->getKey(),
            oldValues: ['is_active' => $old],
            newValues: ['is_active' => $template->is_active],
        );

        return $this->success(
            $template->is_active
                ? 'Template telah diaktifkan.'
                : 'Template telah dinyahaktifkan.',
        );
    }

    public function saveSequence(Request $request): RedirectResponse
    {
        $request->merge([
            'sequence_key' => strtoupper((string) $request->input('sequence_key')),
        ]);
        $validated = $request->validate([
            'sequence_key' => [
                'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/',
            ],
            'name' => ['required', 'string', 'max:120'],
            'prefix' => ['required', 'string', 'max:50'],
            'format' => [
                'required', 'string', 'max:150',
                function (string $attribute, mixed $value, $fail) {
                    if (! preg_match('/\{\{SEQ(?::\d{1,2})?\}\}/', (string) $value)) {
                        $fail('Format mesti mengandungi token {{SEQ}} atau {{SEQ:05}}.');
                    }
                },
            ],
            'next_number' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'reset_annually' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);
        $key = strtoupper($validated['sequence_key']);
        $sequence = DocumentSequence::query()->firstOrNew(['sequence_key' => $key]);
        $old = $sequence->exists ? $sequence->toArray() : [];

        if (
            ! $validated['is_active']
            && DocumentTemplate::query()
                ->where('sequence_key', $key)
                ->where('is_active', true)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'is_active' => 'Siri masih digunakan oleh template aktif dan tidak boleh dinyahaktifkan.',
            ]);
        }

        if (
            $sequence->exists
            && (int) $validated['next_number'] < (int) $sequence->next_number
            && ! $request->user()->hasPermission('documents.approve')
        ) {
            throw ValidationException::withMessages([
                'next_number' => 'Hanya pelulus dokumen boleh mengundurkan nombor siri.',
            ]);
        }

        $sequence->fill([
            ...$validated,
            'sequence_key' => $key,
            'created_by' => $sequence->created_by ?? $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ])->save();
        AuditLogger::record(
            $request,
            $old === [] ? 'document_sequence.created' : 'document_sequence.updated',
            'document_sequences',
            $sequence->getKey(),
            oldValues: $old,
            newValues: $sequence->only([
                'sequence_key', 'prefix', 'format', 'next_number',
                'reset_annually', 'is_active',
            ]),
        );

        return $this->success('Siri nombor rujukan telah disimpan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplate(
        Request $request,
        ?DocumentTemplate $template = null,
    ): array {
        $request->merge([
            'code' => strtoupper((string) $request->input('code')),
            'sequence_key' => strtoupper((string) $request->input('sequence_key')),
        ]);
        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('document_templates', 'code')->ignore($template),
            ],
            'name' => ['required', 'string', 'max:180'],
            'category' => ['required', Rule::in(DocumentTemplate::CATEGORIES)],
            'subject_template' => ['required', 'string', 'max:500'],
            'body_template' => ['required', 'string', 'max:20000'],
            'available_variables' => ['nullable', 'array', 'max:50'],
            'available_variables.*' => [
                'string', 'max:60', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
            ],
            'sequence_key' => ['required', 'string', 'max:40', 'exists:document_sequences,sequence_key'],
            'requires_approval' => ['required', 'boolean'],
            'approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'acknowledgement_required' => ['required', 'boolean'],
            'default_validity_months' => ['nullable', 'integer', 'between:1,1200'],
            'confidentiality' => [
                'required', Rule::in(DocumentTemplate::CONFIDENTIALITY_LEVELS),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        if (isset($validated['approver_user_id'])) {
            $approver = User::query()->findOrFail($validated['approver_user_id']);
            if (! $approver->hasPermission('documents.approve')) {
                throw ValidationException::withMessages([
                    'approver_user_id' => 'Pengguna ini tidak mempunyai permission kelulusan dokumen.',
                ]);
            }
        }

        if (
            $validated['is_active']
            && ! DocumentSequence::query()
                ->where('sequence_key', strtoupper($validated['sequence_key']))
                ->where('is_active', true)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'sequence_key' => 'Template aktif mesti menggunakan siri nombor rujukan yang aktif.',
            ]);
        }

        return $validated;
    }

    private function success(string $message): RedirectResponse
    {
        return back()->with('toast', ['type' => 'success', 'message' => $message]);
    }
}
