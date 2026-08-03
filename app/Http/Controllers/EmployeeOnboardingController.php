<?php

namespace App\Http\Controllers;

use App\Models\OnboardingCase;
use App\Models\OnboardingTask;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeOnboardingController extends Controller
{
    public function index(Request $request): Response
    {
        $case = OnboardingCase::query()
            ->with([
                'candidate.requisition:id,code,title',
                'template:id,name',
                'manager:id,name,email',
                'buddy:id,name,email',
                'tasks.assignee:id,name,email',
            ])
            ->where('employee_user_id', $request->user()->getAuthIdentifier())
            ->latest('start_date')
            ->first();
        $tasks = $case?->tasks ?? collect();
        $done = $tasks->whereIn('status', ['completed', 'waived'])->count();

        return Inertia::render('EmployeeSelfService/Onboarding', [
            'onboarding' => $case
                ? [
                    'id' => $case->getKey(),
                    'candidate_name' => $case->candidate?->name,
                    'position' => $case->candidate?->requisition?->title,
                    'template' => $case->template?->name,
                    'manager' => $case->manager?->name,
                    'buddy' => $case->buddy?->name,
                    'start_date' => $case->start_date?->toDateString(),
                    'status' => $case->status,
                    'progress' => $tasks->count() > 0
                        ? (int) round(($done / $tasks->count()) * 100)
                        : 0,
                    'tasks' => $tasks->map(fn (OnboardingTask $task) => [
                        'id' => $task->getKey(),
                        'title' => $task->title,
                        'description' => $task->description,
                        'category' => $task->category,
                        'assignee_role' => $task->assignee_role,
                        'assignee_user_id' => $task->assignee_user_id,
                        'assignee' => $task->assignee?->name,
                        'due_date' => $task->due_date?->toDateString(),
                        'is_required' => $task->is_required,
                        'status' => $task->status,
                        'completion_notes' => $task->completion_notes,
                        'can_update' => $task->assignee_role === 'employee'
                            && (int) $task->assignee_user_id
                                === (int) $request->user()->getAuthIdentifier(),
                    ]),
                ]
                : null,
        ]);
    }

    public function updateTask(
        Request $request,
        OnboardingTask $task,
    ): RedirectResponse {
        $case = $task->onboardingCase;
        abort_unless(
            (int) $case->employee_user_id === (int) $request->user()->getAuthIdentifier()
            && $task->assignee_role === 'employee'
            && (int) $task->assignee_user_id === (int) $request->user()->getAuthIdentifier(),
            403,
        );
        $validated = $request->validate([
            'status' => ['required', Rule::in(['in_progress', 'completed'])],
            'completion_notes' => [
                Rule::requiredIf($request->input('status') === 'completed'),
                'nullable',
                'string',
                'max:3000',
            ],
        ]);
        $old = $task->status;
        $task->update([
            ...$validated,
            'completed_by' => $validated['status'] === 'completed'
                ? $request->user()->getAuthIdentifier()
                : null,
            'completed_at' => $validated['status'] === 'completed' ? now() : null,
        ]);
        AuditLogger::record(
            $request,
            'onboarding.employee_task_updated',
            'onboarding_tasks',
            $task->getKey(),
            oldValues: ['status' => $old],
            newValues: [
                'status' => $task->status,
                'completion_notes' => $task->completion_notes,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Tugasan onboarding anda telah dikemas kini.',
        ]);
    }
}
