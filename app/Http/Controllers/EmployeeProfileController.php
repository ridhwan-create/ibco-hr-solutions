<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEmployeePersonalProfileRequest;
use App\Models\EmployeePersonalProfile;
use App\Models\EmployeeUserLink;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $link = $this->activeLink($request);

        if (! $link) {
            return Inertia::render('EmployeeSelfService/Profile', [
                'employee' => null,
                'position' => null,
                'contact' => null,
            ]);
        }

        if ($link->employee_source === 'local' && $link->employeeRecord) {
            return $this->showLocalProfile($request, $link);
        }

        $employee = DB::connection('ibco')
            ->table('maklumatpekerja as p')
            ->leftJoin('xjantina as gender', 'p.jantina', '=', 'gender.id')
            ->leftJoin('xagama as religion', 'p.agama', '=', 'religion.id')
            ->leftJoin('xbangsa as race', 'p.bangsa', '=', 'race.id')
            ->leftJoin('xstatusperkahwinan as marital', 'p.statusperkahwinan', '=', 'marital.id')
            ->leftJoin('xstatus as employment_status', 'p.status', '=', 'employment_status.id')
            ->where('p.id', $link->employee_id)
            ->where('p.rcd_enable', 1)
            ->select([
                'p.id',
                'p.employeeID as employee_id',
                'p.nama as name',
                'p.nric',
                'p.alamat as address',
                'p.notel as phone',
                'p.email',
                'p.tarikhlahir as birth_date',
                'p.kewarganegaraan as nationality',
                'gender.description as gender',
                'religion.description as religion',
                'race.description as race',
                'marital.description as marital_status',
                'employment_status.description as employment_status',
            ])
            ->first();

        $position = DB::connection('ibco')
            ->table('maklumatjawatan as position')
            ->leftJoin('xdepartment as department', 'position.id_department', '=', 'department.id')
            ->where('position.id_pekerja', $link->employee_id)
            ->where('position.rcd_enable', 1)
            ->select([
                'position.jawatan as title',
                'department.description as department',
                'position.date_lapordiri as joined_at',
                'position.jumlahcuti as leave_entitlement',
            ])
            ->orderByDesc('position.id')
            ->first();

        $profile = EmployeePersonalProfile::query()
            ->where('employee_id', $link->employee_id)
            ->first();

        return Inertia::render('EmployeeSelfService/Profile', [
            'employee' => $employee,
            'position' => $position,
            'contact' => $employee
                ? [
                    'address' => $profile?->address ?? $employee->address,
                    'phone' => $profile?->phone ?? $employee->phone,
                    'email' => $profile?->email ?? $employee->email,
                    'is_updated_locally' => $profile !== null,
                    'updated_at' => $profile?->updated_at?->toIso8601String(),
                ]
                : null,
        ]);
    }

    public function update(
        UpdateEmployeePersonalProfileRequest $request,
    ): RedirectResponse {
        $link = $this->activeLink($request);

        if (! $link) {
            throw ValidationException::withMessages([
                'profile' => 'Akaun anda belum dipautkan kepada rekod pekerja aktif.',
            ]);
        }

        $employee = $link->employee_source === 'local' && $link->employeeRecord
            ? (object) [
                'alamat' => null,
                'notel' => $link->employeeRecord->phone,
                'email' => $link->employeeRecord->official_email,
            ]
            : DB::connection('ibco')
                ->table('maklumatpekerja')
                ->where('id', $link->employee_id)
                ->where('rcd_enable', 1)
                ->first(['alamat', 'notel', 'email']);

        if (! $employee) {
            throw ValidationException::withMessages([
                'profile' => 'Rekod pekerja asal tidak aktif atau tidak dijumpai.',
            ]);
        }

        $values = [
            'address' => $request->validated('address'),
            'phone' => $request->validated('phone'),
            'email' => $request->validated('email'),
        ];

        $profile = EmployeePersonalProfile::query()
            ->where('employee_id', $link->employee_id)
            ->first();
        $oldValues = $profile?->only(['address', 'phone', 'email']) ?? [
            'address' => $employee->alamat,
            'phone' => $employee->notel,
            'email' => $employee->email,
        ];

        $profile ??= new EmployeePersonalProfile([
            'user_id' => $request->user()->getAuthIdentifier(),
            'employee_id' => $link->employee_id,
        ]);

        $profile->fill([
            ...$values,
            'user_id' => $request->user()->getAuthIdentifier(),
            'employee_id' => $link->employee_id,
            'updated_by' => $request->user()->getAuthIdentifier(),
        ])->save();

        AuditLogger::record(
            $request,
            'employee.profile_updated',
            'employee_personal_profiles',
            $profile->getKey(),
            oldValues: $oldValues,
            newValues: [
                'employee_id' => $link->employee_id,
                ...$values,
            ],
        );

        return back()->with('toast', [
            'type' => 'success',
            'message' => 'Maklumat hubungan anda berjaya dikemas kini.',
        ]);
    }

    private function activeLink(Request $request): ?EmployeeUserLink
    {
        return EmployeeUserLink::query()
            ->with('employeeRecord')
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->where('is_active', true)
            ->first();
    }

    private function showLocalProfile(
        Request $request,
        EmployeeUserLink $link,
    ): Response {
        $employee = $link->employeeRecord;
        $profile = EmployeePersonalProfile::query()
            ->where('employee_id', $link->employee_id)
            ->first();

        return Inertia::render('EmployeeSelfService/Profile', [
            'employee' => [
                'id' => $employee->directory_id,
                'employee_id' => $employee->employee_number,
                'name' => $employee->name,
                'nric' => $employee->identity_number,
                'address' => null,
                'phone' => $employee->phone,
                'email' => $employee->official_email,
                'birth_date' => null,
                'nationality' => null,
                'gender' => null,
                'religion' => null,
                'race' => null,
                'marital_status' => null,
                'employment_status' => $employee->status === 'active'
                    ? 'Aktif'
                    : 'Menunggu Tarikh Mula',
            ],
            'position' => [
                'title' => $employee->position_name,
                'department' => $employee->department_id
                    ? 'Jabatan ID '.$employee->department_id
                    : null,
                'joined_at' => $employee->start_date?->toDateString(),
                'leave_entitlement' => null,
            ],
            'contact' => [
                'address' => $profile?->address,
                'phone' => $profile?->phone ?? $employee->phone,
                'email' => $profile?->email ?? $employee->official_email,
                'is_updated_locally' => $profile !== null,
                'updated_at' => $profile?->updated_at?->toIso8601String(),
            ],
        ]);
    }
}
