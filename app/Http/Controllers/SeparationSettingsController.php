<?php

namespace App\Http\Controllers;

use App\Models\ClearanceTemplateItem;
use App\Models\SeparationTemplate;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SeparationSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('SeparationSettings/Index', [
            'templates' => SeparationTemplate::query()
                ->with([
                    'approver:id,name,email',
                    'items.assignee:id,name,email',
                ])
                ->withCount('cases')
                ->orderBy('name')
                ->get(),
            'approvers' => $this->usersWithPermission('separation.approve'),
            'assignees' => $this->usersWithPermission('separation.clearance'),
            'types' => SeparationTemplate::TYPES,
            'ownerTypes' => ClearanceTemplateItem::OWNER_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedTemplate($request);
        $template = SeparationTemplate::query()->create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'is_active' => true,
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'separation.template_created',
            'separation_templates',
            $template->getKey(),
            newValues: $template->only([
                'code', 'name', 'separation_type', 'minimum_notice_days',
                'employee_can_apply', 'exit_interview_required',
                'final_settlement_required', 'approver_user_id', 'is_active',
            ]),
        );

        return $this->success('Template pengakhiran telah ditambah.');
    }

    public function update(
        Request $request,
        SeparationTemplate $template,
    ): RedirectResponse {
        $validated = $this->validatedTemplate($request, $template);
        $old = $template->only([
            'code', 'name', 'description', 'separation_type',
            'minimum_notice_days', 'employee_can_apply',
            'exit_interview_required', 'final_settlement_required',
            'approver_user_id',
        ]);
        $template->update([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'separation.template_updated',
            'separation_templates',
            $template->getKey(),
            oldValues: $old,
            newValues: $template->only(array_keys($old)),
        );

        return $this->success('Template pengakhiran telah dikemas kini.');
    }

    public function toggle(
        Request $request,
        SeparationTemplate $template,
    ): RedirectResponse {
        $old = $template->is_active;
        $template->update([
            'is_active' => ! $old,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'separation.template_status_changed',
            'separation_templates',
            $template->getKey(),
            oldValues: ['is_active' => $old],
            newValues: ['is_active' => $template->is_active],
        );

        return $this->success(
            $template->is_active ? 'Template diaktifkan.' : 'Template dinyahaktifkan.',
        );
    }

    public function storeItem(
        Request $request,
        SeparationTemplate $template,
    ): RedirectResponse {
        $validated = $this->validatedItem($request);
        $item = $template->items()->create($validated);
        AuditLogger::record(
            $request,
            'separation.clearance_item_created',
            'clearance_template_items',
            $item->getKey(),
            newValues: $item->only([
                'separation_template_id', 'title', 'owner_type',
                'assignee_user_id', 'due_offset_days', 'is_mandatory',
                'employee_action_required', 'evidence_required', 'sort_order',
            ]),
        );

        return $this->success('Item checklist telah ditambah.');
    }

    public function updateItem(
        Request $request,
        SeparationTemplate $template,
        ClearanceTemplateItem $item,
    ): RedirectResponse {
        abort_unless($item->separation_template_id === $template->getKey(), 404);
        $validated = $this->validatedItem($request);
        $old = $item->only(array_keys($validated));
        $item->update($validated);
        AuditLogger::record(
            $request,
            'separation.clearance_item_updated',
            'clearance_template_items',
            $item->getKey(),
            oldValues: $old,
            newValues: $item->only(array_keys($validated)),
        );

        return $this->success('Item checklist telah dikemas kini.');
    }

    public function destroyItem(
        Request $request,
        SeparationTemplate $template,
        ClearanceTemplateItem $item,
    ): RedirectResponse {
        abort_unless($item->separation_template_id === $template->getKey(), 404);
        $old = $item->only([
            'separation_template_id', 'title', 'owner_type', 'is_mandatory',
        ]);
        $id = $item->getKey();
        $item->delete();
        AuditLogger::record(
            $request,
            'separation.clearance_item_deleted',
            'clearance_template_items',
            $id,
            oldValues: $old,
        );

        return $this->success('Item checklist telah dibuang daripada template.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedTemplate(
        Request $request,
        ?SeparationTemplate $template = null,
    ): array {
        $validated = $request->validate([
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('separation_templates', 'code')->ignore($template),
            ],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'separation_type' => ['nullable', Rule::in(SeparationTemplate::TYPES)],
            'minimum_notice_days' => ['required', 'integer', 'min:0', 'max:365'],
            'employee_can_apply' => ['required', 'boolean'],
            'exit_interview_required' => ['required', 'boolean'],
            'final_settlement_required' => ['required', 'boolean'],
            'approver_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        if ($validated['approver_user_id']) {
            $approver = User::query()->findOrFail($validated['approver_user_id']);
            abort_unless($approver->hasPermission('separation.approve'), 422);
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedItem(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'owner_type' => ['required', Rule::in(ClearanceTemplateItem::OWNER_TYPES)],
            'assignee_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_offset_days' => ['required', 'integer', 'min:-90', 'max:180'],
            'is_mandatory' => ['required', 'boolean'],
            'employee_action_required' => ['required', 'boolean'],
            'evidence_required' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65000'],
        ]);
        if ($validated['assignee_user_id']) {
            $user = User::query()->findOrFail($validated['assignee_user_id']);
            abort_unless($user->hasPermission('separation.clearance'), 422);
        }

        return $validated;
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

    private function success(string $message): RedirectResponse
    {
        return back()->with('toast', ['type' => 'success', 'message' => $message]);
    }
}
