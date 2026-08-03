<?php

namespace App\Http\Controllers;

use App\Models\EmployeeRecord;
use App\Http\Requests\ReviewEmployeeLeaveRequest;
use App\Models\EmployeeLeaveRequest;
use App\Models\LeaveApprovalAssignment;
use App\Models\LeaveNotification;
use App\Models\LeaveType;
use App\Support\AuditLogger;
use App\Support\LeaveBalanceManager;
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

class LeaveRequestController extends Controller
{
    public function __construct(
        private readonly LeaveBalanceManager $balances,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->validatedFilters($request);
        $query = $this->filteredQuery($request, $filters);
        $requests = (clone $query)
            ->with([
                'reviewer:id,name',
                'supervisorReviewer:id,name',
                'systemLeaveType:id,name,deduct_balance',
            ])
            ->latest('submitted_at')
            ->paginate(20)
            ->withQueryString();
        $employeeMap = $this->employeeMap(
            collect($requests->items())->pluck('employee_id')->all(),
        );

        $requests->through(
            fn (EmployeeLeaveRequest $leave) => $this->requestPayload(
                $leave,
                $employeeMap,
            ),
        );

        $visibleBase = $this->visibleQuery($request);
        $counts = (clone $visibleBase)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $stageCounts = (clone $visibleBase)
            ->where('status', 'pending')
            ->selectRaw('approval_stage, COUNT(*) as aggregate')
            ->groupBy('approval_stage')
            ->pluck('aggregate', 'approval_stage');
        $month = Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth();
        $calendarRows = $this->visibleQuery($request)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $month->copy()->endOfMonth()->toDateString())
            ->whereDate('end_date', '>=', $month->toDateString())
            ->with('systemLeaveType:id,name')
            ->orderBy('start_date')
            ->limit(300)
            ->get();
        $calendarEmployeeMap = $this->employeeMap(
            $calendarRows->pluck('employee_id')->all(),
        );
        $reportByType = (clone $query)
            ->selectRaw('leave_type_label, status, SUM(requested_days) as days, COUNT(*) as total')
            ->groupBy('leave_type_label', 'status')
            ->orderBy('leave_type_label')
            ->get()
            ->map(fn ($row) => [
                'leave_type' => $row->leave_type_label,
                'status' => $row->status,
                'days' => (float) $row->days,
                'total' => (int) $row->total,
            ]);

        return Inertia::render('LeaveRequests/Index', [
            'requests' => $requests,
            'filters' => $filters,
            'statistics' => [
                'total' => (int) $counts->sum(),
                'pending_supervisor' => (int) ($stageCounts['supervisor'] ?? 0),
                'pending_hr' => (int) ($stageCounts['hr'] ?? 0),
                'approved' => (int) ($counts['approved'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
            ],
            'leaveTypes' => LeaveType::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (LeaveType $type) => [
                    'id' => $type->getKey(),
                    'name' => $type->name,
                ]),
            'calendar' => $calendarRows->map(function (
                EmployeeLeaveRequest $leave,
            ) use ($calendarEmployeeMap) {
                $employee = $calendarEmployeeMap[(string) $leave->employee_id] ?? null;

                return [
                    'id' => $leave->getKey(),
                    'employee_name' => $employee['name'] ?? 'Pekerja',
                    'employee_id' => $employee['employee_id'] ?? null,
                    'leave_type' => $leave->systemLeaveType?->name
                        ?? $leave->leave_type_label,
                    'start_date' => $leave->start_date?->toDateString(),
                    'end_date' => $leave->end_date?->toDateString(),
                    'requested_days' => (float) $leave->requested_days,
                    'status' => $leave->status,
                    'approval_stage' => $leave->approval_stage,
                ];
            }),
            'reportByType' => $reportByType,
            'permissions' => [
                'can_supervise' => $request->user()->hasPermission('leave.supervise'),
                'can_manage' => $request->user()->hasPermission('leave.manage'),
                'can_approve' => $request->user()->hasPermission('leave.approve'),
            ],
        ]);
    }

