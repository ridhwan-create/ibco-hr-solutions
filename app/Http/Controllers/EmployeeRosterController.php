<?php

namespace App\Http\Controllers;

use App\Models\EmployeeUserLink;
use App\Models\RosterEntry;
use App\Models\RosterNotification;
use App\Models\RosterPeriod;
use App\Models\ShiftSwapRequest;
use App\Support\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeRosterController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);
        $month = $validated['month'] ?? now()->format('Y-m');
        $periodStart = CarbonImmutable::createFromFormat('Y-m', $month)
            ->startOfMonth();
        $link = EmployeeUserLink::query()
            ->with('officeLocation:id,name')
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('is_active', true)
            ->first();

        if (! $link) {
            return Inertia::render('EmployeeSelfService/Roster', [
                'month' => $month,
                'employee' => null,
                'period' => null,
                'entries' => [],
                'swapOptions' => [],
                'swapRequests' => [],
                'notifications' => [],
                'summary' => $this->emptySummary(),
            ]);
        }

        $employee = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('id', $link->employee_id)
            ->where('rcd_enable', 1)
            ->first(['id', 'employeeID', 'nama']);
        $departmentId = DB::connection('ibco')
            ->table('maklumatjawatan')
            ->where('id_pekerja', $link->employee_id)
            ->where('rcd_enable', 1)
            ->orderByDesc('id')
            ->value('id_department');
        $period = RosterPeriod::query()
            ->whereDate('period_start', $periodStart)
            ->whereIn('status', ['published', 'locked'])
            ->first();
        $entries = $period
            ? RosterEntry::query()
                ->with('shiftTemplate:id,code,name')
                ->where('roster_period_id', $period->getKey())
                ->where('employee_id', $link->employee_id)
                ->orderBy('work_date')
                ->get()
            : collect();
        $swapOptions = $period && $period->status === 'published'
            ? RosterEntry::query()
                ->with(['shiftTemplate:id,name', 'user:id,name'])
                ->where('roster_period_id', $period->getKey())
                ->where('department_id', $departmentId)
                ->where('employee_id', '!=', $link->employee_id)
                ->whereNotNull('user_id')
                ->whereDate('work_date', '>=', today())
                ->where('day_type', 'workday')
                ->orderBy('work_date')
                ->orderBy('scheduled_start_at')
                ->limit(100)
                ->get()
            : collect();
        $swapRequests = ShiftSwapRequest::query()
            ->with([
                'requesterEntry.shiftTemplate:id,name',
                'targetEntry.shiftTemplate:id,name',
                'requester:id,name',
                'target:id,name',
                'reviewer:id,name',
            ])
            ->where(function ($query) use ($request) {
                $query->where(
                    'requester_user_id',
                    $request->user()->getAuthIdentifier(),
                )->orWhere(
                    'target_user_id',
                    $request->user()->getAuthIdentifier(),
                );
            })
            ->latest()
            ->limit(20)
            ->get();
        $notifications = RosterNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('EmployeeSelfService/Roster', [
            'month' => $month,
            'employee' => $employee
                ? [
                    'id' => (int) $employee->id,
                    'employee_id' => $employee->employeeID,
                    'name' => $employee->nama,
                    'office_name' => $link->officeLocation?->name,
                ]
                : null,
            'period' => $period
                ? [
                    'id' => $period->getKey(),
                    'status' => $period->status,
                    'published_at' => $period->published_at?->toIso8601String(),
                    'locked_at' => $period->locked_at?->toIso8601String(),
                ]
                : null,
            'entries' => $entries->map(
                fn (RosterEntry $entry) => $this->entryPayload($entry),
            ),
            'swapOptions' => $swapOptions->map(fn (RosterEntry $entry) => [
                ...$this->entryPayload($entry),
                'employee_name' => $entry->user?->name,
            ]),
            'swapRequests' => $swapRequests->map(
                fn (ShiftSwapRequest $swap) => $this->swapPayload(
                    $swap,
                    $request->user()->getAuthIdentifier(),
                ),
            ),
            'notifications' => $notifications->map(
                fn (RosterNotification $notification) => [
                    'id' => $notification->getKey(),
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                ],
            ),
            'summary' => [
                'workdays' => $entries->where('day_type', 'workday')->count(),
                'rest_days' => $entries->whereIn(
                    'day_type',
                    ['rest_day', 'off'],
                )->count(),
                'public_holidays' => $entries
                    ->where('day_type', 'public_holiday')
                    ->count(),
                'pending_swaps' => $swapRequests
                    ->where('status', 'pending')
                    ->count(),
                'unread_notifications' => $notifications
                    ->whereNull('read_at')
                    ->count(),
            ],
        ]);
    }

    public function storeSwap(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'requester_roster_entry_id' => [
                'required',
                'integer',
                'exists:roster_entries,id',
            ],
            'target_roster_entry_id' => [
                'required',
                'integer',
                'different:requester_roster_entry_id',
                'exists:roster_entries,id',
            ],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $requesterEntry = RosterEntry::query()
            ->with('period')
            ->findOrFail($validated['requester_roster_entry_id']);
        $targetEntry = RosterEntry::query()
            ->with('period')
            ->findOrFail($validated['target_roster_entry_id']);

        abort_unless(
            $requesterEntry->user_id === $request->user()->getAuthIdentifier(),
            403,
        );

        if (
            $requesterEntry->period?->status !== 'published'
            || $targetEntry->period?->status !== 'published'
            || $requesterEntry->roster_period_id
                !== $targetEntry->roster_period_id
            || $targetEntry->user_id === null
            || $targetEntry->user_id === $request->user()->getAuthIdentifier()
            || $requesterEntry->department_id !== $targetEntry->department_id
            || $requesterEntry->day_type !== 'workday'
            || $targetEntry->day_type !== 'workday'
            || $requesterEntry->scheduled_start_at === null
            || $targetEntry->scheduled_start_at === null
            || $requesterEntry->work_date->isBefore(today())
            || $targetEntry->work_date->isBefore(today())
        ) {
            throw ValidationException::withMessages([
                'target_roster_entry_id' => 'Pasangan pertukaran syif tidak sah atau roster telah dikunci.',
            ]);
        }

        $duplicate = ShiftSwapRequest::query()
            ->where('status', 'pending')
            ->where(function ($query) use ($requesterEntry, $targetEntry) {
                $query->whereIn('requester_roster_entry_id', [
                    $requesterEntry->getKey(),
                    $targetEntry->getKey(),
                ])->orWhereIn('target_roster_entry_id', [
                    $requesterEntry->getKey(),
                    $targetEntry->getKey(),
                ]);
            })
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'target_roster_entry_id' => 'Salah satu jadual sudah mempunyai pertukaran yang masih menunggu.',
            ]);
        }

        $swap = ShiftSwapRequest::query()->create([
            'requester_roster_entry_id' => $requesterEntry->getKey(),
            'target_roster_entry_id' => $targetEntry->getKey(),
            'requester_user_id' => $request->user()->getAuthIdentifier(),
            'target_user_id' => $targetEntry->user_id,
            'department_id' => $requesterEntry->department_id,
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);
        RosterNotification::query()->create([
            'user_id' => $targetEntry->user_id,
            'shift_swap_request_id' => $swap->getKey(),
            'title' => 'Permohonan pertukaran syif',
            'message' => $request->user()->name
                .' memohon pertukaran syif dan sedang menunggu kelulusan penyelia.',
        ]);

        AuditLogger::record(
            $request,
            'roster.swap_requested',
            'shift_swap_requests',
            $swap->getKey(),
            newValues: [
                'requester_roster_entry_id' => $requesterEntry->getKey(),
                'target_roster_entry_id' => $targetEntry->getKey(),
                'department_id' => $requesterEntry->department_id,
                'status' => 'pending',
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Permohonan pertukaran syif dihantar kepada penyelia.',
        ]);
    }

    public function cancelSwap(
        Request $request,
        ShiftSwapRequest $shiftSwapRequest,
    ): RedirectResponse {
        abort_unless(
            $shiftSwapRequest->requester_user_id
                === $request->user()->getAuthIdentifier(),
            403,
        );

        if ($shiftSwapRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'status' => 'Hanya pertukaran yang masih menunggu boleh dibatalkan.',
            ]);
        }

        $shiftSwapRequest->update(['status' => 'cancelled']);

        AuditLogger::record(
            $request,
            'roster.swap_cancelled',
            'shift_swap_requests',
            $shiftSwapRequest->getKey(),
            oldValues: ['status' => 'pending'],
            newValues: ['status' => 'cancelled'],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Permohonan pertukaran syif dibatalkan.',
        ]);
    }

    public function readNotifications(Request $request): RedirectResponse
    {
        RosterNotification::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function entryPayload(RosterEntry $entry): array
    {
        return [
            'id' => $entry->getKey(),
            'work_date' => $entry->work_date->toDateString(),
            'day_type' => $entry->day_type,
            'shift_name' => $entry->shiftTemplate?->name,
            'shift_code' => $entry->shiftTemplate?->code,
            'scheduled_start_at' => $entry->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $entry->scheduled_end_at?->toIso8601String(),
            'break_minutes' => $entry->break_minutes,
            'notes' => $entry->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function swapPayload(
        ShiftSwapRequest $swap,
        int|string $currentUserId,
    ): array {
        return [
            'id' => $swap->getKey(),
            'status' => $swap->status,
            'reason' => $swap->reason,
            'review_notes' => $swap->review_notes,
            'reviewer' => $swap->reviewer?->name,
            'is_requester' => $swap->requester_user_id === (int) $currentUserId,
            'requester_name' => $swap->requester?->name,
            'target_name' => $swap->target?->name,
            'requester_entry' => $swap->requesterEntry
                ? $this->entryPayload($swap->requesterEntry)
                : null,
            'target_entry' => $swap->targetEntry
                ? $this->entryPayload($swap->targetEntry)
                : null,
            'created_at' => $swap->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function emptySummary(): array
    {
        return [
            'workdays' => 0,
            'rest_days' => 0,
            'public_holidays' => 0,
            'pending_swaps' => 0,
            'unread_notifications' => 0,
        ];
    }
}
