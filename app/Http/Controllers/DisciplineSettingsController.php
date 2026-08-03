<?php

namespace App\Http\Controllers;

use App\Models\ComplaintCategory;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DisciplineSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('DisciplineSettings/Index', [
            'categories' => ComplaintCategory::query()
                ->withCount('cases')
                ->orderBy('name')
                ->get(),
            'severityOptions' => ComplaintCategory::SEVERITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $category = ComplaintCategory::query()->create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'is_active' => true,
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'discipline.category_created',
            'complaint_categories',
            $category->getKey(),
            newValues: $category->only([
                'code', 'name', 'default_severity', 'sla_days', 'appeal_days',
                'requires_show_cause', 'allow_protected_identity', 'is_active',
            ]),
        );

        return $this->success('Kategori aduan telah ditambah.');
    }

    public function update(
        Request $request,
        ComplaintCategory $category,
    ): RedirectResponse {
        $validated = $this->validated($request, $category);
        $old = $category->only([
            'code', 'name', 'description', 'default_severity', 'sla_days',
            'appeal_days', 'requires_show_cause', 'allow_protected_identity',
        ]);
        $category->update([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'discipline.category_updated',
            'complaint_categories',
            $category->getKey(),
            oldValues: $old,
            newValues: $category->only(array_keys($old)),
        );

        return $this->success('Kategori aduan telah dikemas kini.');
    }

    public function toggle(
        Request $request,
        ComplaintCategory $category,
    ): RedirectResponse {
        $old = $category->is_active;
        $category->update([
            'is_active' => ! $old,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            'discipline.category_status_changed',
            'complaint_categories',
            $category->getKey(),
            oldValues: ['is_active' => $old],
            newValues: ['is_active' => $category->is_active],
        );

        return $this->success(
            $category->is_active ? 'Kategori diaktifkan.' : 'Kategori dinyahaktifkan.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(
        Request $request,
        ?ComplaintCategory $category = null,
    ): array {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('complaint_categories', 'code')->ignore($category),
            ],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'default_severity' => ['required', Rule::in(ComplaintCategory::SEVERITIES)],
            'sla_days' => ['required', 'integer', 'min:1', 'max:365'],
            'appeal_days' => ['required', 'integer', 'min:1', 'max:90'],
            'requires_show_cause' => ['required', 'boolean'],
            'allow_protected_identity' => ['required', 'boolean'],
        ]);
    }

    private function success(string $message): RedirectResponse
    {
        return back()->with('toast', ['type' => 'success', 'message' => $message]);
    }
}
