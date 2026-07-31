<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\PerformanceCycle;
use App\Models\PerformanceNotification;
use App\Models\PerformanceSupervisorAssignment;
use App\Models\PerformanceTemplate;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceSettingsController extends Controller
{
    public function index(): Response
    {
        $departments = DB::connection('ibco')
            ->table('xdepartment')
            ->where('rcd_enable', 1)
            ->orderBy('description')
            ->get(['id', 'description as name']);
        $departmentMap = $departments->pluck('name', 'id');

        return Inertia::render('PerformanceSettings/Index', [
            'cycles' => PerformanceCycle::query()
                ->withCount('reviews')
                ->latest('period_start')
                ->get()
                ->map(fn (PerformanceCycle $cycle) => [
                    'id' => $cycle->getKey(),
                    'code' => $cycle->code,
                    'name' => $cycle->name,
                    'cycle_type' => $cycle->cycle_type,
                    'period_start' => $cycle->period_start?->toDateString(),
                    'period_end' => $cycle->period_end?->toDateString(),
                    'self_assessment_due_at' => $cycle->self_assessment_due_at?->toDateString(),
                    'supervisor_due_at' => $cycle->supervisor_due_at?->toDateString(),
                    'moderation_due_at' => $cycle->moderation_due_at?->toDateString(),
                    'status' => $cycle->status,
                    'rating_scale' => $cycle->rating_scale,
                    'reviews_count' => $cycle->reviews_count,
                ]),
            'templates' => PerformanceTemplate::query()
                ->with('items')
                ->orderBy('name')
                ->get()
                ->map(fn (PerformanceTemplate $template) => [
                    'id' => $template->getKey(),
                    'code' => $template->code,
                    'name' => $template->name,
                    'department_id' => $template->department_id,
                    'department' => $departmentMap[$template->department_id] ?? null,
                    'position_name' => $template->position_name,
                    'description' => $template->description,
                    'is_active' => $template->is_active,
                    'total_weight' => round((float) $template->items->sum('weight'), 2),
                    'items' => $template->items->map(fn ($item) => [
                        'id' => $item->getKey(),
                        'title' => $item->title,
                        'description' => $item->description,
                        'measure_type' => $item->measure_type,
                        'target_value' => $item->target_value === null
                            ? null
                            : (float) $item->target_value,
                        'unit' => $item->unit,
                        'weight' => (float) $item->weight,
                        'scoring_guide' => $item->scoring_guide,
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
            'supervisors' => User::query()
                ->with('roleAssignments')
                ->orderBy('name')
                ->get()
                ->filter(fn (User $user) => $user->hasRole(UserRole::Supervisor)
                    || $user->hasRole(UserRole::SuperAdmin))
                ->values()
                ->map(fn (User $user) => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
            'assignments' => PerformanceSupervisorAssignment::query()
                ->with('supervisor:id,name,email')
                ->orderBy('department_id')
                ->get()
                ->map(fn (PerformanceSupervisorAssignment $assignment) => [
                    'id' => $assignment->getKey(),
                    'department_id' => $assignment->department_id,
                    'department' => $departmentMap[$assignment->department_id] ?? 'Jabatan #'.$assignment->department_id,
                    'supervisor_user_id' => $assignment->supervisor_user_id,
                    'supervisor_name' => $assignment->supervisor?->name,
                    'supervisor_email' => $assignment->supervisor?->email,
                    'is_active' => $assignment->is_active,
                ]),
        ]);
    }

    public function storeCycle(Request $request): RedirectResponse
    {
        $validated = $this->validateCycle($request);
        $cycle = PerformanceCycle::query()->create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'status' => 'draft',
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'performance_cycle.created',
            'performance_cycles',
            $cycle->getKey(),
            newValues: $cycle->only(['code', 'name', 'cycle_type', 'period_start', 'period_end']),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Kitaran penilaian telah ditambah sebagai Draf.',
        ]);
    }

    public function updateCycle(
        Request $request,
        PerformanceCycle $cycle,
    ): RedirectResponse {
        if ($cycle->status === 'finalized') {
            throw ValidationException::withMessages([
                'cycle' => 'Kitaran yang dimuktamadkan tidak boleh diubah.',
            ]);
        }

        $old = $cycle->only([
            'code',
            'name',
            'cycle_type',
            'period_start',
            'period_end',
            'self_assessment_due_at',
            'supervisor_due_at',
            'moderation_due_at',
            'rating_scale',
        ]);
        $validated = $this->validateCycle($request, $cycle);
        $cycle->update([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'performance_cycle.updated',
            'performance_cycles',
            $cycle->getKey(),
            oldValues: $old,
            newValues: $cycle->only(array_keys($old)),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Kitaran penilaian telah dikemas kini.',
        ]);
    }

    public function changeCycleStatus(
        Request $request,
        PerformanceCycle $cycle,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_review', 'finalized'])],
        ]);
        $next = $validated['status'];
        $allowed = match ($cycle->status) {
            'draft' => ['open'],
            'open' => ['in_review'],
            'in_review' => ['finalized'],
            default => [],
        };

        if (! in_array($next, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => 'Perubahan status kitaran ini tidak dibenarkan.',
            ]);
        }

        if (
            $next === 'finalized'
            && $cycle->reviews()->where('status', '<>', 'finalized')->exists()
        ) {
            throw ValidationException::withMessages([
                'status' => 'Semua penilaian pekerja mesti dimuktamadkan terlebih dahulu.',
            ]);
        }

        $old = $cycle->status;
        $cycle->update([
            'status' => $next,
            'opened_at' => $next === 'open' ? now() : $cycle->opened_at,
            'finalized_at' => $next === 'finalized' ? now() : null,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        if ($next === 'open') {
            $cycle->reviews()
                ->where('status', 'goal_setting')
                ->update(['status' => 'self_assessment']);
            $cycle->reviews()
                ->whereNotNull('employee_user_id')
                ->get(['id', 'employee_user_id'])
                ->each(fn ($review) => PerformanceNotification::query()->create([
                    'user_id' => $review->employee_user_id,
                    'performance_review_id' => $review->getKey(),
                    'type' => 'cycle_opened',
                    'title' => 'Self-Assessment telah dibuka',
                    'message' => "Lengkapkan Self-Assessment {$cycle->name} sebelum "
                        .$cycle->self_assessment_due_at?->format('d/m/Y').'.',
                ]));
        }

        AuditLogger::record(
            $request,
            "performance_cycle.{$next}",
            'performance_cycles',
            $cycle->getKey(),
            oldValues: ['status' => $old],
            newValues: ['status' => $next],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => match ($next) {
                'open' => 'Kitaran dibuka untuk Self-Assessment.',
                'in_review' => 'Kitaran dipindahkan ke fasa semakan.',
                default => 'Kitaran penilaian telah dimuktamadkan.',
            },
        ]);
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $validated = $this->validateTemplate($request);
        $template = DB::transaction(function () use ($request, $validated) {
            $template = PerformanceTemplate::query()->create([
                'code' => strtoupper($validated['code']),
                'name' => $validated['name'],
                'department_id' => $validated['department_id'] ?? null,
                'position_name' => $validated['position_name'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'],
                'created_by' => $request->user()->getAuthIdentifier(),
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
            $this->replaceTemplateItems($template, $validated['items']);

            return $template;
        });

        AuditLogger::record(
            $request,
            'performance_template.created',
            'performance_templates',
            $template->getKey(),
            newValues: [
                'code' => $template->code,
                'name' => $template->name,
                'department_id' => $template->department_id,
                'position_name' => $template->position_name,
                'total_weight' => 100,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Template KPI dengan pemberat 100% telah ditambah.',
        ]);
    }

    public function updateTemplate(
        Request $request,
        PerformanceTemplate $template,
    ): RedirectResponse {
        $validated = $this->validateTemplate($request, $template);
        $old = $template->load('items')->toArray();
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
            $this->replaceTemplateItems($template, $validated['items']);
        });

        AuditLogger::record(
            $request,
            'performance_template.updated',
            'performance_templates',
            $template->getKey(),
            oldValues: ['code' => $old['code'], 'name' => $old['name']],
            newValues: [
                'code' => $template->code,
                'name' => $template->name,
                'department_id' => $template->department_id,
                'position_name' => $template->position_name,
                'total_weight' => 100,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Template KPI telah dikemas kini. Penilaian sedia ada kekal sebagai snapshot.',
        ]);
    }

    public function toggleTemplate(
        Request $request,
        PerformanceTemplate $template,
    ): RedirectResponse {
        $old = $template->is_active;
        $template->update([
            'is_active' => ! $old,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            $template->is_active
                ? 'performance_template.activated'
                : 'performance_template.deactivated',
            'performance_templates',
            $template->getKey(),
            oldValues: ['is_active' => $old],
            newValues: ['is_active' => $template->is_active],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $template->is_active
                ? 'Template KPI diaktifkan.'
                : 'Template KPI dinyahaktifkan.',
        ]);
    }

    public function saveAssignment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'integer'],
            'supervisor_user_id' => ['required', 'integer', 'exists:users,id'],
            'is_active' => ['required', 'boolean'],
        ]);
        $supervisor = User::query()->findOrFail($validated['supervisor_user_id']);

        if (
            ! $supervisor->hasRole(UserRole::Supervisor)
            && ! $supervisor->hasRole(UserRole::SuperAdmin)
        ) {
            throw ValidationException::withMessages([
                'supervisor_user_id' => 'Pengguna mesti mempunyai role Penyelia atau Super Admin.',
            ]);
        }

        $assignment = PerformanceSupervisorAssignment::query()->updateOrCreate(
            ['department_id' => $validated['department_id']],
            [
                'supervisor_user_id' => $supervisor->getKey(),
                'is_active' => $validated['is_active'],
                'updated_by' => $request->user()->getAuthIdentifier(),
            ],
        );

        AuditLogger::record(
            $request,
            'performance_supervisor.assigned',
            'performance_supervisor_assignments',
            $assignment->getKey(),
            newValues: [
                'department_id' => $assignment->department_id,
                'supervisor_user_id' => $assignment->supervisor_user_id,
                'is_active' => $assignment->is_active,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Penyelia penilaian jabatan telah ditetapkan.',
        ]);
    }

    public function destroyAssignment(
        Request $request,
        PerformanceSupervisorAssignment $assignment,
    ): RedirectResponse {
        $values = $assignment->only(['department_id', 'supervisor_user_id']);
        $id = $assignment->getKey();
        $assignment->delete();

        AuditLogger::record(
            $request,
            'performance_supervisor.removed',
            'performance_supervisor_assignments',
            $id,
            oldValues: $values,
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Penetapan penyelia penilaian telah dibuang.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCycle(
        Request $request,
        ?PerformanceCycle $cycle = null,
    ): array {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:32',
                Rule::unique('performance_cycles', 'code')->ignore($cycle),
            ],
            'name' => ['required', 'string', 'max:150'],
            'cycle_type' => ['required', Rule::in(PerformanceCycle::TYPES)],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'self_assessment_due_at' => ['required', 'date', 'after_or_equal:period_start', 'before_or_equal:period_end'],
            'supervisor_due_at' => ['required', 'date', 'after_or_equal:self_assessment_due_at', 'before_or_equal:period_end'],
            'moderation_due_at' => ['required', 'date', 'after_or_equal:supervisor_due_at'],
            'rating_scale' => ['required', 'array', 'min:3', 'max:10'],
            'rating_scale.*.label' => ['required', 'string', 'max:80'],
            'rating_scale.*.minimum' => ['required', 'numeric', 'min:1', 'max:5'],
        ]);
        $minimums = collect($validated['rating_scale'])
            ->pluck('minimum')
            ->map(fn ($value) => (float) $value);

        if ($minimums->unique()->count() !== $minimums->count()) {
            throw ValidationException::withMessages([
                'rating_scale' => 'Nilai minimum bagi setiap rating mesti unik.',
            ]);
        }

        $validated['rating_scale'] = collect($validated['rating_scale'])
            ->sortByDesc(fn (array $rating) => (float) $rating['minimum'])
            ->values()
            ->all();

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplate(
        Request $request,
        ?PerformanceTemplate $template = null,
    ): array {
        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:32',
                Rule::unique('performance_templates', 'code')->ignore($template),
            ],
            'name' => ['required', 'string', 'max:150'],
            'department_id' => ['nullable', 'integer'],
            'position_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.title' => ['required', 'string', 'max:200'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.measure_type' => ['required', Rule::in(['quantitative', 'qualitative'])],
            'items.*.target_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:50'],
            'items.*.weight' => ['required', 'numeric', 'gt:0', 'max:100'],
            'items.*.scoring_guide' => ['nullable', 'string', 'max:2000'],
        ]);
        $totalWeight = round((float) collect($validated['items'])->sum('weight'), 2);

        if (abs($totalWeight - 100) > .01) {
            throw ValidationException::withMessages([
                'items' => "Jumlah pemberat KPI mesti tepat 100%. Jumlah semasa: {$totalWeight}%.",
            ]);
        }

        return $validated;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function replaceTemplateItems(
        PerformanceTemplate $template,
        array $items,
    ): void {
        $template->items()->delete();

        foreach ($items as $index => $item) {
            $template->items()->create([
                ...$item,
                'target_value' => $item['target_value'] ?? null,
                'unit' => $item['unit'] ?? null,
                'description' => $item['description'] ?? null,
                'scoring_guide' => $item['scoring_guide'] ?? null,
                'sort_order' => $index + 1,
            ]);
        }
    }
}
