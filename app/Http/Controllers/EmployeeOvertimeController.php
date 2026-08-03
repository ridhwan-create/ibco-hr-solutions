<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOvertimeRequest;
use App\Models\EmployeeUserLink;
use App\Models\GeoAttendanceRecord;
use App\Models\OvertimeApprovalAssignment;
use App\Models\OvertimeNotification;
use App\Models\OvertimeRequest;
use App\Models\OvertimeType;
use App\Support\AuditLogger;
use App\Support\WorkScheduleResolver;
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

class EmployeeOvertimeController extends Controller
{
    public function __construct(
        private readonly WorkScheduleResolver $scheduleResolver,
    ) {}

    public function index(Request $request): Response
    {
        $link = $this->activeLink($request);

        if (! $link) {
            return Inertia::render('EmployeeSelfService/Overtime', [
                'employee' => null,
                'overtimeTypes' => [],
                'summary' => $this->emptySummary(),
                'requests' => [],
                'legacyOvertime' => [],
                'notifications' => [],
                'attendanceEvidence' => [],
            ]);
        }

        $employee = $link->employee_source === 'local' && $link->employeeRecord
            ? (object) [
                'id' => $link->employee_id,
                'employee_id' => $link->employeeRecord->employee_number,
                'name' => $link->employeeRecord->name,
            ]
            : DB::connection('ibco')
                ->table('maklumatpekerja')
                ->where('id', $link->employee_id)
                ->where('rcd_enable', 1)
                ->first(['id', 'employeeID as employee_id', 'nama as name']);
        $requests = OvertimeRequest::query()
            ->where('employee_id', $link->employee_id)
            ->with([
                'overtimeType:id,name,rate_multiplier',
                'supervisorReviewer:id,name',
                'reviewer:id,name',
                'attendanceRecord:id,attendance_date,clock_in_at,clock_out_at,status',
            ])
            ->latest('submitted_at')
            ->limit(30)
            ->get();
        $notifications = OvertimeNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->latest()
            ->limit(10)
            ->get();
        $attendanceEvidence = GeoAttendanceRecord::query()
            ->where('employee_id', $link->employee_id)
            ->whereDate('attendance_date', '>=', now()->subDays(30)->toDateString())
            ->latest('attendance_date')
            ->limit(30)
            ->get(['id', 'attendance_date', 'clock_in_at', 'clock_out_at', 'status'])
            ->map(fn (GeoAttendanceRecord $record) => [
                'id' => $record->getKey(),
                'attendance_date' => $record->attendance_date?->toDateString(),
                'clock_in_at' => $record->clock_in_at?->toIso8601String(),
                'clock_out_at' => $record->clock_out_at?->toIso8601String(),
                'status' => $record->status,
            ]);

        $legacyOvertime = $link->employee_source === 'local'
            ? collect()
            : DB::connection('ibco')
            ->table('maklumatot as ot')
            ->leftJoin('xjenisot as type', 'ot.jenis_ot', '=', 'type.id')
            ->where('ot.id_pekerja', $link->employee_id)
            ->where('ot.rcd_enable', 1)
            ->select([
                'ot.id',
                'type.description as overtime_type',
                'ot.tarikh as work_date',
                'ot.waktu_masuk as start_time',
                'ot.waktu_keluar as end_time',
                'ot.catatan as notes',
            ])
            ->orderByDesc('ot.tarikh')
            ->orderByDesc('ot.id')
            ->limit(10)
            ->get();

        return Inertia::render('EmployeeSelfService/Overtime', [
            'employee' => $employee,
            'overtimeTypes' => OvertimeType::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (OvertimeType $type) => [
                    'id' => $type->getKey(),
                    'name' => $type->name,
                    'rate_multiplier' => (float) $type->rate_multiplier,
                    'minimum_minutes' => $type->minimum_minutes,
                    'maximum_hours' => (float) $type->maximum_hours,
                    'requires_attachment' => $type->requires_attachment,
                ]),
            'summary' => [
                'pending' => $requests->where('status', 'pending')->count(),
                'approved' => $requests->where('status', 'approved')->count(),
                'approved_hours' => round(
                    $requests->where('status', 'approved')->sum('approved_minutes') / 60,
                    2,
                ),
                'unread_notifications' => $notifications->whereNull('read_at')->count(),
            ],
            'requests' => $requests->map(
                fn (OvertimeRequest $overtime) => $this->requestPayload($overtime),
            ),
            'legacyOvertime' => $legacyOvertime,
            'notifications' => $notifications->map(fn (OvertimeNotification $notification) => [
                'id' => $notification->getKey(),
                'title' => $notification->title,
                'message' => $notification->message,
                'read_at' => $notification->read_at?->toIso8601String(),
                'created_at' => $notification->created_at?->toIso8601String(),
            ]),
            'attendanceEvidence' => $attendanceEvidence,
        ]);
    }

    public function store(StoreOvertimeRequest $request): RedirectResponse
    {
        $link = $this->activeLink($request);

        if (! $link) {
            throw ValidationException::withMessages([
                'overtime' => 'Akaun anda belum dipautkan kepada rekod pekerja aktif.',
            ]);
        }

        $employee = $link->employee_source === 'local'
            ? $link->employeeRecord
            : DB::connection('ibco')
                ->table('maklumatpekerja')
                ->where('id', $link->employee_id)
                ->where('rcd_enable', 1)
                ->first(['id']);

        if (! $employee) {
            throw ValidationException::withMessages([
                'overtime' => 'Rekod pekerja asal tidak aktif atau tidak dijumpai.',
            ]);
        }

        $type = OvertimeType::query()
            ->whereKey($request->integer('overtime_type_id'))
            ->where('is_active', true)
            ->first();

        if (! $type) {
            throw ValidationException::withMessages([
                'overtime_type_id' => 'Jenis kerja lebih masa yang dipilih tidak sah.',
            ]);
        }

        if ($type->requires_attachment && ! $request->hasFile('attachment')) {
            throw ValidationException::withMessages([
                'attachment' => "Lampiran wajib untuk {$type->name}.",
            ]);
        }

        $workDate = $request->validated('work_date');
        $start = Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$workDate} {$request->validated('start_time')}",
        );
        $end = Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$workDate} {$request->validated('end_time')}",
        );

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $breakMinutes = (int) ($request->validated('break_minutes') ?? 0);
        $requestedMinutes = (int) $start->diffInMinutes($end) - $breakMinutes;
        $maximumMinutes = (int) round(((float) $type->maximum_hours) * 60);

        if ($requestedMinutes < $type->minimum_minutes) {
            throw ValidationException::withMessages([
                'end_time' => "Tempoh bersih minimum ialah {$type->minimum_minutes} minit.",
            ]);
        }

        if ($requestedMinutes > $maximumMinutes) {
            throw ValidationException::withMessages([
                'end_time' => "Tempoh bersih maksimum untuk {$type->name} ialah {$type->maximum_hours} jam.",
            ]);
        }

        $overlaps = OvertimeRequest::query()
            ->where('employee_id', $link->employee_id)
            ->whereIn('status', ['pending', 'approved'])
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'work_date' => 'Tempoh ini bertindih dengan permohonan OT yang masih aktif.',
            ]);
        }

        $departmentId = $link->employee_source === 'local'
            ? $link->employeeRecord?->department_id
            : DB::connection('ibco')
                ->table('maklumatjawatan')
                ->where('id_pekerja', $link->employee_id)
                ->where('rcd_enable', 1)
                ->orderByDesc('id')
                ->value('id_department');
        $hasSupervisor = $departmentId !== null
            && OvertimeApprovalAssignment::query()
                ->where('department_id', $departmentId)
                ->where('is_active', true)
                ->exists();
        $attendance = GeoAttendanceRecord::query()
            ->where('employee_id', $link->employee_id)
            ->whereDate('attendance_date', $workDate)
            ->first();
        $attendanceMatch = $this->attendanceMatchStatus($attendance, $start, $end);
        $schedule = $this->scheduleResolver->resolve(
            $link->employee_id,
            $workDate,
            $departmentId === null ? null : (int) $departmentId,
            $link->office_location_id,
        );
        $expectedTypeCode = match ($schedule['day_type']) {
            'public_holiday' => 'PUBLIC_HOLIDAY',
            'rest_day', 'off' => 'REST_DAY',
            default => 'WEEKDAY',
        };

        if (
            $type->code !== 'OTHER'
            && $type->code !== $expectedTypeCode
        ) {
            throw ValidationException::withMessages([
                'overtime_type_id' => 'Jenis OT tidak sepadan dengan roster rasmi. Pilih jenis yang dicadangkan untuk hari tersebut.',
            ]);
        }

        $rosterMatch = match ($schedule['day_type']) {
            'public_holiday' => 'public_holiday',
            'rest_day', 'off' => 'rest_day',
            'workday' => $schedule['scheduled_end_at']
                && $start->greaterThanOrEqualTo($schedule['scheduled_end_at'])
                    ? 'after_shift'
                    : 'overlap_shift',
            default => 'not_found',
        };
        $storedAttachment = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $storedAttachment = [
                'attachment_disk' => 'local',
                'attachment_path' => $file->storeAs(
                    "overtime-attachments/{$request->user()->getAuthIdentifier()}",
                    Str::uuid().'.'.$file->getClientOriginalExtension(),
                    'local',
                ),
                'attachment_original_name' => $file->getClientOriginalName(),
                'attachment_mime_type' => $file->getMimeType(),
                'attachment_size' => $file->getSize(),
            ];
        }

        try {
            $overtime = OvertimeRequest::query()->create([
                'user_id' => $request->user()->getAuthIdentifier(),
                'employee_id' => $link->employee_id,
                'department_id' => $departmentId,
                'overtime_type_id' => $type->getKey(),
                'attendance_record_id' => $attendance?->getKey(),
                'roster_entry_id' => $schedule['roster_entry_id'],
                'roster_day_type' => $schedule['day_type'],
                'roster_match_status' => $rosterMatch,
                'work_date' => $workDate,
                'start_at' => $start,
                'end_at' => $end,
                'break_minutes' => $breakMinutes,
                'requested_minutes' => $requestedMinutes,
                'attendance_match_status' => $attendanceMatch,
                'reason' => $request->validated('reason'),
                'work_description' => $request->validated('work_description'),
                'status' => 'pending',
                'approval_stage' => $hasSupervisor ? 'supervisor' : 'hr',
                'submitted_at' => now(),
                ...($storedAttachment ?? []),
            ]);
        } catch (Throwable $exception) {
            if ($storedAttachment) {
                Storage::disk('local')->delete($storedAttachment['attachment_path']);
            }

            throw $exception;
        }

        AuditLogger::record(
            $request,
            'overtime.submitted',
            'overtime_requests',
            $overtime->getKey(),
            newValues: [
                'employee_id' => $overtime->employee_id,
                'overtime_type_id' => $overtime->overtime_type_id,
                'work_date' => $overtime->work_date?->toDateString(),
                'requested_minutes' => $overtime->requested_minutes,
                'approval_stage' => $overtime->approval_stage,
                'attendance_match_status' => $overtime->attendance_match_status,
                'roster_day_type' => $overtime->roster_day_type,
                'roster_match_status' => $overtime->roster_match_status,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $hasSupervisor
                ? 'Permohonan OT dihantar kepada penyelia.'
                : 'Permohonan OT dihantar terus kepada HR.',
        ]);
    }

    public function cancel(
        Request $request,
        OvertimeRequest $overtimeRequest,
    ): RedirectResponse {
        abort_unless(
            $overtimeRequest->user_id === $request->user()->getAuthIdentifier(),
            403,
        );

        if ($overtimeRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Hanya permohonan yang masih menunggu boleh dibatalkan.',
            ]);
        }

        $oldStage = $overtimeRequest->approval_stage;
        $overtimeRequest->update([
            'status' => 'cancelled',
            'approval_stage' => 'completed',
        ]);

        AuditLogger::record(
            $request,
            'overtime.cancelled',
            'overtime_requests',
            $overtimeRequest->getKey(),
            oldValues: ['status' => 'pending', 'approval_stage' => $oldStage],
            newValues: [
                'employee_id' => $overtimeRequest->employee_id,
                'status' => 'cancelled',
                'approval_stage' => 'completed',
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Permohonan OT telah dibatalkan.',
        ]);
    }

    public function downloadAttachment(
        Request $request,
        OvertimeRequest $overtimeRequest,
    ): StreamedResponse {
        $isOwner = $overtimeRequest->user_id
            === $request->user()->getAuthIdentifier();
        $canManage = $request->user()->hasPermission('overtime.manage');
        $canSupervise = $request->user()->hasPermission('overtime.supervise')
            && OvertimeApprovalAssignment::query()
                ->where('department_id', $overtimeRequest->department_id)
                ->where('approver_user_id', $request->user()->getAuthIdentifier())
                ->where('is_active', true)
                ->exists();

        abort_unless($isOwner || $canManage || $canSupervise, 403);
        abort_unless(
            $overtimeRequest->attachment_disk
                && $overtimeRequest->attachment_path
                && Storage::disk($overtimeRequest->attachment_disk)
                    ->exists($overtimeRequest->attachment_path),
            404,
        );

        return Storage::disk($overtimeRequest->attachment_disk)->download(
            $overtimeRequest->attachment_path,
            $overtimeRequest->attachment_original_name ?? 'lampiran-ot',
        );
    }

    public function readNotifications(Request $request): RedirectResponse
    {
        OvertimeNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    private function activeLink(Request $request): ?EmployeeUserLink
    {
        return EmployeeUserLink::query()
            ->with('employeeRecord')
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('is_active', true)
            ->first();
    }

    private function attendanceMatchStatus(
        ?GeoAttendanceRecord $attendance,
        Carbon $start,
        Carbon $end,
    ): string {
        if (! $attendance) {
            return 'not_found';
        }

        if (! $attendance->clock_out_at || $attendance->status === 'cancelled') {
            return 'incomplete';
        }

        return $attendance->clock_in_at->lessThanOrEqualTo($start)
            && $attendance->clock_out_at->greaterThanOrEqualTo($end)
                ? 'matched'
                : 'partial';
    }

    /**
     * @return array<string, mixed>
     */
    private function requestPayload(OvertimeRequest $overtime): array
    {
        return [
            'id' => $overtime->getKey(),
            'overtime_type' => $overtime->overtimeType?->name,
            'rate_multiplier' => (float) ($overtime->overtimeType?->rate_multiplier ?? 0),
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
            'supervisor_reviewer' => $overtime->supervisorReviewer?->name,
            'supervisor_review_notes' => $overtime->supervisor_review_notes,
            'reviewer' => $overtime->reviewer?->name,
            'review_notes' => $overtime->review_notes,
            'has_attachment' => $overtime->attachment_path !== null,
            'attachment_name' => $overtime->attachment_original_name,
        ];
    }

    /**
     * @return array{pending: int, approved: int, approved_hours: float, unread_notifications: int}
     */
    private function emptySummary(): array
    {
        return [
            'pending' => 0,
            'approved' => 0,
            'approved_hours' => 0,
            'unread_notifications' => 0,
        ];
    }
}