    public function supervisorReview(
        Request $request,
        EmployeeLeaveRequest $leaveRequest,
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('leave.supervise'), 403);
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_notes' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:status,rejected',
            ],
        ]);

        $leaveRequest = DB::transaction(function () use (
            $request,
            $leaveRequest,
            $validated,
        ) {
            $locked = EmployeeLeaveRequest::query()
                ->lockForUpdate()
                ->findOrFail($leaveRequest->getKey());

            if (
                $locked->status !== 'pending'
                || $locked->approval_stage !== 'supervisor'
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Permohonan ini bukan lagi di peringkat semakan penyelia.',
                ]);
            }

            $isAssignedApprover = LeaveApprovalAssignment::query()
                ->where('department_id', $locked->department_id)
                ->where('approver_user_id', $request->user()->getAuthIdentifier())
                ->where('is_active', true)
                ->exists();

            abort_unless(
                $isAssignedApprover
                    || $request->user()->hasPermission('leave.manage'),
                403,
            );

            $approved = $validated['status'] === 'approved';
            $locked->update([
                'status' => $approved ? 'pending' : 'rejected',
                'approval_stage' => $approved ? 'hr' : 'completed',
                'supervisor_reviewed_at' => now(),
                'supervisor_reviewed_by' => $request->user()->getAuthIdentifier(),
                'supervisor_review_notes' => $validated['review_notes'] ?? null,
            ]);
            LeaveNotification::query()->create([
                'user_id' => $locked->user_id,
                'leave_request_id' => $locked->getKey(),
                'title' => $approved
                    ? 'Permohonan disokong penyelia'
                    : 'Permohonan ditolak penyelia',
                'message' => $approved
                    ? 'Permohonan cuti anda telah dihantar kepada Pengurus HR untuk kelulusan akhir.'
                    : 'Permohonan cuti anda tidak disokong oleh penyelia.',
            ]);

            return $locked;
        });

        AuditLogger::record(
            $request,
            $leaveRequest->status === 'pending'
                ? 'leave.supervisor_approved'
                : 'leave.supervisor_rejected',
            'employee_leave_requests',
            $leaveRequest->getKey(),
            oldValues: [
                'status' => 'pending',
                'approval_stage' => 'supervisor',
            ],
            newValues: [
                'employee_id' => $leaveRequest->employee_id,
                'status' => $leaveRequest->status,
                'approval_stage' => $leaveRequest->approval_stage,
                'review_notes' => $leaveRequest->supervisor_review_notes,
                'reviewed_by' => $leaveRequest->supervisor_reviewed_by,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $leaveRequest->status === 'pending'
                ? 'Permohonan telah disokong dan dihantar kepada Pengurus HR.'
                : 'Permohonan cuti telah ditolak.',
        ]);
    }

    public function review(
        ReviewEmployeeLeaveRequest $request,
        EmployeeLeaveRequest $leaveRequest,
    ): RedirectResponse {
        $leaveRequest = DB::transaction(function () use ($request, $leaveRequest) {
            $locked = EmployeeLeaveRequest::query()
                ->with('systemLeaveType')
                ->lockForUpdate()
                ->findOrFail($leaveRequest->getKey());

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
                    'status' => 'Anda tidak boleh meluluskan permohonan cuti sendiri.',
                ]);
            }

            if ($request->validated('status') === 'approved') {
                $this->balances->deduct($locked, $request->user());
            }

            $locked->update([
                'status' => $request->validated('status'),
                'approval_stage' => 'completed',
                'review_notes' => $request->validated('review_notes'),
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->getAuthIdentifier(),
            ]);
            LeaveNotification::query()->create([
                'user_id' => $locked->user_id,
                'leave_request_id' => $locked->getKey(),
                'title' => $locked->status === 'approved'
                    ? 'Permohonan cuti diluluskan'
                    : 'Permohonan cuti ditolak',
                'message' => $locked->status === 'approved'
                    ? 'HR telah meluluskan permohonan cuti anda.'
                    : 'HR telah menolak permohonan cuti anda.',
            ]);

            return $locked;
        });

        AuditLogger::record(
            $request,
            $leaveRequest->status === 'approved'
                ? 'leave.approved'
                : 'leave.rejected',
            'employee_leave_requests',
            $leaveRequest->getKey(),
            oldValues: [
                'status' => 'pending',
                'approval_stage' => 'hr',
            ],
            newValues: [
                'employee_id' => $leaveRequest->employee_id,
                'status' => $leaveRequest->status,
                'approval_stage' => 'completed',
                'review_notes' => $leaveRequest->review_notes,
                'reviewed_by' => $leaveRequest->reviewed_by,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $leaveRequest->status === 'approved'
                ? 'Permohonan cuti telah diluluskan dan baki dikemas kini.'
                : 'Permohonan cuti telah ditolak.',
        ]);
    }

    public function cancelApproved(
        Request $request,
        EmployeeLeaveRequest $leaveRequest,
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('leave.approve'), 403);
        $validated = $request->validate([
            'cancellation_notes' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $leaveRequest = DB::transaction(function () use (
            $request,
            $leaveRequest,
            $validated,
        ) {
            $locked = EmployeeLeaveRequest::query()
                ->with('systemLeaveType')
                ->lockForUpdate()
                ->findOrFail($leaveRequest->getKey());

            if ($locked->status !== 'approved') {
                throw ValidationException::withMessages([
                    'cancellation_notes' => 'Hanya cuti yang telah diluluskan boleh dibatalkan oleh HR.',
                ]);
            }

            if ((int) $locked->user_id === (int) $request->user()->getAuthIdentifier()) {
                throw ValidationException::withMessages([
                    'cancellation_notes' => 'Anda tidak boleh membatalkan kelulusan cuti sendiri.',
                ]);
            }

            $this->balances->refund($locked, $request->user());
            $locked->update([
                'status' => 'cancelled',
                'review_notes' => trim(
                    ($locked->review_notes ? $locked->review_notes."\n\n" : '')
                    .'Pembatalan HR: '.$validated['cancellation_notes'],
                ),
            ]);
            LeaveNotification::query()->create([
                'user_id' => $locked->user_id,
                'leave_request_id' => $locked->getKey(),
                'title' => 'Cuti diluluskan dibatalkan',
                'message' => 'HR telah membatalkan cuti dan baki berkaitan telah dipulangkan.',
            ]);

            return $locked;
        });

        AuditLogger::record(
            $request,
            'leave.approved_cancelled',
            'employee_leave_requests',
            $leaveRequest->getKey(),
            oldValues: ['status' => 'approved'],
            newValues: [
                'status' => 'cancelled',
                'employee_id' => $leaveRequest->employee_id,
                'cancellation_notes' => $validated['cancellation_notes'],
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Cuti diluluskan telah dibatalkan dan baki dipulangkan.',
        ]);
    }

    public function reportCsv(Request $request): StreamedResponse
    {
        abort_unless(
            $request->user()->hasPermission('leave.manage')
                || $request->user()->hasPermission('leave.approve')
                || $request->user()->hasPermission('leave.supervise'),
            403,
        );
        $filters = $this->validatedFilters($request);
        $rows = $this->filteredQuery($request, $filters)
            ->orderByDesc('submitted_at')
            ->limit(5000)
            ->get();
        $employeeMap = $this->employeeMap($rows->pluck('employee_id')->all());

        return response()->streamDownload(function () use ($rows, $employeeMap) {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, [
                'ID Pekerja',
                'Nama',
                'Jenis Cuti',
                'Tarikh Mula',
                'Tarikh Tamat',
                'Hari',
                'Status',
                'Peringkat',
                'Tarikh Permohonan',
            ]);

            foreach ($rows as $leave) {
                $employee = $employeeMap[(string) $leave->employee_id] ?? [];
                fputcsv($stream, [
                    $this->csvValue($employee['employee_id'] ?? ''),
                    $this->csvValue($employee['name'] ?? ''),
                    $this->csvValue($leave->leave_type_label),
                    $leave->start_date?->toDateString(),
                    $leave->end_date?->toDateString(),
                    (float) $leave->requested_days,
                    $leave->status,
                    $leave->approval_stage,
                    $leave->submitted_at?->toDateTimeString(),
                ]);
            }

            fclose($stream);
        }, 'laporan-cuti-'.$filters['month'].'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{search: string, status: string, stage: string, leave_type_id: string, month: string}
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(EmployeeLeaveRequest::STATUSES)],
            'stage' => ['nullable', Rule::in(['supervisor', 'hr', 'completed'])],
            'leave_type_id' => ['nullable', 'integer', 'exists:leave_types,id'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        return [
            'search' => trim($validated['search'] ?? ''),
            'status' => $validated['status'] ?? '',
            'stage' => $validated['stage'] ?? '',
            'leave_type_id' => isset($validated['leave_type_id'])
                ? (string) $validated['leave_type_id']
                : '',
            'month' => $validated['month'] ?? now()->format('Y-m'),
        ];
    }

    /**
     * @param  array{search: string, status: string, stage: string, leave_type_id: string, month: string}  $filters
     */
    private function filteredQuery(Request $request, array $filters): Builder
    {
        $matchingEmployeeIds = $this->matchingEmployeeIds($filters['search']);
        $month = Carbon::createFromFormat('Y-m', $filters['month'])->startOfMonth();

        return $this->visibleQuery($request)
            ->when($filters['search'] !== '', function (Builder $query) use (
                $matchingEmployeeIds,
            ) {
                $matchingEmployeeIds === []
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('employee_id', $matchingEmployeeIds);
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
                $filters['leave_type_id'] !== '',
                fn (Builder $query) => $query->where(
                    'system_leave_type_id',
                    (int) $filters['leave_type_id'],
                ),
            )
            ->whereDate('start_date', '<=', $month->copy()->endOfMonth()->toDateString())
            ->whereDate('end_date', '>=', $month->toDateString());
    }

    private function visibleQuery(Request $request): Builder
    {
        $query = EmployeeLeaveRequest::query();

        if (
            $request->user()->hasPermission('leave.manage')
            || $request->user()->hasPermission('leave.approve')
        ) {
            return $query;
        }

        abort_unless($request->user()->hasPermission('leave.supervise'), 403);
        $departmentIds = LeaveApprovalAssignment::query()
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
        EmployeeLeaveRequest $leave,
        array $employeeMap,
    ): array {
        return [
            'id' => $leave->getKey(),
            'employee' => $employeeMap[(string) $leave->employee_id] ?? null,
            'leave_type' => $leave->systemLeaveType?->name
                ?? $leave->leave_type_label,
            'start_date' => $leave->start_date?->toDateString(),
            'end_date' => $leave->end_date?->toDateString(),
            'duration_type' => $leave->duration_type,
            'requested_days' => (float) $leave->requested_days,
            'reason' => $leave->reason,
            'status' => $leave->status,
            'approval_stage' => $leave->approval_stage,
            'submitted_at' => $leave->submitted_at?->toIso8601String(),
            'supervisor_reviewed_at' => $leave->supervisor_reviewed_at?->toIso8601String(),
            'supervisor_review_notes' => $leave->supervisor_review_notes,
            'supervisor_reviewer' => $leave->supervisorReviewer?->name,
            'reviewed_at' => $leave->reviewed_at?->toIso8601String(),
            'review_notes' => $leave->review_notes,
            'reviewer' => $leave->reviewer?->name,
            'has_attachment' => (bool) $leave->attachment_path,
            'attachment_name' => $leave->attachment_original_name,
        ];
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
            ->limit(250)
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

    /**
     * @param  array<int, int|string>  $employeeIds
     * @return array<string, array<string, mixed>>
     */
    private function employeeMap(array $employeeIds): array
    {
        $ids = collect($employeeIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $legacy = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->whereIn('id', $ids)
            ->get(['id', 'employeeID as employee_id', 'nama as name'])
            ->mapWithKeys(fn ($employee) => [
                (string) $employee->id => [
                    'id' => (int) $employee->id,
                    'employee_id' => $employee->employee_id,
                    'name' => $employee->name,
                ],
            ])
            ->all();
        $local = EmployeeRecord::query()
            ->whereIn('directory_id', $ids)
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

    private function csvValue(string $value): string
    {
        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
