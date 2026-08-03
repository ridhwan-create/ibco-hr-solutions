<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveMaklumatPekerjaRequest;
use App\Models\EmployeeRecord;
use App\Models\MaklumatPekerja;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MaklumatPekerjaController extends Controller
{
    private const EDITABLE_FIELDS = [
        'employeeID',
        'nric',
        'nama',
        'alamat',
        'jantina',
        'tarikhlahir',
        'agama',
        'bangsa',
        'kewarganegaraan',
        'statusperkahwinan',
        'notel',
        'email',
        'status',
    ];

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $legacyRecords = DB::connection('ibco')->table('maklumatpekerja as p')
            ->leftJoin('xstatus as s', 'p.status', '=', 's.id')
            ->where('p.rcd_enable', 1)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('p.nama', 'like', "%{$search}%")
                        ->orWhere('p.employeeID', 'like', "%{$search}%")
                        ->orWhere('p.nric', 'like', "%{$search}%")
                        ->orWhere('p.email', 'like', "%{$search}%");
                });
            })
            ->select([
                'p.id',
                'p.employeeID as employee_id',
                'p.nama',
                'p.nric',
                'p.notel as no_telefon',
                'p.email',
                's.description as status',
            ])
            ->orderBy('p.nama')
            ->get()
            ->map(fn ($employee) => [
                'id' => (int) $employee->id,
                'employee_id' => $employee->employee_id,
                'nama' => $employee->nama,
                'nric' => $employee->nric,
                'no_telefon' => $employee->no_telefon,
                'email' => $employee->email,
                'status' => $employee->status,
                'source' => 'db_spp',
                '_can_edit' => true,
                '_can_delete' => true,
            ]);
        $localRecords = EmployeeRecord::query()
            ->whereIn('status', ['pending_activation', 'active'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhere('identity_number', 'like', "%{$search}%")
                    ->orWhere('official_email', 'like', "%{$search}%");
            }))
            ->get()
            ->map(fn (EmployeeRecord $employee) => [
                'id' => $employee->directory_id,
                'employee_id' => $employee->employee_number,
                'nama' => $employee->name,
                'nric' => $employee->identity_number,
                'no_telefon' => $employee->phone,
                'email' => $employee->official_email,
                'status' => $employee->status === 'active'
                    ? 'Aktif'
                    : 'Menunggu Tarikh Mula',
                'source' => 'IBCO HR Solutions',
                '_can_edit' => false,
                '_can_delete' => false,
            ]);
        $combined = $legacyRecords
            ->concat($localRecords)
            ->sortBy(fn (array $employee) => Str::lower($employee['nama'] ?? ''))
            ->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 15;
        $records = new LengthAwarePaginator(
            $combined->forPage($page, $perPage)->values(),
            $combined->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );

        return Inertia::render('MaklumatPekerja/Index', [
            'records' => $records,
            'filters' => [
                'search' => $search,
            ],
            'canManage' => $request->user()->hasPermission('employees.manage'),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $localEmployee = EmployeeRecord::query()
            ->with(['officeLocation:id,name', 'manager:id,name'])
            ->where('directory_id', $id)
            ->first();

        if ($localEmployee) {
            return $this->showLocalEmployee($request, $localEmployee);
        }

        $connection = DB::connection('ibco');
        $canViewPayroll = $request->user()->hasPermission('payroll.view');

        $pekerja = $connection->table('maklumatpekerja as p')
            ->leftJoin('xjantina as jantina', 'p.jantina', '=', 'jantina.id')
            ->leftJoin('xagama as agama', 'p.agama', '=', 'agama.id')
            ->leftJoin('xbangsa as bangsa', 'p.bangsa', '=', 'bangsa.id')
            ->leftJoin('xstatus as status', 'p.status', '=', 'status.id')
            ->where('p.id', $id)
            ->where('p.rcd_enable', 1)
            ->select([
                'p.id',
                'p.employeeID as employee_id',
                'p.nama',
                'p.nric',
                'p.alamat',
                'p.tarikhlahir as tarikh_lahir',
                'p.kewarganegaraan',
                'p.notel as no_telefon',
                'p.email',
                'jantina.description as jantina',
                'agama.description as agama',
                'bangsa.description as bangsa',
                'p.statusperkahwinan as status_perkahwinan_id',
                'status.description as status',
            ])
            ->first();

        abort_if($pekerja === null, 404);

        $statusPerkahwinan = $pekerja->status_perkahwinan_id
            ? $connection->table('xstatusperkahwinan')
                ->where('id', $pekerja->status_perkahwinan_id)
                ->value('description')
            : null;

        unset($pekerja->status_perkahwinan_id);
        $pekerja->status_perkahwinan = $statusPerkahwinan;

        $jawatanFields = [
            'j.id',
            'j.jawatan',
            'd.description as jabatan',
            'j.date_lapordiri as tarikh_lapor_diri',
            'j.date_tempohcubaan as tarikh_tamat_tempoh_cubaan',
            'j.jumlahcuti as kelayakan_cuti',
        ];

        if ($canViewPayroll) {
            array_push(
                $jawatanFields,
                'j.salary as gaji_asas',
                'b.description as bank',
                'j.noakaun as no_akaun',
                'j.noepf as no_kwsp',
                'j.nosocso as no_perkeso',
            );
        }

        $jawatan = $connection->table('maklumatjawatan as j')
            ->leftJoin('xdepartment as d', 'j.id_department', '=', 'd.id')
            ->leftJoin('xbank as b', 'j.id_bank', '=', 'b.id')
            ->where('j.id_pekerja', $id)
            ->where('j.rcd_enable', 1)
            ->select($jawatanFields)
            ->orderByDesc('j.id')
            ->first();

        $jawatanHistory = $connection->table('maklumatjawatan as j')
            ->leftJoin('xdepartment as d', 'j.id_department', '=', 'd.id')
            ->leftJoin('xbank as b', 'j.id_bank', '=', 'b.id')
            ->where('j.id_pekerja', $id)
            ->select([
                ...$jawatanFields,
                'j.rcd_enable as aktif',
                'j.mdf_dt as tarikh_tamat',
            ])
            ->orderByDesc('j.rcd_enable')
            ->orderByDesc('j.date_lapordiri')
            ->orderByDesc('j.id')
            ->get();

        $statistics = [
            'kehadiran' => $connection->table('maklumatkehadiran')
                ->where('id_pekerja', $id)
                ->where('rcd_enable', 1)
                ->count(),
            'cuti' => $connection->table('maklumatcuti')
                ->where('id_pekerja', $id)
                ->where('rcd_enable', 1)
                ->count(),
            'kerja_lebih_masa' => $connection->table('maklumatot')
                ->where('id_pekerja', $id)
                ->where('rcd_enable', 1)
                ->count(),
        ];

        if ($canViewPayroll) {
            $statistics['payroll'] = $connection->table('maklumatpayroll')
                ->where(function ($query) use ($id, $pekerja) {
                    $query->where('id_pekerja', $id)
                        ->when($pekerja->employee_id, function ($query) use ($pekerja) {
                            $query->orWhere('employeeID', $pekerja->employee_id);
                        });
                })
                ->where('rcd_enable', 1)
                ->count();
        }

        $recentAttendance = $connection->table('maklumatkehadiran as k')
            ->leftJoin('xpilihanjam as pj', 'k.pilihan_jam', '=', 'pj.id')
            ->where('k.id_pekerja', $id)
            ->where('k.rcd_enable', 1)
            ->select([
                'k.id',
                'pj.description as pilihan_jam',
                'k.waktu_masuk',
                'k.waktu_keluar',
                'k.catatan',
            ])
            ->orderByDesc('k.waktu_masuk')
            ->orderByDesc('k.id')
            ->limit(5)
            ->get();

        $recentLeave = $connection->table('maklumatcuti as c')
            ->leftJoin('xsenaraicuti as sc', 'c.jenis_cuti', '=', 'sc.id')
            ->leftJoin('xstatuscuti as st', 'c.status_permohonan', '=', 'st.id')
            ->where('c.id_pekerja', $id)
            ->where('c.rcd_enable', 1)
            ->select([
                'c.id',
                'sc.description as jenis_cuti',
                'c.date_mulacuti as tarikh_mula',
                'c.date_tamatcuti as tarikh_tamat',
                'c.bil_cutidipohon as bilangan_hari',
                'st.description as status_permohonan',
            ])
            ->orderByDesc('c.date_mulacuti')
            ->orderByDesc('c.id')
            ->limit(5)
            ->get();

        $recentOvertime = $connection->table('maklumatot as ot')
            ->leftJoin('xjenisot as jot', 'ot.jenis_ot', '=', 'jot.id')
            ->where('ot.id_pekerja', $id)
            ->where('ot.rcd_enable', 1)
            ->select([
                'ot.id',
                'jot.description as jenis_ot',
                'ot.tarikh',
                'ot.waktu_masuk',
                'ot.waktu_keluar',
                'ot.catatan',
            ])
            ->orderByDesc('ot.tarikh')
            ->orderByDesc('ot.id')
            ->limit(5)
            ->get();

        $recentPayroll = $canViewPayroll
            ? $connection->table('maklumatpayroll as py')
                ->where(function ($query) use ($id, $pekerja) {
                    $query->where('py.id_pekerja', $id)
                        ->when($pekerja->employee_id, function ($query) use ($pekerja) {
                            $query->orWhere('py.employeeID', $pekerja->employee_id);
                        });
                })
                ->where('py.rcd_enable', 1)
                ->select([
                    'py.id',
                    'py.pay_period as tempoh_gaji',
                    'py.bulan',
                    'py.no_kwsp',
                    'py.no_socso',
                    'py.no_akaun',
                ])
                ->orderByDesc('py.pay_period')
                ->orderByDesc('py.id')
                ->limit(5)
                ->get()
            : [];

        return Inertia::render('MaklumatPekerja/Show', [
            'pekerja' => $pekerja,
            'jawatan' => $jawatan,
            'jawatanHistory' => $jawatanHistory,
            'statistics' => $statistics,
            'recentAttendance' => $recentAttendance,
            'recentLeave' => $recentLeave,
            'recentOvertime' => $recentOvertime,
            'recentPayroll' => $recentPayroll,
            'canViewPayroll' => $canViewPayroll,
            'canManage' => $request->user()->hasPermission('employees.manage'),
            'canManagePositions' => $request->user()->hasPermission('positions.manage'),
            'dataSource' => 'db_spp',
        ]);
    }

    private function showLocalEmployee(
        Request $request,
        EmployeeRecord $employee,
    ): Response {
        $employeeId = $employee->directory_id;
        $probationEnd = $employee->probation_months > 0
            ? $employee->start_date?->addMonths($employee->probation_months)->toDateString()
            : null;
        $attendance = DB::table('geo_attendance_records')
            ->where('employee_id', $employeeId)
            ->latest('attendance_date')
            ->latest('clock_in_at')
            ->limit(5)
            ->get();
        $leave = DB::table('employee_leave_requests')
            ->where('employee_id', $employeeId)
            ->latest('start_date')
            ->limit(5)
            ->get();
        $overtime = DB::table('overtime_requests')
            ->where('employee_id', $employeeId)
            ->latest('work_date')
            ->limit(5)
            ->get();

        return Inertia::render('MaklumatPekerja/Show', [
            'pekerja' => [
                'id' => $employee->directory_id,
                'employee_id' => $employee->employee_number,
                'nama' => $employee->name,
                'nric' => $employee->identity_number,
                'alamat' => null,
                'tarikh_lahir' => null,
                'kewarganegaraan' => null,
                'no_telefon' => $employee->phone,
                'email' => $employee->official_email,
                'jantina' => null,
                'agama' => null,
                'bangsa' => null,
                'status_perkahwinan' => null,
                'status' => $employee->status === 'active'
                    ? 'Aktif'
                    : 'Menunggu Tarikh Mula',
            ],
            'jawatan' => [
                'id' => $employee->getKey(),
                'jawatan' => $employee->position_name,
                'jabatan' => $employee->department_id
                    ? 'Jabatan ID '.$employee->department_id
                    : null,
                'tarikh_lapor_diri' => $employee->start_date?->toDateString(),
                'tarikh_tamat_tempoh_cubaan' => $probationEnd,
                'gaji_asas' => $employee->salary,
                'bank' => null,
                'no_akaun' => null,
                'no_kwsp' => null,
                'no_perkeso' => null,
                'kelayakan_cuti' => null,
            ],
            'jawatanHistory' => [],
            'statistics' => [
                'kehadiran' => DB::table('geo_attendance_records')
                    ->where('employee_id', $employeeId)
                    ->where('status', 'active')
                    ->count(),
                'cuti' => DB::table('employee_leave_requests')
                    ->where('employee_id', $employeeId)
                    ->count(),
                'kerja_lebih_masa' => DB::table('overtime_requests')
                    ->where('employee_id', $employeeId)
                    ->count(),
                'payroll' => DB::table('payroll_entries')
                    ->where('employee_id', $employeeId)
                    ->count(),
            ],
            'recentAttendance' => $attendance->map(fn ($record) => [
                'id' => $record->id,
                'pilihan_jam' => null,
                'waktu_masuk' => $record->clock_in_at,
                'waktu_keluar' => $record->clock_out_at,
                'catatan' => $record->notes,
            ]),
            'recentLeave' => $leave->map(fn ($record) => [
                'id' => $record->id,
                'jenis_cuti' => $record->leave_type_label,
                'tarikh_mula' => $record->start_date,
                'tarikh_tamat' => $record->end_date,
                'bilangan_hari' => $record->requested_days,
                'status_permohonan' => $record->status,
            ]),
            'recentOvertime' => $overtime->map(fn ($record) => [
                'id' => $record->id,
                'jenis_ot' => 'Kerja Lebih Masa',
                'tarikh' => $record->work_date,
                'waktu_masuk' => $record->start_at,
                'waktu_keluar' => $record->end_at,
                'catatan' => $record->reason,
            ]),
            'recentPayroll' => [],
            'canViewPayroll' => $request->user()->hasPermission('payroll.view'),
            'canManage' => false,
            'canManagePositions' => false,
            'dataSource' => 'IBCO HR Solutions',
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('MaklumatPekerja/Create', [
            'options' => $this->formOptions(),
        ]);
    }

    public function store(SaveMaklumatPekerjaRequest $request): RedirectResponse
    {
        $payload = $request->safe()->only(self::EDITABLE_FIELDS);
        $payload['rcd_enable'] = 1;
        $payload['crt_dt'] = now()->toDateString();
        $payload['crt_by'] = $this->actorName($request);

        $pekerja = DB::connection('ibco')->transaction(
            fn () => MaklumatPekerja::query()->create($payload),
        );

        AuditLogger::record(
            $request,
            'employee.created',
            'maklumatpekerja',
            $pekerja->getKey(),
            newValues: $pekerja->only([...self::EDITABLE_FIELDS, 'rcd_enable']),
        );

        return redirect()
            ->route('pekerja.show', $pekerja->getKey())
            ->with('toast', [
                'type' => 'success',
                'message' => 'Pekerja berjaya ditambah.',
            ]);
    }

    public function edit(int $id): Response
    {
        $pekerja = $this->findActiveEmployee($id);

        return Inertia::render('MaklumatPekerja/Edit', [
            'pekerja' => [
                'id' => $pekerja->getKey(),
                ...$pekerja->only(self::EDITABLE_FIELDS),
            ],
            'options' => $this->formOptions(),
        ]);
    }

    public function update(SaveMaklumatPekerjaRequest $request, int $id): RedirectResponse
    {
        $pekerja = $this->findActiveEmployee($id);
        $oldValues = $pekerja->only(self::EDITABLE_FIELDS);

        $pekerja->fill($request->safe()->only(self::EDITABLE_FIELDS));
        $changedFields = array_keys(
            array_intersect_key(
                $pekerja->getDirty(),
                array_flip(self::EDITABLE_FIELDS),
            ),
        );

        if ($changedFields === []) {
            return redirect()
                ->route('pekerja.show', $pekerja->getKey())
                ->with('toast', [
                    'type' => 'info',
                    'message' => 'Tiada perubahan pada maklumat pekerja.',
                ]);
        }

        DB::connection('ibco')->transaction(function () use ($pekerja, $request) {
            $pekerja->forceFill([
                'mdf_dt' => now()->toDateString(),
                'mdf_by' => $this->actorName($request),
            ])->save();
        });

        AuditLogger::record(
            $request,
            'employee.updated',
            'maklumatpekerja',
            $pekerja->getKey(),
            oldValues: array_intersect_key($oldValues, array_flip($changedFields)),
            newValues: $pekerja->only($changedFields),
        );

        return redirect()
            ->route('pekerja.show', $pekerja->getKey())
            ->with('toast', [
                'type' => 'success',
                'message' => 'Maklumat pekerja berjaya dikemas kini.',
            ]);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $pekerja = $this->findActiveEmployee($id);

        DB::connection('ibco')->transaction(function () use ($pekerja, $request) {
            $pekerja->forceFill([
                'rcd_enable' => 0,
                'mdf_dt' => now()->toDateString(),
                'mdf_by' => $this->actorName($request),
            ])->save();
        });

        AuditLogger::record(
            $request,
            'employee.deactivated',
            'maklumatpekerja',
            $pekerja->getKey(),
            oldValues: ['rcd_enable' => 1],
            newValues: ['rcd_enable' => 0],
        );

        return redirect()
            ->route('pekerja.index')
            ->with('toast', [
                'type' => 'success',
                'message' => 'Pekerja berjaya dinyahaktifkan.',
            ]);
    }

    private function findActiveEmployee(int $id): MaklumatPekerja
    {
        return MaklumatPekerja::query()
            ->whereKey($id)
            ->where('rcd_enable', 1)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'jantina' => $this->referenceOptions('xjantina'),
            'agama' => $this->referenceOptions('xagama'),
            'bangsa' => $this->referenceOptions('xbangsa'),
            'statusPerkahwinan' => $this->referenceOptions('xstatusperkahwinan'),
            'status' => $this->referenceOptions('xstatus'),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function referenceOptions(string $table): array
    {
        return DB::connection('ibco')
            ->table($table)
            ->where('rcd_enable', 1)
            ->orderBy('description')
            ->get(['id', 'description'])
            ->map(fn ($option) => [
                'value' => (string) $option->id,
                'label' => (string) $option->description,
            ])
            ->values()
            ->all();
    }

    private function actorName(Request $request): string
    {
        return Str::limit($request->user()?->name ?? 'System', 12, '');
    }
}
