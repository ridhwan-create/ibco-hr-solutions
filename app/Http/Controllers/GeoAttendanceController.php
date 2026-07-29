<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordGeoAttendanceRequest;
use App\Http\Requests\SaveAttendanceAdjustmentRequest;
use App\Http\Requests\StoreManualAttendanceRequest;
use App\Models\AttendanceAdjustment;
use App\Models\EmployeeUserLink;
use App\Models\GeoAttendanceRecord;
use App\Models\OfficeLocation;
use App\Support\AuditLogger;
use App\Support\Geofence;
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
    public function clockPage(Request $request): Response
    {
        $link = EmployeeUserLink::query()
            ->with('officeLocation')
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('is_active', true)
            ->first();

        $employee = $link
            ? DB::connection('ibco')
                ->table('maklumatpekerja')
                ->where('id', $link->employee_id)
                ->where('rcd_enable', 1)
                ->first(['id', 'employeeID', 'nama'])
            : null;

        $today = now()->toDateString();
        $todayRecord = $link
            ? GeoAttendanceRecord::query()
                ->where('employee_id', $link->employee_id)
                ->whereDate('attendance_date', $today)
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

        $record = DB::transaction(function () use ($request, $link, $location, $now) {
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
                ->whereDate('attendance_date', $now->toDateString())
                ->where('status', 'active')
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

            $record->update([
                'clock_out_at' => $now,
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
        $this->legacyEmployeeOrFail((int) $validated['employee_id']);

        $clockIn = Carbon::parse($validated['clock_in_at']);
        $clockOut = isset($validated['clock_out_at'])
            ? Carbon::parse($validated['clock_out_at'])
            : null;
        $attendanceDate = Carbon::parse($validated['attendance_date'])->toDateString();

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
        ) {
            $record = GeoAttendanceRecord::query()->create([
                'employee_id' => (int) $validated['employee_id'],
                'office_location_id' => (int) $validated['office_location_id'],
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
        ) {
            $record->update([
                'clock_in_at' => $clockIn,
                'clock_out_at' => $clockOut,
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
            ->with('officeLocation')
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('is_active', true)
            ->first();

        if (! $link || ! $link->officeLocation?->is_active) {
            throw ValidationException::withMessages([
                'attendance' => 'Akaun belum dipautkan kepada pekerja dan lokasi pejabat aktif.',
            ]);
        }

        $this->legacyEmployeeOrFail($link->employee_id);

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

    private function legacyEmployeeOrFail(int $employeeId): object
    {
        $employee = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('id', $employeeId)
            ->where('rcd_enable', 1)
            ->first(['id', 'employeeID', 'nama']);

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee_id' => 'Rekod pekerja aktif tidak ditemui dalam db_spp.',
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

        return DB::connection('ibco')
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

        return DB::connection('ibco')
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
    }

    /**
     * @return array<int, array{id: int, employee_id: string|null, name: string|null}>
     */
    private function employeeOptions(): array
    {
        return DB::connection('ibco')
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
    }

    /**
     * @return array<string, mixed>
     */
    private function recordPayload(GeoAttendanceRecord $record): array
    {
        return [
            'id' => $record->getKey(),
            'attendance_date' => $record->attendance_date?->toDateString(),
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
            'status' => $record->status,
        ];
    }
}
