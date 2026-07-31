<?php

namespace App\Http\Controllers;

use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeUserLink;
use App\Models\GeoAttendanceRecord;
use App\Models\OvertimeApprovalAssignment;
use App\Models\RosterEntry;
use App\Models\RosterNotification;
use App\Models\RosterPeriod;
use App\Models\ShiftSwapRequest;
use App\Models\ShiftTemplate;
use App\Support\AuditLogger;
use App\Support\RosterGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RosterController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->validatedFilters($request);
        $periodStart = CarbonImmutable::createFromFormat(
            'Y-m',
            $filters['month'],
        )->startOfMonth();
        $period = RosterPeriod::query()
            ->whereDate('period_start', $periodStart)
            ->first();
        $directory = collect($this->employeeDirectory($request));
        $matchingIds = $directory
            ->when(
                $filters['search'] !== '',
                fn (Collection $employees) => $employees->filter(
                    fn (array $employee) => str_contains(
                        strtolower(
                            ($employee['employee_number'] ?? '').' '
                            .$employee['name'],
                        ),
                        strtolower($filters['search']),
                    ),
                ),
            )
            ->pluck('employee_id')
            ->all();
        $visibleIds = $directory->pluck('employee_id')->all();
        $query = $period
            ? RosterEntry::query()
                ->where('roster_period_id', $period->getKey())
                ->when(
                    $visibleIds === [],
                    fn (Builder $query) => $query->whereRaw('1 = 0'),
                    fn (Builder $query) => $query->whereIn(
                        'employee_id',
                        $visibleIds,
                    ),
                )
                ->when(
                    $filters['department_id'] !== '',
                    fn (Builder $query) => $query->where(
                        'department_id',
                        (int) $filters['department_id'],
                    ),
                )
                ->when(
                    $filters['office_id'] !== '',
                    fn (Builder $query) => $query->where(
                        'office_location_id',
                        (int) $filters['office_id'],
                    ),
                )
                ->when(
                    $filters['day_type'] !== '',
                    fn (Builder $query) => $query->where(
                        'day_type',
                        $filters['day_type'],
                    ),
                )
                ->when(
                    $filters['search'] !== '',
                    fn (Builder $query) => $matchingIds === []
                        ? $query->whereRaw('1 = 0')
                        : $query->whereIn('employee_id', $matchingIds),
                )
            : null;
        $entries = $query
            ? (clone $query)
                ->with([
                    'shiftTemplate:id,code,name,grace_minutes,early_departure_grace_minutes',
                    'officeLocation:id,name',
                ])
                ->orderBy('work_date')
                ->orderBy('employee_id')
                ->paginate(60)
                ->withQueryString()
            : null;
        $entryCollection = $entries
            ? collect($entries->items())
            : collect();
        $employeeMap = $directory->keyBy(
            fn (array $employee) => (string) $employee['employee_id'],
        );
        $attendanceMap = $this->attendanceMap($entryCollection);
        $leaveMap = $this->leaveMap($entryCollection);

        $entries?->through(
            fn (RosterEntry $entry) => $this->entryPayload(
                $entry,
                $employeeMap->get((string) $entry->employee_id),
                $attendanceMap,
                $leaveMap,
            ),
        );
        $statistics = $query
            ? $this->statistics((clone $query)->get())
            : $this->emptyStatistics();
        $pendingSwaps = ShiftSwapRequest::query()
            ->with([
                'requesterEntry.shiftTemplate:id,name',
                'targetEntry.shiftTemplate:id,name',
                'requester:id,name',
                'target:id,name',
            ])
            ->where('status', 'pending')
            ->when(
                ! $request->user()->hasPermission('roster.manage'),
                fn (Builder $query) => $query->whereIn(
                    'department_id',
                    $this->supervisedDepartmentIds($request),
                ),
            )
            ->latest()
            ->get()
            ->map(fn (ShiftSwapRequest $swap) => $this->swapPayload($swap));

        return Inertia::render('Rosters/Index', [
            'period' => $period ? $this->periodPayload($period) : null,
            'entries' => $entries,
            'filters' => $filters,
            'statistics' => $statistics,
            'pendingSwaps' => $pendingSwaps,
            'shiftTemplates' => ShiftTemplate::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get()
                ->map(fn (ShiftTemplate $template) => [
                    'id' => $template->getKey(),
                    'name' => $template->name,
                    'start_time' => substr($template->start_time, 0, 5),
                    'end_time' => substr($template->end_time, 0, 5),
                    'break_minutes' => $template->break_minutes,
                    'crosses_midnight' => $template->crosses_midnight,
                ]),
            'departmentOptions' => $this->departmentOptions($request),
            'officeOptions' => \App\Models\OfficeLocation::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'permissions' => [
                'can_manage' => $request->user()->hasPermission('roster.manage'),
                'can_publish' => $request->user()->hasPermission('roster.publish'),
                'can_supervise' => $request->user()->hasPermission(
                    'roster.supervise',
                ),
            ],
        ]);
    }

    public function generate(
        Request $request,
        RosterGenerator $generator,
    ): RedirectResponse {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $start = CarbonImmutable::createFromFormat(
            'Y-m',
            $validated['month'],
        )->startOfMonth();
        $period = RosterPeriod::query()->firstOrCreate(
            ['period_start' => $start->toDateString()],
            [
                'period_end' => $start->endOfMonth()->toDateString(),
                'status' => 'draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => $request->user()->getAuthIdentifier(),
                'updated_by' => $request->user()->getAuthIdentifier(),
            ],
        );

        if ($period->status !== 'draft') {
            throw ValidationException::withMessages([
                'month' => 'Roster bulan ini telah diterbitkan dan tidak boleh dijana semula.',
            ]);
        }

        $employees = $this->employeeDirectory($request, enforceVisibility: false);

        if ($employees === []) {
            throw ValidationException::withMessages([
                'roster' => 'Tiada akaun Employee aktif yang dipautkan kepada pekerja dan lokasi.',
            ]);
        }

        $generator->generate($period, array_map(fn (array $employee) => [
            'employee_id' => $employee['employee_id'],
            'user_id' => $employee['user_id'],
            'department_id' => $employee['department_id'],
            'office_location_id' => $employee['office_location_id'],
        ], $employees), $request->user());

        AuditLogger::record(
            $request,
            'roster.generated',
            'roster_periods',
            $period->getKey(),
            newValues: [
                'period_start' => $period->period_start->toDateString(),
                'employee_count' => count($employees),
                'entry_count' => $period->entries()->count(),
                'status' => 'draft',
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Roster berjaya dijana sebagai Draf.',
        ]);
    }

    public function updateEntry(
        Request $request,
        RosterEntry $entry,
    ): RedirectResponse {
        $period = $entry->period()->firstOrFail();

        if ($period->status !== 'draft') {
            throw ValidationException::withMessages([
                'roster' => 'Hanya roster Draf boleh diubah.',
            ]);
        }

        $validated = $request->validate([
            'day_type' => ['required', Rule::in(RosterEntry::DAY_TYPES)],
            'shift_template_id' => [
                'nullable',
                'integer',
                'required_if:day_type,workday',
                'exists:shift_templates,id',
            ],
            'start_time' => ['nullable', 'date_format:H:i', 'required_if:day_type,workday'],
            'end_time' => ['nullable', 'date_format:H:i', 'required_if:day_type,workday'],
            'break_minutes' => ['nullable', 'integer', 'between:0,240'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $before = $entry->only([
            'shift_template_id',
            'day_type',
            'scheduled_start_at',
            'scheduled_end_at',
            'break_minutes',
            'notes',
        ]);
        $isWorkday = $validated['day_type'] === 'workday';
        $start = $isWorkday
            ? CarbonImmutable::parse(
                "{$entry->work_date->toDateString()} {$validated['start_time']}",
            )
            : null;
        $end = $isWorkday
            ? CarbonImmutable::parse(
                "{$entry->work_date->toDateString()} {$validated['end_time']}",
            )
            : null;

        if ($start && $end && $end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        $entry->update([
            'shift_template_id' => $isWorkday
                ? (int) $validated['shift_template_id']
                : null,
            'day_type' => $validated['day_type'],
            'scheduled_start_at' => $start,
            'scheduled_end_at' => $end,
            'break_minutes' => $isWorkday
                ? (int) ($validated['break_minutes'] ?? 0)
                : 0,
            'source' => 'manual',
            'notes' => $validated['notes'] ?? null,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'roster.entry_updated',
            'roster_entries',
            $entry->getKey(),
            oldValues: $before,
            newValues: $entry->fresh()->only([
                'employee_id',
                'work_date',
                'shift_template_id',
                'day_type',
                'scheduled_start_at',
                'scheduled_end_at',
                'break_minutes',
                'notes',
            ]),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Jadual pekerja berjaya dikemas kini.',
        ]);
    }

    public function publish(
        Request $request,
        RosterPeriod $period,
    ): RedirectResponse {
        if ($period->status !== 'draft' || ! $period->entries()->exists()) {
            throw ValidationException::withMessages([
                'roster' => 'Roster mesti mempunyai rekod Draf sebelum diterbitkan.',
            ]);
        }

        DB::transaction(function () use ($request, $period) {
            $period->update([
                'status' => 'published',
                'published_at' => now(),
                'published_by' => $request->user()->getAuthIdentifier(),
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
            $userIds = $period->entries()
                ->whereNotNull('user_id')
                ->distinct()
                ->pluck('user_id');

            foreach ($userIds as $userId) {
                RosterNotification::query()->create([
                    'user_id' => $userId,
                    'roster_period_id' => $period->getKey(),
                    'title' => 'Roster baharu diterbitkan',
                    'message' => 'Jadual kerja '
                        .$period->period_start->translatedFormat('F Y')
                        .' telah diterbitkan.',
                ]);
            }
        });

        AuditLogger::record(
            $request,
            'roster.published',
            'roster_periods',
            $period->getKey(),
            oldValues: ['status' => 'draft'],
            newValues: ['status' => 'published'],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Roster diterbitkan dan Employee telah dimaklumkan.',
        ]);
    }

    public function lock(
        Request $request,
        RosterPeriod $period,
    ): RedirectResponse {
        if ($period->status !== 'published') {
            throw ValidationException::withMessages([
                'roster' => 'Hanya roster yang telah diterbitkan boleh dikunci.',
            ]);
        }

        $period->update([
            'status' => 'locked',
            'locked_at' => now(),
            'locked_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'roster.locked',
            'roster_periods',
            $period->getKey(),
            oldValues: ['status' => 'published'],
            newValues: ['status' => 'locked'],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Roster telah dikunci dan tidak boleh diubah.',
        ]);
    }

    public function reviewSwap(
        Request $request,
        ShiftSwapRequest $shiftSwapRequest,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_notes' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:status,rejected',
            ],
        ]);
        $canManage = $request->user()->hasPermission('roster.manage');
        $assigned = $request->user()->hasPermission('roster.supervise')
            && in_array(
                $shiftSwapRequest->department_id,
                $this->supervisedDepartmentIds($request),
                true,
            );
        abort_unless($canManage || $assigned, 403);

        $swap = DB::transaction(function () use (
            $request,
            $shiftSwapRequest,
            $validated,
        ) {
            $swap = ShiftSwapRequest::query()
                ->lockForUpdate()
                ->findOrFail($shiftSwapRequest->getKey());

            if ($swap->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Permohonan pertukaran ini telah diselesaikan.',
                ]);
            }

            $requesterEntry = RosterEntry::query()
                ->with('period')
                ->lockForUpdate()
                ->findOrFail($swap->requester_roster_entry_id);
            $targetEntry = RosterEntry::query()
                ->with('period')
                ->lockForUpdate()
                ->findOrFail($swap->target_roster_entry_id);

            if (
                $requesterEntry->period?->status !== 'published'
                || $targetEntry->period?->status !== 'published'
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Roster telah dikunci atau tidak lagi boleh ditukar.',
                ]);
            }

            if ($validated['status'] === 'approved') {
                $requesterShift = $requesterEntry->only([
                    'shift_template_id',
                    'day_type',
                    'scheduled_start_at',
                    'scheduled_end_at',
                    'break_minutes',
                ]);
                $targetShift = $targetEntry->only([
                    'shift_template_id',
                    'day_type',
                    'scheduled_start_at',
                    'scheduled_end_at',
                    'break_minutes',
                ]);
                $requesterEntry->update([
                    ...$this->shiftOnDate(
                        $targetShift,
                        $requesterEntry->work_date,
                    ),
                    'source' => 'swap',
                    'updated_by' => $request->user()->getAuthIdentifier(),
                ]);
                $targetEntry->update([
                    ...$this->shiftOnDate(
                        $requesterShift,
                        $targetEntry->work_date,
                    ),
                    'source' => 'swap',
                    'updated_by' => $request->user()->getAuthIdentifier(),
                ]);
            }

            $swap->update([
                'status' => $validated['status'],
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->getAuthIdentifier(),
                'review_notes' => $validated['review_notes'] ?? null,
            ]);
            $title = $validated['status'] === 'approved'
                ? 'Pertukaran syif diluluskan'
                : 'Pertukaran syif ditolak';

            foreach ([$swap->requester_user_id, $swap->target_user_id] as $userId) {
                RosterNotification::query()->create([
                    'user_id' => $userId,
                    'shift_swap_request_id' => $swap->getKey(),
                    'title' => $title,
                    'message' => 'Permohonan pertukaran syif telah '
                        .($validated['status'] === 'approved'
                            ? 'diluluskan.'
                            : 'ditolak.'),
                ]);
            }

            return $swap;
        });

        AuditLogger::record(
            $request,
            $swap->status === 'approved'
                ? 'roster.swap_approved'
                : 'roster.swap_rejected',
            'shift_swap_requests',
            $swap->getKey(),
            oldValues: ['status' => 'pending'],
            newValues: [
                'status' => $swap->status,
                'review_notes' => $swap->review_notes,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $swap->status === 'approved'
                ? 'Pertukaran syif diluluskan.'
                : 'Pertukaran syif ditolak.',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $period = RosterPeriod::query()
            ->whereDate(
                'period_start',
                CarbonImmutable::createFromFormat('Y-m', $filters['month'])
                    ->startOfMonth(),
            )
            ->firstOrFail();
        $directory = collect($this->employeeDirectory($request))->keyBy(
            fn (array $employee) => (string) $employee['employee_id'],
        );
        $matchingIds = $directory
            ->when(
                $filters['search'] !== '',
                fn (Collection $employees) => $employees->filter(
                    fn (array $employee) => str_contains(
                        strtolower(
                            ($employee['employee_number'] ?? '').' '
                            .$employee['name'],
                        ),
                        strtolower($filters['search']),
                    ),
                ),
            )
            ->pluck('employee_id')
            ->all();
        $rows = RosterEntry::query()
            ->with(['shiftTemplate:id,name', 'officeLocation:id,name'])
            ->where('roster_period_id', $period->getKey())
            ->when(
                $directory->isEmpty(),
                fn (Builder $query) => $query->whereRaw('1 = 0'),
                fn (Builder $query) => $query->whereIn(
                    'employee_id',
                    $directory->keys()->all(),
                ),
            )
            ->when(
                $filters['search'] !== '',
                fn (Builder $query) => $matchingIds === []
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('employee_id', $matchingIds),
            )
            ->when(
                $filters['department_id'] !== '',
                fn (Builder $query) => $query->where(
                    'department_id',
                    (int) $filters['department_id'],
                ),
            )
            ->when(
                $filters['office_id'] !== '',
                fn (Builder $query) => $query->where(
                    'office_location_id',
                    (int) $filters['office_id'],
                ),
            )
            ->when(
                $filters['day_type'] !== '',
                fn (Builder $query) => $query->where(
                    'day_type',
                    $filters['day_type'],
                ),
            )
            ->orderBy('work_date')
            ->orderBy('employee_id')
            ->get();

        AuditLogger::record(
            $request,
            'roster.report_exported',
            'roster_periods',
            $period->getKey(),
            newValues: [
                'month' => $filters['month'],
                'search' => $filters['search'],
                'department_id' => $filters['department_id'],
                'office_id' => $filters['office_id'],
                'day_type' => $filters['day_type'],
                'row_count' => $rows->count(),
            ],
        );

        return response()->streamDownload(function () use ($rows, $directory) {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, [
                'ID Pekerja',
                'Nama',
                'Jabatan',
                'Lokasi',
                'Tarikh',
                'Jenis Hari',
                'Syif',
                'Mula',
                'Tamat',
                'Rehat (minit)',
                'Sumber',
            ]);

            foreach ($rows as $entry) {
                $employee = $directory->get((string) $entry->employee_id, []);
                fputcsv($stream, [
                    $this->csvValue($employee['employee_number'] ?? ''),
                    $this->csvValue($employee['name'] ?? ''),
                    $this->csvValue($employee['department_name'] ?? ''),
                    $this->csvValue($entry->officeLocation?->name ?? ''),
                    $entry->work_date?->toDateString(),
                    $entry->day_type,
                    $this->csvValue($entry->shiftTemplate?->name ?? ''),
                    $entry->scheduled_start_at?->format('Y-m-d H:i'),
                    $entry->scheduled_end_at?->format('Y-m-d H:i'),
                    $entry->break_minutes,
                    $entry->source,
                ]);
            }

            fclose($stream);
        }, 'roster-'.$filters['month'].'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{search: string, month: string, department_id: string, office_id: string, day_type: string}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'month' => ['nullable', 'date_format:Y-m'],
            'department_id' => ['nullable', 'integer'],
            'office_id' => ['nullable', 'integer', 'exists:office_locations,id'],
            'day_type' => ['nullable', Rule::in(RosterEntry::DAY_TYPES)],
        ]);

        return [
            'search' => trim($validated['search'] ?? ''),
            'month' => $validated['month'] ?? now()->format('Y-m'),
            'department_id' => isset($validated['department_id'])
                ? (string) $validated['department_id']
                : '',
            'office_id' => isset($validated['office_id'])
                ? (string) $validated['office_id']
                : '',
            'day_type' => $validated['day_type'] ?? '',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function employeeDirectory(
        Request $request,
        bool $enforceVisibility = true,
    ): array {
        $links = EmployeeUserLink::query()
            ->with('user:id,name,email')
            ->where('is_active', true)
            ->get()
            ->keyBy('employee_id');
        $employeeIds = $links->keys();

        if ($employeeIds->isEmpty()) {
            return [];
        }

        $positions = DB::connection('ibco')
            ->table('maklumatjawatan')
            ->whereIn('id_pekerja', $employeeIds)
            ->where('rcd_enable', 1)
            ->orderByDesc('id')
            ->get(['id_pekerja', 'id_department'])
            ->unique('id_pekerja')
            ->keyBy(fn ($position) => (string) $position->id_pekerja);
        $departmentIds = $positions
            ->pluck('id_department')
            ->filter()
            ->unique();
        $departments = $departmentIds->isEmpty()
            ? collect()
            : DB::connection('ibco')
                ->table('xdepartment')
                ->whereIn('id', $departmentIds)
                ->get(['id', 'description'])
                ->keyBy(fn ($department) => (string) $department->id);
        $supervised = $enforceVisibility
            && $request->user()->hasPermission('roster.supervise')
            && ! $request->user()->hasPermission('roster.manage')
            ? $this->supervisedDepartmentIds($request)
            : null;

        return DB::connection('ibco')
            ->table('maklumatpekerja')
            ->whereIn('id', $employeeIds)
            ->where('rcd_enable', 1)
            ->orderBy('nama')
            ->get(['id', 'employeeID', 'nama'])
            ->map(function ($employee) use (
                $links,
                $positions,
                $departments,
            ) {
                $link = $links->get((string) $employee->id)
                    ?? $links->get((int) $employee->id);
                $position = $positions->get((string) $employee->id);
                $departmentId = $position?->id_department === null
                    ? null
                    : (int) $position->id_department;

                return [
                    'employee_id' => (int) $employee->id,
                    'employee_number' => $employee->employeeID,
                    'name' => $employee->nama ?? "Pekerja #{$employee->id}",
                    'user_id' => $link?->user_id,
                    'user_name' => $link?->user?->name,
                    'department_id' => $departmentId,
                    'department_name' => $departmentId
                        ? $departments->get((string) $departmentId)?->description
                            ?? 'Tanpa Jabatan'
                        : 'Tanpa Jabatan',
                    'office_location_id' => $link?->office_location_id,
                ];
            })
            ->when(
                is_array($supervised),
                fn (Collection $employees) => $employees->whereIn(
                    'department_id',
                    $supervised,
                ),
            )
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function departmentOptions(Request $request): array
    {
        $query = DB::connection('ibco')
            ->table('xdepartment')
            ->where('rcd_enable', 1);

        if (
            $request->user()->hasPermission('roster.supervise')
            && ! $request->user()->hasPermission('roster.manage')
        ) {
            $query->whereIn('id', $this->supervisedDepartmentIds($request));
        }

        return $query->orderBy('description')
            ->get(['id', 'description'])
            ->map(fn ($department) => [
                'id' => (int) $department->id,
                'name' => $department->description,
            ])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function supervisedDepartmentIds(Request $request): array
    {
        return OvertimeApprovalAssignment::query()
            ->where('approver_user_id', $request->user()->getAuthIdentifier())
            ->where('is_active', true)
            ->pluck('department_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  Collection<int, RosterEntry>  $entries
     * @return array<string, GeoAttendanceRecord>
     */
    private function attendanceMap(Collection $entries): array
    {
        if ($entries->isEmpty()) {
            return [];
        }

        return GeoAttendanceRecord::query()
            ->whereIn('employee_id', $entries->pluck('employee_id')->unique())
            ->whereBetween('attendance_date', [
                $entries->min('work_date')->toDateString(),
                $entries->max('work_date')->toDateString(),
            ])
            ->where('status', 'active')
            ->get()
            ->mapWithKeys(fn (GeoAttendanceRecord $record) => [
                $record->employee_id.'|'.$record->attendance_date->toDateString()
                    => $record,
            ])
            ->all();
    }

    /**
     * @param  Collection<int, RosterEntry>  $entries
     * @return array<string, bool>
     */
    private function leaveMap(Collection $entries): array
    {
        if ($entries->isEmpty()) {
            return [];
        }

        $map = [];
        $leaves = EmployeeLeaveRequest::query()
            ->whereIn('employee_id', $entries->pluck('employee_id')->unique())
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $entries->max('work_date'))
            ->whereDate('end_date', '>=', $entries->min('work_date'))
            ->get();

        foreach ($leaves as $leave) {
            for (
                $date = CarbonImmutable::parse($leave->start_date);
                $date->lte($leave->end_date);
                $date = $date->addDay()
            ) {
                $map[$leave->employee_id.'|'.$date->toDateString()] = true;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, GeoAttendanceRecord>  $attendanceMap
     * @param  array<string, bool>  $leaveMap
     * @return array<string, mixed>
     */
    private function entryPayload(
        RosterEntry $entry,
        ?array $employee,
        array $attendanceMap,
        array $leaveMap,
    ): array {
        $key = $entry->employee_id.'|'.$entry->work_date->toDateString();
        $attendance = $attendanceMap[$key] ?? null;
        $onLeave = isset($leaveMap[$key]);
        $isAbsent = $entry->day_type === 'workday'
            && $entry->work_date->isBefore(today())
            && ! $attendance
            && ! $onLeave;

        return [
            'id' => $entry->getKey(),
            'employee' => $employee,
            'office' => $entry->officeLocation
                ? [
                    'id' => $entry->officeLocation->getKey(),
                    'name' => $entry->officeLocation->name,
                ]
                : null,
            'shift_template' => $entry->shiftTemplate
                ? [
                    'id' => $entry->shiftTemplate->getKey(),
                    'code' => $entry->shiftTemplate->code,
                    'name' => $entry->shiftTemplate->name,
                ]
                : null,
            'work_date' => $entry->work_date->toDateString(),
            'day_type' => $entry->day_type,
            'scheduled_start_at' => $entry->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $entry->scheduled_end_at?->toIso8601String(),
            'break_minutes' => $entry->break_minutes,
            'source' => $entry->source,
            'notes' => $entry->notes,
            'attendance' => $attendance
                ? [
                    'clock_in_at' => $attendance->clock_in_at?->toIso8601String(),
                    'clock_out_at' => $attendance->clock_out_at?->toIso8601String(),
                    'late_minutes' => $attendance->late_minutes,
                    'early_departure_minutes' => $attendance
                        ->early_departure_minutes,
                ]
                : null,
            'on_leave' => $onLeave,
            'is_absent' => $isAbsent,
        ];
    }

    /**
     * @param  Collection<int, RosterEntry>  $entries
     * @return array<string, int|float>
     */
    private function statistics(Collection $entries): array
    {
        $attendanceMap = $this->attendanceMap($entries);
        $leaveMap = $this->leaveMap($entries);
        $late = collect($attendanceMap)->sum('late_minutes');
        $early = collect($attendanceMap)->sum('early_departure_minutes');
        $absent = $entries->filter(function (RosterEntry $entry) use (
            $attendanceMap,
            $leaveMap,
        ) {
            $key = $entry->employee_id.'|'.$entry->work_date->toDateString();

            return $entry->day_type === 'workday'
                && $entry->work_date->isBefore(today())
                && ! isset($attendanceMap[$key])
                && ! isset($leaveMap[$key]);
        })->count();

        return [
            'employees' => $entries->pluck('employee_id')->unique()->count(),
            'workdays' => $entries->where('day_type', 'workday')->count(),
            'rest_days' => $entries->whereIn(
                'day_type',
                ['rest_day', 'off'],
            )->count(),
            'public_holidays' => $entries
                ->where('day_type', 'public_holiday')
                ->count(),
            'late_minutes' => (int) $late,
            'early_departure_minutes' => (int) $early,
            'absent' => $absent,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptyStatistics(): array
    {
        return [
            'employees' => 0,
            'workdays' => 0,
            'rest_days' => 0,
            'public_holidays' => 0,
            'late_minutes' => 0,
            'early_departure_minutes' => 0,
            'absent' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function periodPayload(RosterPeriod $period): array
    {
        return [
            'id' => $period->getKey(),
            'period_start' => $period->period_start->toDateString(),
            'period_end' => $period->period_end->toDateString(),
            'status' => $period->status,
            'notes' => $period->notes,
            'published_at' => $period->published_at?->toIso8601String(),
            'locked_at' => $period->locked_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function swapPayload(ShiftSwapRequest $swap): array
    {
        return [
            'id' => $swap->getKey(),
            'requester' => $swap->requester?->name,
            'target' => $swap->target?->name,
            'reason' => $swap->reason,
            'status' => $swap->status,
            'requester_entry' => [
                'work_date' => $swap->requesterEntry?->work_date?->toDateString(),
                'shift_name' => $swap->requesterEntry?->shiftTemplate?->name,
            ],
            'target_entry' => [
                'work_date' => $swap->targetEntry?->work_date?->toDateString(),
                'shift_name' => $swap->targetEntry?->shiftTemplate?->name,
            ],
            'created_at' => $swap->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $shift
     * @return array<string, mixed>
     */
    private function shiftOnDate(array $shift, CarbonImmutable $date): array
    {
        $start = isset($shift['scheduled_start_at'])
            && $shift['scheduled_start_at']
            ? CarbonImmutable::parse($shift['scheduled_start_at'])
            : null;
        $end = isset($shift['scheduled_end_at'])
            && $shift['scheduled_end_at']
            ? CarbonImmutable::parse($shift['scheduled_end_at'])
            : null;
        $scheduledStart = $start
            ? CarbonImmutable::parse(
                "{$date->toDateString()} {$start->format('H:i:s')}",
            )
            : null;
        $scheduledEnd = $end
            ? CarbonImmutable::parse(
                "{$date->toDateString()} {$end->format('H:i:s')}",
            )
            : null;

        if (
            $scheduledStart
            && $scheduledEnd
            && (
                $end->toDateString() !== $start->toDateString()
                || $scheduledEnd->lessThanOrEqualTo($scheduledStart)
            )
        ) {
            $scheduledEnd = $scheduledEnd->addDay();
        }

        return [
            'shift_template_id' => $shift['shift_template_id'],
            'day_type' => $shift['day_type'],
            'scheduled_start_at' => $scheduledStart,
            'scheduled_end_at' => $scheduledEnd,
            'break_minutes' => $shift['break_minutes'],
        ];
    }

    private function csvValue(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\\-@]/', $value) ? "'{$value}" : $value;
    }
}
