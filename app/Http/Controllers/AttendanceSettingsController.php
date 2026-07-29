<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\SaveEmployeeUserLinkRequest;
use App\Http\Requests\SaveOfficeLocationRequest;
use App\Models\EmployeeUserLink;
use App\Models\OfficeLocation;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceSettingsController extends Controller
{
    public function index(): Response
    {
        $links = EmployeeUserLink::query()
            ->with([
                'user:id,name,email,role',
                'user.roleAssignments',
                'officeLocation:id,name',
            ])
            ->latest()
            ->get();
        $employeeIds = $links->pluck('employee_id')->unique()->values();
        $employees = $employeeIds->isEmpty()
            ? collect()
            : DB::connection('ibco')
                ->table('maklumatpekerja')
                ->whereIn('id', $employeeIds)
                ->get(['id', 'employeeID', 'nama', 'rcd_enable'])
                ->keyBy(fn ($employee) => (string) $employee->id);

        return Inertia::render('GeoAttendance/Settings', [
            'offices' => OfficeLocation::query()
                ->withCount([
                    'employeeLinks as active_links_count' => fn ($query) => $query->where('is_active', true),
                ])
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get()
                ->map(fn (OfficeLocation $office) => [
                    'id' => $office->getKey(),
                    'name' => $office->name,
                    'address' => $office->address,
                    'latitude' => $office->latitude,
                    'longitude' => $office->longitude,
                    'radius_meters' => $office->radius_meters,
                    'accuracy_limit_meters' => $office->accuracy_limit_meters,
                    'is_active' => $office->is_active,
                    'active_links_count' => $office->active_links_count,
                ]),
            'links' => $links->map(function (EmployeeUserLink $link) use ($employees) {
                $employee = $employees[(string) $link->employee_id] ?? null;

                return [
                    'id' => $link->getKey(),
                    'is_active' => $link->is_active,
                    'user' => $link->user
                        ? [
                            'id' => $link->user->getKey(),
                            'name' => $link->user->name,
                            'email' => $link->user->email,
                            'role' => $link->user->resolvedRole()->value,
                            'roles' => $link->user->roleValues(),
                        ]
                        : null,
                    'employee' => $employee
                        ? [
                            'id' => (int) $employee->id,
                            'employee_id' => $employee->employeeID,
                            'name' => $employee->nama,
                            'is_active' => (bool) $employee->rcd_enable,
                        ]
                        : null,
                    'office' => $link->officeLocation
                        ? [
                            'id' => $link->officeLocation->getKey(),
                            'name' => $link->officeLocation->name,
                        ]
                        : null,
                ];
            }),
            'userOptions' => User::query()
                ->whereHas(
                    'roleAssignments',
                    fn ($query) => $query->where(
                        'role',
                        UserRole::Employee->value,
                    ),
                )
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => [
                    'id' => $user->getKey(),
                    'name' => $user->name,
                    'email' => $user->email,
                ]),
            'employeeOptions' => DB::connection('ibco')
                ->table('maklumatpekerja')
                ->where('rcd_enable', 1)
                ->orderBy('nama')
                ->get(['id', 'employeeID', 'nama'])
                ->map(fn ($employee) => [
                    'id' => (int) $employee->id,
                    'employee_id' => $employee->employeeID,
                    'name' => $employee->nama,
                ]),
        ]);
    }

    public function storeOffice(
        SaveOfficeLocationRequest $request,
    ): RedirectResponse {
        $office = OfficeLocation::query()->create([
            ...$request->validated(),
            'is_active' => true,
            'created_by' => $request->user()->getAuthIdentifier(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'office.created',
            'office_locations',
            $office->getKey(),
            newValues: $this->officeAuditValues($office),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Lokasi pejabat berjaya ditambah.',
        ]);
    }

    public function updateOffice(
        SaveOfficeLocationRequest $request,
        OfficeLocation $office,
    ): RedirectResponse {
        $before = $this->officeAuditValues($office);
        $office->update([
            ...$request->validated(),
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'office.updated',
            'office_locations',
            $office->getKey(),
            oldValues: $before,
            newValues: $this->officeAuditValues($office),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Lokasi pejabat berjaya dikemas kini.',
        ]);
    }

    public function toggleOffice(
        Request $request,
        OfficeLocation $office,
    ): RedirectResponse {
        if (
            $office->is_active
            && $office->employeeLinks()->where('is_active', true)->exists()
        ) {
            throw ValidationException::withMessages([
                'office' => 'Lokasi tidak boleh dinyahaktifkan selagi masih mempunyai pautan pekerja aktif.',
            ]);
        }

        $before = $office->is_active;
        $office->update([
            'is_active' => ! $office->is_active,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            $office->is_active ? 'office.activated' : 'office.deactivated',
            'office_locations',
            $office->getKey(),
            oldValues: ['is_active' => $before],
            newValues: ['is_active' => $office->is_active],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => $office->is_active
                ? 'Lokasi pejabat berjaya diaktifkan.'
                : 'Lokasi pejabat berjaya dinyahaktifkan.',
        ]);
    }

    public function storeLink(
        SaveEmployeeUserLinkRequest $request,
    ): RedirectResponse {
        $validated = $request->validated();
        $employee = DB::connection('ibco')
            ->table('maklumatpekerja')
            ->where('id', $validated['employee_id'])
            ->where('rcd_enable', 1)
            ->first(['id', 'employeeID', 'nama']);

        if (! $employee) {
            throw ValidationException::withMessages([
                'employee_id' => 'Rekod pekerja aktif tidak ditemui dalam db_spp.',
            ]);
        }

        $duplicate = EmployeeUserLink::query()
            ->where('employee_id', $validated['employee_id'])
            ->where('user_id', '!=', $validated['user_id'])
            ->where('is_active', true)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'employee_id' => 'Pekerja ini sudah dipautkan kepada akaun Employee yang lain.',
            ]);
        }

        $link = EmployeeUserLink::query()->firstOrNew([
            'user_id' => $validated['user_id'],
        ]);
        $before = $link->exists ? $this->linkAuditValues($link) : [];

        $link->fill([
            'employee_id' => $validated['employee_id'],
            'office_location_id' => $validated['office_location_id'],
            'is_active' => true,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        if (! $link->exists) {
            $link->created_by = $request->user()->getAuthIdentifier();
        }

        $link->save();

        AuditLogger::record(
            $request,
            $before === [] ? 'employee_link.created' : 'employee_link.updated',
            'employee_user_links',
            $link->getKey(),
            oldValues: $before,
            newValues: $this->linkAuditValues($link),
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Pautan akaun, pekerja dan lokasi berjaya disimpan.',
        ]);
    }

    public function deactivateLink(
        Request $request,
        EmployeeUserLink $link,
    ): RedirectResponse {
        if (! $link->is_active) {
            return back();
        }

        $link->update([
            'is_active' => false,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ]);

        AuditLogger::record(
            $request,
            'employee_link.deactivated',
            'employee_user_links',
            $link->getKey(),
            oldValues: ['is_active' => true],
            newValues: ['is_active' => false],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Pautan pekerja dinyahaktifkan tanpa memadam rekod.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function officeAuditValues(OfficeLocation $office): array
    {
        return [
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
    private function linkAuditValues(EmployeeUserLink $link): array
    {
        return [
            'user_id' => $link->user_id,
            'employee_id' => $link->employee_id,
            'office_location_id' => $link->office_location_id,
            'is_active' => $link->is_active,
        ];
    }
}
