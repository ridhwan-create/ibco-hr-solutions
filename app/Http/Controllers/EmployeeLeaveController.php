<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeLeaveRequest;
use App\Models\EmployeeLeaveRequest;
use App\Models\EmployeeUserLink;
use App\Models\LeaveApprovalAssignment;
use App\Models\LeaveNotification;
use App\Models\LeaveType;
use App\Models\PublicHoliday;
use App\Support\AuditLogger;
use App\Support\LeaveBalanceManager;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EmployeeLeaveController extends Controller
{
    public function __construct(
        private readonly LeaveBalanceManager $balances,
    ) {}

    public function index(Request $request): Response
    {
        $link = $this->activeLink($request);

        if (! $link) {
            return Inertia::render('EmployeeSelfService/Leave', [
                'employee' => null,
                'leaveTypes' => [],
                'balances' => [],
                'summary' => $this->emptySummary(),
                'requests' => [],
                'legacyLeave' => [],
                'notifications' => [],
            ]);
        }

        $employee = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('id', $link->employee_id)
            ->where('rcd_enable', 1)
            ->first(['id', 'employeeID as employee_id', 'nama as name']);

        $leaveTypes = LeaveType::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
        $balancePayload = $leaveTypes
            ->map(function (LeaveType $leaveType) use ($link) {
                $summary = $leaveType->deduct_balance
                    ? $this->balances->summary(
                        $link->employee_id,
                        $leaveType,
                        now()->year,
                    )
                    : null;
                $reserved = $leaveType->deduct_balance
                    ? (float) EmployeeLeaveRequest::query()
                        ->where('employee_id', $link->employee_id)
                        ->where('system_leave_type_id', $leaveType->getKey())
                        ->where('status', 'pending')
                        ->whereYear('start_date', now()->year)
                        ->sum('requested_days')
                    : 0.0;

                return [
                    'leave_type_id' => $leaveType->getKey(),
                    'leave_type' => $leaveType->name,
                    'deduct_balance' => $leaveType->deduct_balance,
                    'entitled' => $summary['entitled'] ?? null,
                    'carry_forward' => $summary['carry_forward'] ?? null,
                    'adjustment' => $summary['adjustment'] ?? null,
                    'balance' => $summary['balance'] ?? null,
                    'reserved' => $leaveType->deduct_balance ? $reserved : null,
                    'available' => $leaveType->deduct_balance
                        ? max(0, ($summary['balance'] ?? 0) - $reserved)
                        : null,
                ];
            })
            ->values();

        $leaveEntitlement = DB::connection('ibco')
            ->table('maklumatjawatan')
            ->where('id_pekerja', $link->employee_id)
            ->where('rcd_enable', 1)
            ->orderByDesc('id')
            ->value('jumlahcuti');

        $legacyBalance = DB::connection('ibco')
            ->table('maklumatcuti')
            ->where('id_pekerja', $link->employee_id)
            ->where('rcd_enable', 1)
            ->where('tahun', (string) now()->year)
            ->orderByDesc('id')
            ->value('bakicuti');

        $requests = EmployeeLeaveRequest::query()
            ->where('employee_id', $link->employee_id)
            ->with(['systemLeaveType:id,name', 'supervisorReviewer:id,name', 'reviewer:id,name'])
            ->latest('submitted_at')
            ->limit(30)
            ->get()
            ->map(fn (EmployeeLeaveRequest $leave) => $this->requestPayload($leave));

        $legacyLeave = DB::connection('ibco')
            ->table('maklumatcuti as leave')
            ->leftJoin('xsenaraicuti as type', 'leave.jenis_cuti', '=', 'type.id')
            ->leftJoin('xstatuscuti as status', 'leave.status_permohonan', '=', 'status.id')
            ->where('leave.id_pekerja', $link->employee_id)
            ->where('leave.rcd_enable', 1)
            ->select([
                'leave.id',
                'type.description as leave_type',
                'leave.date_mulacuti as start_date',
                'leave.date_tamatcuti as end_date',
                'leave.bil_cutidipohon as requested_days',
                'leave.bakicuti as balance',
                'status.description as status',
            ])
            ->orderByDesc('leave.date_mulacuti')
            ->orderByDesc('leave.id')
            ->limit(10)
            ->get();

        $notifications = LeaveNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (LeaveNotification $notification) => [
                'id' => $notification->getKey(),
                'title' => $notification->title,
                'message' => $notification->message,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]);

        return Inertia::render('EmployeeSelfService/Leave', [
            'employee' => $employee,
            'leaveTypes' => $leaveTypes->map(fn (LeaveType $leaveType) => [
                'id' => $leaveType->getKey(),
                'label' => $leaveType->name,
                'allow_half_day' => $leaveType->allow_half_day,
                'requires_attachment' => $leaveType->requires_attachment,
                'deduct_balance' => $leaveType->deduct_balance,
            ]),
            'balances' => $balancePayload,
            'summary' => [
                'entitlement' => $leaveEntitlement,
                'legacy_balance' => $legacyBalance,
                'pending' => $requests->where('status', 'pending')->count(),
                'approved' => $requests->where('status', 'approved')->count(),
                'unread_notifications' => $notifications->whereNull('read_at')->count(),
            ],
            'requests' => $requests,
            'legacyLeave' => $legacyLeave,
            'notifications' => $notifications,
        ]);
    }

    public function store(
        StoreEmployeeLeaveRequest $request,
    ): RedirectResponse {
        $link = $this->activeLink($request);

        if (! $link) {
            throw ValidationException::withMessages([
                'leave' => 'Akaun anda belum dipautkan kepada rekod pekerja aktif.',
            ]);
        }

        $employeeExists = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('id', $link->employee_id)
            ->where('rcd_enable', 1)
            ->exists();

        if (! $employeeExists) {
            throw ValidationException::withMessages([
                'leave' => 'Rekod pekerja asal tidak aktif atau tidak dijumpai.',
            ]);
        }

        $leaveType = LeaveType::query()
            ->whereKey($request->integer('leave_type_id'))
            ->where('is_active', true)
            ->first();

        if (! $leaveType) {
            throw ValidationException::withMessages([
                'leave_type_id' => 'Jenis cuti yang dipilih tidak sah.',
            ]);
        }

        if ($leaveType->requires_attachment && ! $request->hasFile('attachment')) {
            throw ValidationException::withMessages([
                'attachment' => "Lampiran wajib untuk {$leaveType->name}.",
            ]);
        }

        $start = Carbon::parse($request->validated('start_date'))->startOfDay();
        $end = Carbon::parse($request->validated('end_date'))->startOfDay();
        $durationType = $request->validated('duration_type') ?? 'full_day';

        if ($start->year !== $end->year) {
            throw ValidationException::withMessages([
                'end_date' => 'Permohonan tidak boleh merentasi dua tahun. Hantar permohonan berasingan.',
            ]);
        }

        if ($start->diffInDays($end) > 90) {
            throw ValidationException::withMessages([
                'end_date' => 'Tempoh permohonan tidak boleh melebihi 90 hari.',
            ]);
        }

        if ($durationType !== 'full_day' && ! $leaveType->allow_half_day) {
            throw ValidationException::withMessages([
                'duration_type' => "{$leaveType->name} tidak membenarkan cuti separuh hari.",
            ]);
        }

        if ($durationType !== 'full_day' && ! $start->isSameDay($end)) {
            throw ValidationException::withMessages([
                'end_date' => 'Cuti separuh hari mesti bermula dan tamat pada tarikh yang sama.',
            ]);
        }

        $requestedDays = $durationType === 'full_day'
            ? $this->workingDays($start, $end)
            : ($this->isWorkingDay($start) ? 0.5 : 0.0);

        if ($requestedDays <= 0) {
            throw ValidationException::withMessages([
                'end_date' => 'Tempoh yang dipilih tidak mempunyai hari bekerja.',
            ]);
        }

        $overlappingRequests = EmployeeLeaveRequest::query()
            ->where('employee_id', $link->employee_id)
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get(['duration_type']);
        $hasOverlap = $overlappingRequests->contains(
            fn (EmployeeLeaveRequest $existing) => $durationType === 'full_day'
                || $existing->duration_type === 'full_day'
                || $existing->duration_type === $durationType,
        );

        if ($hasOverlap) {
            throw ValidationException::withMessages([
                'start_date' => 'Terdapat permohonan cuti aktif yang bertindih dengan tarikh ini.',
            ]);
        }

        if ($leaveType->deduct_balance) {
            $balance = $this->balances->summary(
                $link->employee_id,
                $leaveType,
                $start->year,
            )['balance'];
            $reserved = (float) EmployeeLeaveRequest::query()
                ->where('employee_id', $link->employee_id)
                ->where('system_leave_type_id', $leaveType->getKey())
                ->where('status', 'pending')
                ->whereYear('start_date', $start->year)
                ->sum('requested_days');

            if (($balance - $reserved) < $requestedDays) {
                throw ValidationException::withMessages([
                    'leave_type_id' => sprintf(
                        'Baki %s yang tersedia ialah %.1f hari.',
                        $leaveType->name,
                        max(0, $balance - $reserved),
                    ),
                ]);
            }
        }

        $departmentId = $this->employeeDepartmentId($link->employee_id);
        $assignment = $departmentId
            ? LeaveApprovalAssignment::query()
                ->where('department_id', $departmentId)
                ->where('is_active', true)
                ->first()
            : null;
        $attachment = $request->file('attachment');
        $storedAttachment = null;

        if ($attachment) {
            $storedAttachment = [
                'attachment_disk' => 'local',
                'attachment_path' => $attachment->storeAs(
                    'leave-attachments',
                    Str::uuid()->toString().'.'.$attachment->extension(),
                    'local',
                ),
                'attachment_original_name' => Str::limit(
                    $attachment->getClientOriginalName(),
                    240,
                    '',
                ),
                'attachment_mime_type' => $attachment->getMimeType(),
                'attachment_size' => $attachment->getSize(),
            ];
        }

        try {
            $leave = DB::transaction(function () use (
                $request,
                $link,
                $leaveType,
                $departmentId,
                $assignment,
                $start,
                $end,
                $durationType,
                $requestedDays,
                $storedAttachment,
            ) {
                $leave = EmployeeLeaveRequest::query()->create([
                    'user_id' => $request->user()->getAuthIdentifier(),
                    'employee_id' => $link->employee_id,
                    'department_id' => $departmentId,
                    'leave_type_id' => $leaveType->getKey(),
                    'system_leave_type_id' => $leaveType->getKey(),
                    'leave_type_label' => $leaveType->name,
                    'start_date' => $start,
                    'end_date' => $end,
                    'duration_type' => $durationType,
                    'requested_days' => $requestedDays,
                    'reason' => $request->validated('reason'),
                    'status' => 'pending',
                    'approval_stage' => $assignment ? 'supervisor' : 'hr',
                    'submitted_at' => now(),
                    ...($storedAttachment ?? []),
                ]);

                if ($assignment) {
                    LeaveNotification::query()->create([
                        'user_id' => $assignment->approver_user_id,
                        'leave_request_id' => $leave->getKey(),
                        'title' => 'Permohonan cuti menunggu semakan',
                        'message' => "{$leaveType->name} memerlukan semakan penyelia.",
                    ]);
                }

                return $leave;
            });
        } catch (Throwable $exception) {
            if ($storedAttachment) {
                Storage::disk($storedAttachment['attachment_disk'])
                    ->delete($storedAttachment['attachment_path']);
            }

            throw $exception;
        }

        AuditLogger::record(
            $request,
            'leave.submitted',
            'employee_leave_requests',
            $leave->getKey(),
            newValues: [
                'employee_id' => $leave->employee_id,
                'leave_type' => $leave->leave_type_label,
                'start_date' => $leave->start_date?->toDateString(),
                'end_date' => $leave->end_date?->toDateString(),
                'duration_type' => $leave->duration_type,
                'requested_days' => $leave->requested_days,
                'status' => $leave->status,
                'approval_stage' => $leave->approval_stage,
                'has_attachment' => (bool) $leave->attachment_path,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $assignment
                ? 'Permohonan cuti berjaya dihantar kepada penyelia.'
                : 'Permohonan cuti berjaya dihantar kepada HR.',
        ]);
    }

    public function cancel(
        Request $request,
        EmployeeLeaveRequest $leaveRequest,
    ): RedirectResponse {
        $leaveRequest = DB::transaction(function () use ($request, $leaveRequest) {
            $locked = EmployeeLeaveRequest::query()
                ->lockForUpdate()
                ->findOrFail($leaveRequest->getKey());

            abort_unless(
                (int) $locked->user_id === (int) $request->user()->getAuthIdentifier(),
                403,
            );

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'leave' => 'Hanya permohonan yang masih menunggu boleh dibatalkan.',
                ]);
            }

            $locked->update([
                'status' => 'cancelled',
                'approval_stage' => 'completed',
            ]);

            return $locked;
        });

        AuditLogger::record(
            $request,
            'leave.cancelled',
            'employee_leave_requests',
            $leaveRequest->getKey(),
            oldValues: ['status' => 'pending'],
            newValues: [
                'employee_id' => $leaveRequest->employee_id,
                'status' => 'cancelled',
                'approval_stage' => 'completed',
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Permohonan cuti telah dibatalkan.',
        ]);
    }

    public function downloadAttachment(
        Request $request,
        EmployeeLeaveRequest $leaveRequest,
    ): StreamedResponse {
        abort_unless($leaveRequest->attachment_path, 404);

        $isOwner = (int) $leaveRequest->user_id
            === (int) $request->user()->getAuthIdentifier();
        $canManage = $request->user()->hasPermission('leave.manage');
        $canSupervise = $request->user()->hasPermission('leave.supervise')
            && LeaveApprovalAssignment::query()
                ->where('department_id', $leaveRequest->department_id)
                ->where('approver_user_id', $request->user()->getAuthIdentifier())
                ->where('is_active', true)
                ->exists();

        abort_unless($isOwner || $canManage || $canSupervise, 403);

        return Storage::disk($leaveRequest->attachment_disk ?? 'local')
            ->download(
                $leaveRequest->attachment_path,
                $leaveRequest->attachment_original_name ?? 'lampiran-cuti',
            );
    }

    public function readNotifications(Request $request): RedirectResponse
    {
        LeaveNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(EmployeeLeaveRequest $leave): array
    {
        return [
            'id' => $leave->getKey(),
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
            'supervisor_review_notes' => $leave->supervisor_review_notes,
            'supervisor_reviewer' => $leave->supervisorReviewer?->name,
            'review_notes' => $leave->review_notes,
            'reviewer' => $leave->reviewer?->name,
            'has_attachment' => (bool) $leave->attachment_path,
            'attachment_name' => $leave->attachment_original_name,
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function emptySummary(): array
    {
        return [
            'entitlement' => null,
            'legacy_balance' => null,
            'pending' => 0,
            'approved' => 0,
            'unread_notifications' => 0,
        ];
    }

    private function activeLink(Request $request): ?EmployeeUserLink
    {
        return EmployeeUserLink::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('is_active', true)
            ->first();
    }

    private function employeeDepartmentId(int $employeeId): ?int
    {
        $departmentId = DB::connection('ibco')
            ->table('maklumatjawatan')
            ->where('id_pekerja', $employeeId)
            ->where('rcd_enable', 1)
            ->orderByDesc('id')
            ->value('id_department');

        return $departmentId !== null ? (int) $departmentId : null;
    }

    private function workingDays(Carbon $start, Carbon $end): float
    {
        $days = 0.0;
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            if ($this->isWorkingDay($cursor)) {
                $days++;
            }

            $cursor->addDay();
        }

        return $days;
    }

    private function isWorkingDay(Carbon $date): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return ! PublicHoliday::query()
            ->whereDate('holiday_date', $date->toDateString())
            ->where('is_active', true)
            ->exists();
    }
}
