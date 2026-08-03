<?php

namespace App\Http\Controllers;

use App\Models\EmployeeRecord;
use App\Http\Requests\ReviewOvertimeRequest;
use App\Models\OvertimeApprovalAssignment;
use App\Models\OvertimeNotification;
use App\Models\OvertimeRequest;
use App\Models\OvertimeType;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OvertimeRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->validatedFilters($request);
        $query = $this->filteredQuery($request, $filters);
        $requests = (clone $query)
            ->with([
                'overtimeType:id,name,rate_multiplier,minimum_minutes',
                'attendanceRecord:id,attendance_date,clock_in_at,clock_out_at,status',
                'supervisorReviewer:id,name',
                'reviewer:id,name',
            ])
            ->latest('submitted_at')
            ->paginate(20)
            ->withQueryString();
        $employeeMap = $this->employeeMap(
            collect($requests->items())->pluck('employee_id')->all(),
        );

        $requests->through(
            fn (OvertimeRequest $overtime) => $this->requestPayload(
                $overtime,
                $employeeMap,
            ),
        );

        $visibleBase = $this->visibleQuery($request);
        $statusCounts = (clone $visibleBase)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $stageCounts = (clone $visibleBase)
            ->where('status', 'pending')
            ->selectRaw('approval_stage, COUNT(*) as aggregate')
            ->groupBy('approval_stage')
            ->pluck('aggregate', 'approval_stage');
        $approvedMinutes = (clone $visibleBase)
            ->where('status', 'approved')
            ->when(
                ! $filters['all_months'],
                fn (Builder $query) => $this->applyMonthFilter(
                    $query,
                    $filters['month'],
                ),
            )
            ->sum('approved_minutes');
        $report = (clone $query)
            ->selectRaw(
                'overtime_type_id, status, COUNT(*) as total, '
                .'SUM(requested_minutes) as requested_minutes, '
                .'SUM(COALESCE(approved_minutes, 0)) as approved_minutes',
            )
            ->groupBy('overtime_type_id', 'status')
            ->get();
        $typeMap = OvertimeType::query()->pluck('name', 'id');

        return Inertia::render('OvertimeRequests/Index', [
            'requests' => $requests,
            'filters' => $filters,
            'statistics' => [
                'total' => (int) $statusCounts->sum(),
                'pending_supervisor' => (int) ($stageCounts['supervisor'] ?? 0),
                'pending_hr' => (int) ($stageCounts['hr'] ?? 0),
                'approved' => (int) ($statusCounts['approved'] ?? 0),
                'approved_hours' => round(((int) $approvedMinutes) / 60, 2),
            ],
            'overtimeTypes' => OvertimeType::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'reportByType' => $report->map(fn ($row) => [
                'overtime_type' => $typeMap[$row->overtime_type_id] ?? 'Tidak diketahui',
                'status' => $row->status,
                'total' => (int) $row->total,
                'requested_hours' => round(((int) $row->requested_minutes) / 60, 2),
                'approved_hours' => round(((int) $row->approved_minutes) / 60, 2),
            ]),
            'permissions' => [
                'can_supervise' => $request->user()->hasPermission('overtime.supervise'),
                'can_manage' => $request->user()->hasPermission('overtime.manage'),
                'can_approve' => $request->user()->hasPermission('overtime.approve'),
            ],
        ]);
    }

    public function supervisorReview(
        Request $request,
        OvertimeRequest $overtimeRequest,
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('overtime.supervise'), 403);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_notes' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:status,rejected',
            ],
        ]);

        $overtimeRequest = DB::transaction(function () use (
            $request,
            $overtimeRequest,
            $validated,
        ) {
            $locked = OvertimeRequest::query()
                ->lockForUpdate()
                ->findOrFail($overtimeRequest->getKey());

            if (
                $locked->status !== 'pending'
                || $locked->approval_stage !== 'supervisor'
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Permohonan ini bukan lagi di peringkat semakan penyelia.',
                ]);
            }

            $assigned = OvertimeApprovalAssignment::query()
                ->where('department_id', $locked->department_id)
                ->where('approver_user_id', $request->user()->getAuthIdentifier())
                ->where('is_active', true)
                ->exists();

            abort_unless(
                $assigned || $request->user()->hasPermission('overtime.manage'),
                403,
            );

            $supported = $validated['status'] === 'approved';
            $locked->update([
                'status' => $supported ? 'pending' : 'rejected',
                'approval_stage' => $supported ? 'hr' : 'completed',
                'supervisor_reviewed_at' => now(),
                'supervisor_reviewed_by' => $request->user()->getAuthIdentifier(),
                'supervisor_review_notes' => $validated['review_notes'] ?? null,
            ]);
            OvertimeNotification::query()->create([
                'user_id' => $locked->user_id,
                'overtime_request_id' => $locked->getKey(),
                'title' => $supported
                    ? 'Permohonan OT disokong penyelia'
                    : 'Permohonan OT ditolak penyelia',
                'message' => $supported
                    ? 'Permohonan OT anda telah dihantar kepada Pengurus HR untuk kelulusan akhir.'
                    : 'Permohonan OT anda tidak disokong oleh penyelia.',
            ]);

            return $locked;
        });

        AuditLogger::record(
            $request,
            $overtimeRequest->status === 'pending'
                ? 'overtime.supervisor_approved'
                : 'overtime.supervisor_rejected',
            'overtime_requests',
            $overtimeRequest->getKey(),
            oldValues: ['status' => 'pending', 'approval_stage' => 'supervisor'],
            newValues: [
                'employee_id' => $overtimeRequest->employee_id,
                'status' => $overtimeRequest->status,
                'approval_stage' => $overtimeRequest->approval_stage,
                'review_notes' => $overtimeRequest->supervisor_review_notes,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $overtimeRequest->status === 'pending'
                ? 'Permohonan OT disokong dan dihantar kepada Pengurus HR.'
                : 'Permohonan OT telah ditolak.',
        ]);
    }

    public function review(
        ReviewOvertimeRequest $request,
        OvertimeRequest $overtimeRequest,
    ): RedirectResponse {
        $overtimeRequest = DB::transaction(function () use (
            $request,
            $overtimeRequest,
        ) {
            $locked = OvertimeRequest::query()
                ->with('overtimeType')
                ->lockForUpdate()
                ->findOrFail($overtimeRequest->getKey());

            if (
                $locked->status !== 'pending'
                || ! in_array($locked->approval_stage, ['hr', null], true)
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Permohonan ini belum sampai ke peringkat HR atau telah selesai.',
                ]);
            }

            if ((int) $locked->user_id === (int) $request->user()->getAuthIdentifier()) {
                throw ValidationException::withMessages([
                    'status' => 'Anda tidak boleh meluluskan permohonan OT sendiri.',
                ]);
            }

            $approved = $request->validated('status') === 'approved';
            $approvedMinutes = $approved
                ? (int) $request->validated('approved_minutes')
                : null;

            if (
                $approved
                && (
                    $approvedMinutes > $locked->requested_minutes
                    || $approvedMinutes < $locked->overtimeType->minimum_minutes
                )
            ) {
                throw ValidationException::withMessages([
                    'approved_minutes' => "Minit diluluskan mesti antara {$locked->overtimeType->minimum_minutes} dan {$locked->requested_minutes}.",
                ]);
            }

            $locked->update([
                'status' => $approved ? 'approved' : 'rejected',
                'approval_stage' => 'completed',
                'approved_minutes' => $approvedMinutes,
                'review_notes' => $request->validated('review_notes'),
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->getAuthIdentifier(),
            ]);
            OvertimeNotification::query()->create([
                'user_id' => $locked->user_id,
                'overtime_request_id' => $locked->getKey(),
                'title' => $approved
                    ? 'Permohonan OT diluluskan'
                    : 'Permohonan OT ditolak',
                'message' => $approved
                    ? 'Pengurus HR meluluskan '.number_format($approvedMinutes / 60, 2).' jam OT anda.'
                    : 'HR telah menolak permohonan OT anda.',
            ]);

            return $locked;
        });

        AuditLogger::record(
            $request,
            $overtimeRequest->status === 'approved'
                ? 'overtime.approved'
                : 'overtime.rejected',
            'overtime_requests',
            $overtimeRequest->getKey(),
            oldValues: ['status' => 'pending', 'approval_stage' => 'hr'],
            newValues: [
                'employee_id' => $overtimeRequest->employee_id,
                'status' => $overtimeRequest->status,
                'approval_stage' => 'completed',
                'approved_minutes' => $overtimeRequest->approved_minutes,
                'review_notes' => $overtimeRequest->review_notes,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $overtimeRequest->status === 'approved'
                ? 'Permohonan OT telah diluluskan.'
                : 'Permohonan OT telah ditolak.',
        ]);
    }

    public function cancelApproved(
        Request $request,
        OvertimeRequest $overtimeRequest,
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('overtime.approve'), 403);
        $validated = $request->validate([
            'cancellation_notes' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        if ($overtimeRequest->status !== 'approved') {
            throw ValidationException::withMessages([
                'cancellation_notes' => 'Hanya OT yang telah diluluskan boleh dibatalkan oleh HR.',
            ]);
        }

        if ((int) $overtimeRequest->user_id === (int) $request->user()->getAuthIdentifier()) {
            throw ValidationException::withMessages([
                'cancellation_notes' => 'Anda tidak boleh membatalkan kelulusan OT sendiri.',
            ]);
        }

        $oldMinutes = $overtimeRequest->approved_minutes;
        $overtimeRequest->update([
            'status' => 'cancelled',
            'approved_minutes' => null,
            'review_notes' => trim(
                ($overtimeRequest->review_notes ? $overtimeRequest->review_notes."\n\n" : '')
                .'Pembatalan HR: '.$validated['cancellation_notes'],
            ),
        ]);
        OvertimeNotification::query()->create([
            'user_id' => $overtimeRequest->user_id,
            'overtime_request_id' => $overtimeRequest->getKey(),
            'title' => 'OT diluluskan dibatalkan',
            'message' => 'HR telah membatalkan rekod OT yang sebelum ini diluluskan.',
        ]);

        AuditLogger::record(
            $request,
            'overtime.approved_cancelled',
            'overtime_requests',
            $overtimeRequest->getKey(),
            oldValues: ['status' => 'approved', 'approved_minutes' => $oldMinutes],
            newValues: [
                'employee_id' => $overtimeRequest->employee_id,
                'status' => 'cancelled',
                'approved_minutes' => null,
                'cancellation_notes' => $validated['cancellation_notes'],
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Kelulusan OT telah dibatalkan.',
        ]);
    }

    public function reportCsv(Request $request): StreamedResponse
    {
        abort_unless(
            $request->user()->hasPermission('overtime.manage')
                || $request->user()->hasPermission('overtime.approve')
                || $request->user()->hasPermission('overtime.supervise'),
            403,
        );
        $filters = $this->validatedFilters($request);
        $rows = $this->filteredQuery($request, $filters)
            ->with('overtimeType:id,name,rate_multiplier')
            ->orderByDesc('work_date')
            ->limit(5000)
            ->get();
        $employeeMap = $this->employeeMap($rows->pluck('employee_id')->all());

        return response()->streamDownload(function () use ($rows, $employeeMap) {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, [
                'ID Pekerja',
                'Nama',
                'Jenis OT',
                'Tarikh',
                'Mula',
                'Tamat',
                'Jam Dipohon',
                'Jam Diluluskan',
                'Kadar Gandaan',
                'Status',
                'Peringkat',
                'Pengesahan Kehadiran',
            ]);

            foreach ($rows as $overtime) {
                $employee = $employeeMap[(string) $overtime->employee_id] ?? [];
                fputcsv($stream, [
                    $this->csvValue($employee['employee_id'] ?? ''),
                    $this->csvValue($employee['name'] ?? ''),
                    $this->csvValue($overtime->overtimeType?->name ?? ''),
                    $overtime->work_date?->toDateString(),
                    $overtime->start_at?->format('H:i'),
                    $overtime->end_at?->format('H:i'),
                    round($overtime->requested_minutes / 60, 2),
                    $overtime->approved_minutes === null
                        ? ''
                        : round($overtime->approved_minutes / 60, 2),
                    (float) ($overtime->overtimeType?->rate_multiplier ?? 0),
                    $overtime->status,
                    $overtime->approval_stage,
                    $overtime->attendance_match_status,
                ]);
            }

            fclose($stream);
        }, 'laporan-ot-'.$filters['month'].'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{search: string, status: string, stage: string, overtime_type_id: string, month: string, all_months: bool}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(OvertimeRequest::STATUSES)],
            'stage' => ['nullable', Rule::in(['supervisor', 'hr', 'completed'])],
            'overtime_type_id' => ['nullable', 'integer', 'exists:overtime_types,id'],
            'month' => ['nullable', 'date_format:Y-m'],
            'all_months' => ['nullable', 'boolean'],
        ]);

        return [
            'search' => trim($validated['search'] ?? ''),
            'status' => $validated['status'] ?? '',
            'stage' => $validated['stage'] ?? '',
            'overtime_type_id' => isset($validated['overtime_type_id'])
                ? (string) $validated['overtime_type_id']
                : '',
            'month' => $validated['month'] ?? now()->format('Y-m'),
            'all_months' => (bool) ($validated['all_months'] ?? false),
        ];
    }

    /**
     * @param  array{search: string, status: string, stage: string, overtime_type_id: string, month: string, all_months: bool}  $filters
     */
    private function filteredQuery(Request $request, array $filters): Builder
    {
        $matchingEmployeeIds = $this->matchingEmployeeIds($filters['search']);

        return $this->visibleQuery($request)
            ->when($filters['search'] !== '', function (Builder $query) use (
                $matchingEmployeeIds,
                $filters,
            ) {
                $query->where(function (Builder $query) use (
                    $matchingEmployeeIds,
                    $filters,
                ) {
                    if ($matchingEmployeeIds !== []) {
                        $query->whereIn('employee_id', $matchingEmployeeIds);
                    } else {
                        $query->whereRaw('1 = 0');
                    }

                    $query->orWhere('reason', 'like', "%{$filters['search']}%")
                        ->orWhere('work_description', 'like', "%{$filters['search']}%");
                });
            })
            ->when(
                $filters['status'] !== '',
                fn (Builder $query) => $query->where('status', $filters['status']),
            )
            ->when(
                $filters['stage'] !== '',
                fn (Builder $query) => $query->where('approval_stage', $filters['stage']),
            )
            ->when(
                $filters['overtime_type_id'] !== '',
                fn (Builder $query) => $query->where(
                    'overtime_type_id',
                    (int) $filters['overtime_type_id'],
                ),
            )
            ->when(
                ! $filters['all_months'],
                fn (Builder $query) => $this->applyMonthFilter(
                    $query,
                    $filters['month'],
                ),
            );
    }

    private function applyMonthFilter(Builder $query, string $month): Builder
    {
        $period = Carbon::createFromFormat('Y-m', $month);

        return $query
            ->whereYear('work_date', $period->year)
            ->whereMonth('work_date', $period->month);
    }

    private function visibleQuery(Request $request): Builder
    {
        $query = OvertimeRequest::query();

        if (
            $request->user()->hasPermission('overtime.manage')
            || $request->user()->hasPermission('overtime.approve')
        ) {
            return $query;
        }

        abort_unless($request->user()->hasPermission('overtime.supervise'), 403);
        $departmentIds = OvertimeApprovalAssignment::query()
            ->where('approver_user_id', $request->user()->getAuthIdentifier())
            ->where('is_active', true)
            ->pluck('department_id');

        return $query->whereIn('department_id', $departmentIds);
    }

    /**
     * @param  array<string, array<string, mixed>>  $employeeMap
     * @return array<string, mixed>
     */
    private function requestPayload(
        OvertimeRequest $overtime,
        array $employeeMap,
    ): array {
        return [
            'id' => $overtime->getKey(),
            'employee' => $employeeMap[(string) $overtime->employee_id] ?? null,
            'overtime_type' => $overtime->overtimeType?->name,
            'rate_multiplier' => (float) ($overtime->overtimeType?->rate_multiplier ?? 0),
            'minimum_minutes' => $overtime->overtimeType?->minimum_minutes ?? 1,
            'work_date' => $overtime->work_date?->toDateString(),
            'start_at' => $overtime->start_at?->toIso8601String(),
            'end_at' => $overtime->end_at?->toIso8601String(),
            'break_minutes' => $overtime->break_minutes,
            'requested_minutes' => $overtime->requested_minutes,
            'approved_minutes' => $overtime->approved_minutes,
            'reason' => $overtime->reason,
            'work_description' => $overtime->work_description,
            'status' => $overtime->status,
            'approval_stage' => $overtime->approval_stage,
            'attendance_match_status' => $overtime->attendance_match_status,
            'roster_day_type' => $overtime->roster_day_type,
            'roster_match_status' => $overtime->roster_match_status,
            'attendance' => $overtime->attendanceRecord
                ? [
                    'clock_in_at' => $overtime->attendanceRecord->clock_in_at?->toIso8601String(),
                    'clock_out_at' => $overtime->attendanceRecord->clock_out_at?->toIso8601String(),
                    'status' => $overtime->attendanceRecord->status,
                ]
                : null,
            'submitted_at' => $overtime->submitted_at?->toIso8601String(),
            'supervisor_review_notes' => $overtime->supervisor_review_notes,
            'supervisor_reviewer' => $overtime->supervisorReviewer?->name,
            'review_notes' => $overtime->review_notes,
            'reviewer' => $overtime->reviewer?->name,
            'has_attachment' => $overtime->attachment_path !== null,
            'attachment_name' => $overtime->attachment_original_name,
        ];
    }

    /**
     * @param  array<int, int|string>  $employeeIds
     * @return array<string, array{id: int, employee_id: string|null, name: string|null}>
     */
    private function employeeMap(array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $legacy = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->whereIn('id', $employeeIds)
            ->get(['id', 'employeeID', 'nama'])
            ->mapWithKeys(fn ($employee) => [
                (string) $employee->id => [
                    'id' => (int) $employee->id,
                    'employee_id' => $employee->employeeID,
                    'name' => $employee->nama,
                ],
            ])
            ->all();
        $local = EmployeeRecord::query()
            ->whereIn('directory_id', $employeeIds)
            ->get()
            ->mapWithKeys(fn (EmployeeRecord $employee) => [
                (string) $employee->directory_id => [
                    'id' => $employee->directory_id,
                    'employee_id' => $employee->employee_number,
                    'name' => $employee->name,
                ],
            ])
            ->all();

        return $legacy + $local;
    }

    /**
     * @return array<int, int>
     */
    private function matchingEmployeeIds(string $search): array
    {
        if ($search === '') {
            return [];
        }

        return DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('rcd_enable', 1)
            ->where(function ($query) use ($search) {
                $query->where('nama', 'like', "%{$search}%")
                    ->orWhere('employeeID', 'like', "%{$search}%");
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->concat(
                EmployeeRecord::query()
                    ->whereIn('status', ['pending_activation', 'active'])
                    ->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('employee_number', 'like', "%{$search}%");
                    })
                    ->pluck('directory_id'),
            )
            ->unique()
            ->all();
    }

    private function csvValue(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\\-@]/', $value) ? "'{$value}" : $value;
    }
}
