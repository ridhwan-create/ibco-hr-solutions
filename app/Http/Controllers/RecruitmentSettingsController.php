<?php

namespace App\Http\Controllers;

use App\Models\OnboardingTemplate;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RecruitmentSettingsController extends Controller
{
    public function index(): Response
    {
        $departments = DB::connection('ibco')
            ->table('xdepartment')
            ->where('rcd_enable', 1)
            ->orderBy('description')
            ->get(['id', 'description as name']);
        $departmentMap = $departments->pluck('name', 'id');

        return Inertia::render('RecruitmentSettings/Index', [
            'templates' => OnboardingTemplate::query()
                ->with('tasks')
                ->orderBy('name')
                ->get()
                ->map(fn (OnboardingTemplate $template) => [
                    'id' => $template->getKey(),
                    'code' => $template->code,
                    'name' => $template->name,
                    'department_id' => $template->department_id,
                    'department' => $departmentMap[$template->department_id] ?? null,
                    'position_name' => $template->position_name,
                    'description' => $template->description,
                    'is_active' => $template->is_active,
                    'tasks' => $template->tasks->map(fn ($task) => [
                        'id' => $task->getKey(),
                        'title' => $task->title,
                        'description' => $task->description,
                        'category' => $task->category,
                        'assignee_role' => $task->assignee_role,
                        'due_offset_days' => $task->due_offset_days,
                        'is_required' => $task->is_required,
                    ]),
                ]),
            'departments' => $departments,
            'positionNames' => DB::connection('ibco')
                ->table('maklumatjawatan')
                ->where('rcd_enable', 1)
                ->whereNotNull('jawatan')
                ->where('jawatan', '<>', '')
                ->distinct()
                ->orderBy('jawatan')
                ->pluck('jawatan'),
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $validated = $this->validateTemplate($request);
        $template = DB::transaction(function () use ($request, $validated) {
            $template = OnboardingTemplate::query()->create([
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'],
                'department_id' => $validated['department_id'] ?? null,
                'position_name' => $validated['position_name'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'],
                'created_by' => $request->user()->getAuthIdentifier(),
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
            $this->replaceTasks($template, $validated['tasks']);

            return $template;
        });
        AuditLogger::record(
            $request,
            'recruitment.onboarding_template_created',
            'onboarding_templates',
            $template->getKey(),
            newValues: [
                'code' => $template->code,
                'name' => $template->name,
                'department_id' => $template->department_id,
                'position_name' => $template->position_name,
                'tasks_count' => count($validated['tasks']),
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Template onboarding telah ditambah.',
        ]);
    }

    public function updateTemplate(
        Request $request,
        OnboardingTemplate $template,
    ): RedirectResponse {
        $validated = $this->validateTemplate($request, $template);
        $old = $template->load('tasks')->toArray();
        DB::transaction(function () use ($request, $template, $validated) {
            $template->update([
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'],
                'department_id' => $validated['department_id'] ?? null,
                'position_name' => $validated['position_name'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'],
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
            $this->replaceTasks($template, $validated['tasks']);
        });
        AuditLogger::record(
            $request,
            'recruitment.onboarding_template_updated',
            'onboarding_templates',
            $template->getKey(),
            oldValues: [
                'code' => $old['code'],
                'name' => $old['name'],
                'tasks_count' => count($old['tasks']),
            ],
            newValues: [
                'code' => $template->code,
                'name' => $template->name,
                'tasks_count' => count($validated['tasks']),
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Template onboarding telah dikemas kini. Kes sedia ada kekal sebagai snapshot.',
        ]);
    }

    public function toggleTemplate(
        Request $request,
        OnboardingTemplate $template,
    ): RedirectResponse {
        $old = $template->is_active;
        $template->update([
            'is_active' => ! $old,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);
        AuditLogger::record(
            $request,
            $template->is_active
                ? 'recruitment.onboarding_template_activated'
                : 'recruitment.onboarding_template_deactivated',
            'onboarding_templates',
            $template->getKey(),
            oldValues: ['is_active' => $old],
            newValues: ['is_active' => $template->is_active],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $template->is_active
                ? 'Template onboarding diaktifkan.'
                : 'Template onboarding dinyahaktifkan.',
        ]);
    }

    private function validateTemplate(
        Request $request,
        ?OnboardingTemplate $template = null,
    ): array {
        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('onboarding_templates', 'code')->ignore($template),
            ],
            'name' => ['required', 'string', 'max:150'],
            'department_id' => ['nullable', 'integer'],
            'position_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['required', 'boolean'],
            'tasks' => ['required', 'array', 'min:1', 'max:100'],
            'tasks.*.title' => ['required', 'string', 'max:180'],
            'tasks.*.description' => ['nullable', 'string', 'max:3000'],
            'tasks.*.category' => ['required', Rule::in([
                'hr',
                'supervisor',
                'it',
                'finance',
                'employee',
                'facilities',
                'other',
            ])],
            'tasks.*.assignee_role' => ['required', Rule::in([
                'hr',
                'supervisor',
                'employee',
                'custom',
            ])],
            'tasks.*.due_offset_days' => ['required', 'integer', 'min:-30', 'max:365'],
            'tasks.*.is_required' => ['required', 'boolean'],
        ]);
    }

    private function replaceTasks(
        OnboardingTemplate $template,
        array $tasks,
    ): void {
        $template->tasks()->delete();
        $template->tasks()->createMany(
            collect($tasks)
                ->values()
                ->map(fn (array $task, int $index) => [
                    ...$task,
                    'sort_order' => $index + 1,
                ])
                ->all(),
        );
    }
}
