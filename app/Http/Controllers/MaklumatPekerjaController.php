<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveMaklumatPekerjaRequest;
use App\Models\MaklumatPekerja;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $records = DB::connection('ibco')->table('maklumatpekerja as p')
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
            ->paginate(15)
            ->withQueryString();

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
