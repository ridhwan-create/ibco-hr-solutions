<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordGeoAttendanceRequest;
use App\Http\Requests\SaveAttendanceAdjustmentRequest;
use App\Http\Requests\StoreManualAttendanceRequest;
use App\Models\AttendanceAdjustment;
use App\Models\EmployeeRecord;
use App\Models\EmployeeUserLink;
use App\Models\GeoAttendanceRecord;
use App\Models\OfficeLocation;
use App\Support\AuditLogger;
use App\Support\Geofence;
use App\Support\WorkScheduleResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GeoAttendanceController extends Controller
{
    public function __construct(
        private readonly WorkScheduleResolver $scheduleResolver,
    ) {}

    public function clockPage(Request $request): Response
    {
        $link = EmployeeUserLink::query()
            ->with(['officeLocation', 'employeeRecord'])
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('is_active', true)
            ->first();

        $employee = $link ? $this->employeeOrFail($link->employee_id) : null;

        $today = now()->toDateString();
        $todayRecord = $link
            ? GeoAttendanceRecord::query()
                ->where('employee_id', $link->employee_id)
                ->where('status', 'active')
                ->where(function (Builder $query) use ($today) {
                    $query->whereDate('attendance_date', $today)
                        ->orWhere(function (Builder $open) use ($today) {
                            $open->whereNull('clock_out_at')
                                ->whereDate(
                                    'attendance_date',
                                    '>=',
                                    Carbon::parse($today)->subDay(),
                                );
                        });
                })
                ->latest('attendance_date')
                ->first()
            : null;

        $history = $link
            ? GeoAttendanceRecord::query()
                ->with('officeLocation:id,name')
                ->where('employee_id', $link->employee_id)
                ->latest('attendance_date')
                ->latest('clock_in_at')
                ->limit(14)
                ->get()
                ->map(fn (GeoAttendanceRecord $record) => $this->recordPayload($record))
            : collect();

        return Inertia::render('GeoAttendance/Clock', [
            'isSecureContextRequired' => ! app()->environment('local'),
            'serverTime' => now()->toIso8601String(),
            'employee' => $employee
                ? [
                    'id' => (int) $employee->id,
                    'employee_id' => $employee->employeeID,
                    'name' => $employee->nama,
                ]
                : null,
            'office' => $link?->officeLocation && $link->officeLocation->is_active
                ? $this->officePayload($link->officeLocation)
                : null,
            'todayRecord' => $todayRecord
                ? $this->recordPayload($todayRecord->load('officeLocation:id,name'))
                : null,
            'history' => $history,
        ]);
    }

    public function clockIn(RecordGeoAttendanceRequest $request): RedirectResponse
    {
        $link = $this->activeEmployeeLink($request);
        $location = $this->validatedLocation($request, $link->officeLocation);
        $now = now();
        $schedule = $this->scheduleResolver->attendanceSnapshot(
            $link->employee_id,
            $now,
            $now,
            officeLocationId: $link->office_location_id,
        );

        $record = DB::transaction(function () use (
            $request,
            $link,
            $location,
            $now,
            $schedule,
        ) {
            $openRecord = GeoAttendanceRecord::query()
                ->where('employee_id', $link->employee_id)
                ->where('status', 'active')
                ->whereNull('clock_out_at')
                ->lockForUpdate()
                ->exists();

            if ($openRecord) {
                throw ValidationException::withMessages([
                    'attendance' => 'Masih terdapat rekod masuk aktif yang belum direkodkan waktu keluar.',
                ]);
            }

            $exists = GeoAttendanceRecord::query()
                ->where('employee_id', $link->employee_id)
                ->whereDate('attendance_date', $now->toDateString())
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'attendance' => 'Kehadiran masuk untuk hari ini telah direkodkan.',
                ]);
            }

            return GeoAttendanceRecord::query()->create([
                'user_id' => $request->user()->getAuthIdentifier(),
                'employee_id' => $link->employee_id,
                'office_location_id' => $link->office_location_id,
                ...$schedule,
                'attendance_date' => $now->toDateString(),
                'clock_in_at' => $now,
                'clock_in_latitude' => $location['latitude'],
                'clock_in_longitude' => $location['longitude'],
                'clock_in_accuracy_meters' => $location['accuracy'],
                'clock_in_distance_meters' => $location['distance'],
                'clock_in_ip' => $request->ip(),
                'clock_in_user_agent' => $request->userAgent(),
                'source' => 'geolocation',
                'status' => 'active',
                'created_by' => $request->user()->getAuthIdentifier(),
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);
        });

        AuditLogger::record(
            $request,
            'attendance.clocked_in',
            'geo_attendance_records',
            $record->getKey(),
            newValues: [
                'employee_id' => $record->employee_id,
                'office_location_id' => $record->office_location_id,
                'clock_in_at' => $record->clock_in_at?->toIso8601String(),
                'distance_meters' => $record->clock_in_distance_meters,
                'accuracy_meters' => $record->clock_in_accuracy_meters,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => sprintf(
                'Kehadiran masuk berjaya direkodkan. Jarak daripada pejabat: %.0f meter.',
                $location['distance'],
            ),
        ]);
    }

    public function clockOut(RecordGeoAttendanceRequest $request): RedirectResponse
    {
        $link = $this->activeEmployeeLink($request);
        $location = $this->validatedLocation($request, $link->officeLocation);
        $now = now();

        $record = DB::transaction(function () use ($request, $link, $location, $now) {
            $record = GeoAttendanceRecord::query()
                ->where('employee_id', $link->employee_id)
                ->where('status', 'active')
                ->whereNull('clock_out_at')
                ->whereDate('attendance_date', '>=', $now->copy()->subDay())
                ->latest('attendance_date')
                ->lockForUpdate()
                ->first();

            if (! $record) {
                throw ValidationException::withMessages([
                    'attendance' => 'Tiada rekod masuk aktif untuk hari ini.',
                ]);
            }

            if ($record->clock_out_at !== null) {
                throw ValidationException::withMessages([
                    'attendance' => 'Kehadiran keluar untuk hari ini telah direkodkan.',
                ]);
            }

            $schedule = $this->scheduleResolver->attendanceSnapshot(
                $record->employee_id,
                $record->attendance_date,
                $record->clock_in_at,
                $now,
                officeLocationId: $record->office_location_id,
            );
            $record->update([
                'clock_out_at' => $now,
                ...$schedule,
                'clock_out_latitude' => $location['latitude'],
                'clock_out_longitude' => $location['longitude'],
                'clock_out_accuracy_meters' => $location['accuracy'],
                'clock_out_distance_meters' => $location['distance'],
                'clock_out_ip' => $request->ip(),
                'clock_out_user_agent' => $request->userAgent(),
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);

            return $record;
        });

        AuditLogger::record(
            $request,
            'attendance.clocked_out',
            'geo_attendance_records',
            $record->getKey(),
            oldValues: ['clock_out_at' => null],
            newValues: [
                'clock_out_at' => $record->clock_out_at?->toIso8601String(),
                'distance_meters' => $record->clock_out_distance_meters,
                'accuracy_meters' => $record->clock_out_accuracy_meters,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => sprintf(
                'Kehadiran keluar berjaya direkodkan. Jarak daripada pejabat: %.0f meter.',
                $location['distance'],
            ),
        ]);
    }

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'office_id' => ['nullable', 'integer', 'exists:office_locations,id'],
            'status' => ['nullable', Rule::in(['active', 'cancelled', 'open', 'completed'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $search = trim($validated['search'] ?? '');
        $officeId = isset($validated['office_id']) ? (int) $validated['office_id'] : null;
        $status = $validated['status'] ?? '';
        $dateFrom = $validated['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $validated['date_to'] ?? now()->toDateString();
        $matchingEmployeeIds = $this->matchingEmployeeIds($search);

        $records = GeoAttendanceRecord::query()
            ->with('officeLocation:id,name')
            ->when($search !== '', function (Builder $query) use ($matchingEmployeeIds) {
                $matchingEmployeeIds === []
                    ? $query->whereRaw('1 = 0')
                    : $query->whereIn('employee_id', $matchingEmployeeIds);
            })
            ->when($officeId !== null, fn (Builder $query) => $query->where('office_location_id', $officeId))
            ->when($status === 'cancelled', fn (Builder $query) => $query->where('status', 'cancelled'))
            ->when($status === 'active', fn (Builder $query) => $query->where('status', 'active'))
            ->when($status === 'open', fn (Builder $query) => $query
                ->where('status', 'active')
                ->whereNull('clock_out_at'))
            ->when($status === 'completed', fn (Builder $query) => $query
                ->where('status', 'active')
                ->whereNotNull('clock_out_at'))
            ->whereBetween('attendance_date', [$dateFrom, $dateTo])
            ->latest('attendance_date')
            ->latest('clock_in_at')
            ->paginate(20)
            ->withQueryString();

        $employeeMap = $this->employeeMap(
            collect($records->items())->pluck('employee_id')->all(),
        );

        $records->through(function (GeoAttendanceRecord $record) use ($employeeMap) {
            return [
                ...$this->recordPayload($record),
                'employee' => $employeeMap[(string) $record->employee_id] ?? null,
            ];
        });

        $today = now()->toDateString();
        $todayQuery = GeoAttendanceRecord::query()->whereDate('attendance_date', $today);
        $canManage = $request->user()->hasPermission('attendance.manage');

        return Inertia::render('GeoAttendance/Index', [
            'canManage' => $canManage,
            'records' => $records,
            'filters' => [
                'search' => $search,
                'office_id' => $officeId ? (string) $officeId : '',
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'offices' => OfficeLocation::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (OfficeLocation $office) => $this->officePayload($office)),
            'employeeOptions' => $canManage ? $this->employeeOptions() : [],
            'statistics' => [
                'today' => (clone $todayQuery)->where('status', 'active')->count(),
                'clocked_out' => (clone $todayQuery)
                    ->where('status', 'active')
                    ->whereNotNull('clock_out_at')
                    ->count(),
                'open' => (clone $todayQuery)
                    ->where('status', 'active')
                    ->whereNull('clock_out_at')
                    ->count(),
                'cancelled' => (clone $todayQuery)->where('status', 'cancelled')->count(),
            ],
        ]);
    }

    public function storeManual(StoreManualAttendanceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $this->employeeOrFail((int) $validated['employee_id']);

        $clockIn = Carbon::parse($validated['clock_in_at']);
        $clockOut = isset($validated['clock_out_at'])
            ? Carbon::parse($validated['clock_out_at'])
            : null;
        $attendanceDate = Carbon::parse($validated['attendance_date'])->toDateString();
        $schedule = $this->scheduleResolver->attendanceSnapshot(
            (int) $validated['employee_id'],
            $attendanceDate,
            $clockIn,
            $clockOut,
            officeLocationId: (int) $validated['office_location_id'],
        );

        if ($clockIn->toDateString() !== $attendanceDate) {
            throw ValidationException::withMessages([
                'clock_in_at' => 'Tarikh waktu masuk mesti sama dengan tarikh kehadiran.',
            ]);
        }

        if (
            GeoAttendanceRecord::query()
                ->where('employee_id', $validated['employee_id'])
                ->whereDate('attendance_date', $attendanceDate)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'employee_id' => 'Pekerja ini sudah mempunyai rekod kehadiran pada tarikh tersebut.',
            ]);
        }

        $record = DB::transaction(function () use (
            $request,
            $validated,
            $clockIn,
            $clockOut,
            $attendanceDate,
            $schedule,
        ) {
            $record = GeoAttendanceRecord::query()->create([
                'employee_id' => (int) $validated['employee_id'],
                'office_location_id' => (int) $validated['office_location_id'],
                ...$schedule,
                'attendance_date' => $attendanceDate,
                'clock_in_at' => $clockIn,
                'clock_out_at' => $clockOut,
                'source' => 'manual',
                'status' => 'active',
                'notes' => $validated['reason'],
                'created_by' => $request->user()->getAuthIdentifier(),
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);

            AttendanceAdjustment::query()->create([
                'geo_attendance_record_id' => $record->getKey(),
                'user_id' => $request->user()->getAuthIdentifier(),
                'employee_id' => $record->employee_id,
                'action' => 'manual_created',
                'reason' => $validated['reason'],
                'after_values' => $this->adjustableValues($record),
            ]);

            return $record;
        });

        AuditLogger::record(
            $request,
            'attendance.manual_created',
            'geo_attendance_records',
            $record->getKey(),
            newValues: [
                ...$this->adjustableValues($record),
                'reason' => $validated['reason'],
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Rekod kehadiran manual berjaya ditambah.',
        ]);
    }

    public function adjust(
        SaveAttendanceAdjustmentRequest $request,
        GeoAttendanceRecord $record,
    ): RedirectResponse {
        $validated = $request->validated();
        $before = $this->adjustableValues($record);
        $clockIn = Carbon::parse($validated['clock_in_at']);
        $clockOut = isset($validated['clock_out_at'])
            ? Carbon::parse($validated['clock_out_at'])
            : null;
        $schedule = $this->scheduleResolver->attendanceSnapshot(
            $record->employee_id,
            $record->attendance_date,
            $clockIn,
            $clockOut,
            officeLocationId: $record->office_location_id,
        );

        if ($clockIn->toDateString() !== $record->attendance_date->toDateString()) {
            throw ValidationException::withMessages([
                'clock_in_at' => 'Tarikh waktu masuk tidak boleh mengubah tarikh rekod kehadiran.',
            ]);
        }

        DB::transaction(function () use (
            $request,
            $record,
            $validated,
            $clockIn,
            $clockOut,
            $before,
            $schedule,
        ) {
            $record->update([
                'clock_in_at' => $clockIn,
                'clock_out_at' => $clockOut,
                ...$schedule,
                'status' => $validated['cancelled'] ? 'cancelled' : 'active',
                'notes' => $validated['reason'],
                'updated_by' => $request->user()->getAuthIdentifier(),
            ]);

            AttendanceAdjustment::query()->create([
                'geo_attendance_record_id' => $record->getKey(),
                'user_id' => $request->user()->getAuthIdentifier(),
                'employee_id' => $record->employee_id,
                'action' => $validated['cancelled'] ? 'cancelled' : 'corrected',
                'reason' => $validated['reason'],
                'before_values' => $before,
                'after_values' => $this->adjustableValues($record),
            ]);
        });

        AuditLogger::record(
            $request,
            $validated['cancelled']
                ? 'attendance.cancelled'
                : 'attendance.corrected',
            'geo_attendance_records',
            $record->getKey(),
            oldValues: $before,
            newValues: [
                ...$this->adjustableValues($record),
                'reason' => $validated['reason'],
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $validated['cancelled']
                ? 'Rekod kehadiran berjaya dibatalkan tanpa dipadam.'
                : 'Rekod kehadiran berjaya dibetulkan.',
        ]);
    }

    private function activeEmployeeLink(Request $request): EmployeeUserLink
    {
        $link = EmployeeUserLink::query()
            ->with(['officeLocation', 'employeeRecord'])
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('is_active', true)
            ->first();

        if (! $link || ! $link->officeLocation?->is_active) {
            throw ValidationException::withMessages([
                'attendance' => 'Akaun belum dipautkan kepada pekerja dan lokasi pejabat aktif.',
            ]);
        }

        $this->employeeOrFail($link->employee_id);

        return $link;
    }

    /**
     * @return array{latitude: float, longitude: float, accuracy: float, distance: float}
     */
    private function validatedLocation(
        RecordGeoAttendanceRequest $request,
        OfficeLocation $office,
    ): array {
        $latitude = (float) $request->validated('latitude');
        $longitude = (float) $request->validated('longitude');
        $accuracy = (float) $request->validated('accuracy');

        if ($accuracy > $office->accuracy_limit_meters) {
            throw ValidationException::withMessages([
                'accuracy' => sprintf(
                    'Bacaan GPS kurang tepat (±%.0f m). Had lokasi ini ialah ±%d m. Sila tunggu dan cuba lagi.',
                    $accuracy,
                    $office->accuracy_limit_meters,
                ),
            ]);
        }

        $distance = Geofence::distanceInMeters(
            $latitude,
            $longitude,
            (float) $office->latitude,
            (float) $office->longitude,
        );

        if ($distance > $office->radius_meters) {
            throw ValidationException::withMessages([
                'location' => sprintf(
                    'Anda berada %.0f meter dari %s. Rakaman hanya dibenarkan dalam radius %d meter.',
                    $distance,
                    $office->name,
                    $office->radius_meters,
                ),
            ]);
        }

        return compact('latitude', 'longitude', 'accuracy', 'distance');
    }

    private function employeeOrFail(int $employeeId): object
    {
        $local = EmployeeRecord::query()
            ->where('directory_id', $employeeId)
            ->whereIn('status', ['pending_activation', 'active'])
            ->first(['directory_id', 'employee_number', 'name']);

        if ($local) {
            return (object) [
                'id' => $local->directory_id,
                'employeeID' => $local->employee_number,
                'nama' => $local->name,
            ];
        }

        $employee = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('id', $employeeId)
            ->where('rcd_enable', 1)
            ->first(['id', 'employeeID', 'nama']);

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee_id' => 'Rekod pekerja aktif tidak ditemui dalam IBCO HR Solutions atau db_spp.',
            ]);
        }

        return $employee;
    }

    /**
     * @return array<int, int>
     */
    private function matchingEmployeeIds(string $search): array
    {
        if ($search === '') {
            return [];
        }

        $legacyIds = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('rcd_enable', 1)
            ->where(function ($query) use ($search) {
                $query->where('nama', 'like', "%{$search}%")
                    ->orWhere('employeeID', 'like', "%{$search}%")
                    ->orWhere('nric', 'like', "%{$search}%");
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $localIds = EmployeeRecord::query()
            ->whereIn('status', ['pending_activation', 'active'])
            ->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhere('identity_number', 'like', "%{$search}%");
            })
            ->pluck('directory_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique([...$legacyIds, ...$localIds]));
    }

    /**
     * @param  array<int, int|string>  $employeeIds
     * @return array<string, array{id: int, employee_id: string|null, name: string|null}>
     */
    private function employeeMap(array $employeeIds): array
    {
        $ids = collect($employeeIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $legacy = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->whereIn('id', $ids)
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
            ->whereIn('directory_id', $ids)
            ->get(['directory_id', 'employee_number', 'name'])
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
     * @return array<int, array{id: int, employee_id: string|null, name: string|null}>
     */
    private function employeeOptions(): array
    {
        $legacy = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('rcd_enable', 1)
            ->orderBy('nama')
            ->get(['id', 'employeeID', 'nama'])
            ->map(fn ($employee) => [
                'id' => (int) $employee->id,
                'employee_id' => $employee->employeeID,
                'name' => $employee->nama,
            ])
            ->all();
        $local = EmployeeRecord::query()
            ->whereIn('status', ['pending_activation', 'active'])
            ->orderBy('name')
            ->get(['directory_id', 'employee_number', 'name'])
            ->map(fn (EmployeeRecord $employee) => [
                'id' => $employee->directory_id,
                'employee_id' => $employee->employee_number,
                'name' => $employee->name,
            ])
            ->all();

        return collect([...$legacy, ...$local])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function recordPayload(GeoAttendanceRecord $record): array
    {
        return [
            'id' => $record->getKey(),
            'attendance_date' => $record->attendance_date?->toDateString(),
            'roster_entry_id' => $record->roster_entry_id,
            'scheduled_start_at' => $record->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $record->scheduled_end_at?->toIso8601String(),
            'scheduled_minutes' => $record->scheduled_minutes,
            'late_minutes' => $record->late_minutes,
            'early_departure_minutes' => $record->early_departure_minutes,
            'attendance_day_type' => $record->attendance_day_type,
            'clock_in_at' => $record->clock_in_at?->toIso8601String(),
            'clock_out_at' => $record->clock_out_at?->toIso8601String(),
            'clock_in_accuracy_meters' => $record->clock_in_accuracy_meters,
            'clock_in_distance_meters' => $record->clock_in_distance_meters,
            'clock_in_latitude' => $record->clock_in_latitude,
            'clock_in_longitude' => $record->clock_in_longitude,
            'clock_in_ip' => $record->clock_in_ip,
            'clock_in_user_agent' => $record->clock_in_user_agent,
            'clock_out_accuracy_meters' => $record->clock_out_accuracy_meters,
            'clock_out_distance_meters' => $record->clock_out_distance_meters,
            'clock_out_latitude' => $record->clock_out_latitude,
            'clock_out_longitude' => $record->clock_out_longitude,
            'clock_out_ip' => $record->clock_out_ip,
            'clock_out_user_agent' => $record->clock_out_user_agent,
            'source' => $record->source,
            'status' => $record->status,
            'notes' => $record->notes,
            'office' => $record->officeLocation
                ? [
                    'id' => $record->officeLocation->getKey(),
                    'name' => $record->officeLocation->name,
                ]
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function officePayload(OfficeLocation $office): array
    {
        return [
            'id' => $office->getKey(),
            'name' => $office->name,
            'address' => $office->address,
            'latitude' => $office->latitude,
            'longitude' => $office->longitude,
            'radius_meters' => $office->radius_meters,
            'accuracy_limit_meters' => $office->accuracy_limit_meters,
            'is_active' => $office->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adjustableValues(GeoAttendanceRecord $record): array
    {
        return [
            'clock_in_at' => $record->clock_in_at?->toIso8601String(),
            'clock_out_at' => $record->clock_out_at?->toIso8601String(),
            'roster_entry_id' => $record->roster_entry_id,
            'scheduled_start_at' => $record->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $record->scheduled_end_at?->toIso8601String(),
            'late_minutes' => $record->late_minutes,
            'early_departure_minutes' => $record->early_departure_minutes,
            'attendance_day_type' => $record->attendance_day_type,
            'status' => $record->status,
        ];
    }
}
