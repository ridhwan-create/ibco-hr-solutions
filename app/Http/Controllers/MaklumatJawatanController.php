<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveMaklumatJawatanRequest;
use App\Models\MaklumatJawatan;
use App\Models\MaklumatPekerja;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MaklumatJawatanController extends Controller
{
    private const EDITABLE_FIELDS = [
        'id_pekerja',
        'date_lapordiri',
        'date_tempohcubaan',
        'id_department',
        'jawatan',
        'salary',
        'id_bank',
        'noakaun',
        'noepf',
        'nosocso',
        'jumlahcuti',
    ];

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'history'])],
            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('ibco.xdepartment', 'id'),
            ],
        ]);

        $search = trim($validated['search'] ?? '');
        $status = $validated['status'] ?? 'active';
        $departmentId = isset($validated['department_id'])
            ? (int) $validated['department_id']
            : null;
        $canViewPayroll = $request->user()->hasPermission('payroll.view');

        $fields = [
            'j.id',
            'j.id_pekerja',
            'p.employeeID as employee_id',
            'p.nama as nama_pekerja',
            'd.description as jabatan',
            'j.jawatan',
            'j.date_lapordiri as tarikh_berkuat_kuasa',
            'j.date_tempohcubaan as tarikh_tamat_tempoh_cubaan',
            'j.jumlahcuti as kelayakan_cuti',
            'j.rcd_enable as aktif',
            'j.mdf_dt as tarikh_tamat',
        ];

        if ($canViewPayroll) {
            array_push(
                $fields,
                'j.salary as gaji_asas',
                'b.description as bank',
            );
        }

        $records = DB::connection('ibco')->table('maklumatjawatan as j')
            ->leftJoin('maklumatpekerja as p', 'j.id_pekerja', '=', 'p.id')
            ->leftJoin('xdepartment as d', 'j.id_department', '=', 'd.id')
            ->leftJoin('xbank as b', 'j.id_bank', '=', 'b.id')
            ->where('j.rcd_enable', $status === 'active' ? 1 : 0)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('p.nama', 'like', "%{$search}%")
                        ->orWhere('p.employeeID', 'like', "%{$search}%")
                        ->orWhere('j.jawatan', 'like', "%{$search}%")
                        ->orWhere('d.description', 'like', "%{$search}%");
                });
            })
            ->when(
                $departmentId !== null,
                fn ($query) => $query->where('j.id_department', $departmentId),
            )
            ->select($fields)
            ->orderByDesc('j.date_lapordiri')
            ->orderByDesc('j.id')
            ->paginate(15)
            ->withQueryString();

        $statistics = [
            'active' => DB::connection('ibco')
                ->table('maklumatjawatan')
                ->where('rcd_enable', 1)
                ->count(),
            'history' => DB::connection('ibco')
                ->table('maklumatjawatan')
                ->where('rcd_enable', 0)
                ->count(),
            'without_position' => DB::connection('ibco')
                ->table('maklumatpekerja as p')
                ->where('p.rcd_enable', 1)
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('maklumatjawatan as j')
                        ->whereColumn('j.id_pekerja', 'p.id')
                        ->where('j.rcd_enable', 1);
                })
                ->count(),
        ];

        return Inertia::render('MaklumatJawatan/Index', [
            'records' => $records,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'department_id' => $departmentId ? (string) $departmentId : '',
            ],
            'departmentOptions' => $this->referenceOptions('xdepartment'),
            'statistics' => $statistics,
            'canManage' => $request->user()->hasPermission('positions.manage'),
            'canViewPayroll' => $canViewPayroll,
        ]);
    }

    public function create(Request $request): Response
    {
        $selectedEmployeeId = $request->integer('employee_id');

        if (
            $selectedEmployeeId > 0
            && ! MaklumatPekerja::query()
                ->whereKey($selectedEmployeeId)
                ->where('rcd_enable', 1)
                ->exists()
        ) {
            $selectedEmployeeId = 0;
        }

        return Inertia::render('MaklumatJawatan/Create', [
            'options' => $this->formOptions(),
            'selectedEmployeeId' => $selectedEmployeeId > 0
                ? (string) $selectedEmployeeId
                : '',
        ]);
    }

    public function store(SaveMaklumatJawatanRequest $request): RedirectResponse
    {
        $payload = $request->safe()->only(self::EDITABLE_FIELDS);
        [$jawatan, $previous] = $this->createPlacement($request, $payload);

        AuditLogger::record(
            $request,
            $previous ? 'position.changed' : 'position.created',
            'maklumatjawatan',
            $jawatan->getKey(),
            oldValues: $previous ? $this->snapshot($previous) : [],
            newValues: $this->snapshot($jawatan),
        );

        return redirect()
            ->route('jawatan.show', $jawatan->getKey())
            ->with('toast', [
                'type' => 'success',
                'message' => $previous
                    ? 'Penempatan baharu berjaya disimpan dan jawatan terdahulu dipindahkan ke sejarah.'
                    : 'Penempatan pekerja berjaya ditambah.',
            ]);
    }

    public function show(Request $request, int $id): Response
    {
        $canViewPayroll = $request->user()->hasPermission('payroll.view');
        $fields = $this->positionFields($canViewPayroll);

        $jawatan = DB::connection('ibco')->table('maklumatjawatan as j')
            ->leftJoin('maklumatpekerja as p', 'j.id_pekerja', '=', 'p.id')
            ->leftJoin('xdepartment as d', 'j.id_department', '=', 'd.id')
            ->leftJoin('xbank as b', 'j.id_bank', '=', 'b.id')
            ->where('j.id', $id)
            ->select($fields)
            ->first();

        abort_if($jawatan === null, 404);

        $history = DB::connection('ibco')->table('maklumatjawatan as j')
            ->leftJoin('maklumatpekerja as p', 'j.id_pekerja', '=', 'p.id')
            ->leftJoin('xdepartment as d', 'j.id_department', '=', 'd.id')
            ->leftJoin('xbank as b', 'j.id_bank', '=', 'b.id')
            ->where('j.id_pekerja', $jawatan->id_pekerja)
            ->select($fields)
            ->orderByDesc('j.rcd_enable')
            ->orderByDesc('j.date_lapordiri')
            ->orderByDesc('j.id')
            ->get();

        return Inertia::render('MaklumatJawatan/Show', [
            'jawatan' => $jawatan,
            'history' => $history,
            'canManage' => $request->user()->hasPermission('positions.manage'),
            'canViewPayroll' => $canViewPayroll,
        ]);
    }

    public function edit(int $id): Response
    {
        $jawatan = $this->findActivePosition($id);

        return Inertia::render('MaklumatJawatan/Edit', [
            'jawatan' => [
                'id' => $jawatan->getKey(),
                ...$jawatan->only(self::EDITABLE_FIELDS),
            ],
            'pekerja' => DB::connection('ibco')
                ->table('maklumatpekerja')
                ->where('id', $jawatan->id_pekerja)
                ->first(['id', 'employeeID as employee_id', 'nama']),
            'options' => [
                'departments' => $this->referenceOptions('xdepartment'),
                'banks' => $this->referenceOptions('xbank'),
            ],
        ]);
    }

    public function update(
        SaveMaklumatJawatanRequest $request,
        int $id,
    ): RedirectResponse {
        $current = $this->findActivePosition($id);
        $payload = $request->safe()->only(self::EDITABLE_FIELDS);
        $payload['id_pekerja'] = $current->id_pekerja;

        $comparison = clone $current;
        $comparison->fill($payload);
        $changedFields = array_keys(
            array_intersect_key(
                $comparison->getDirty(),
                array_flip(self::EDITABLE_FIELDS),
            ),
        );

        if ($changedFields === []) {
            return redirect()
                ->route('jawatan.show', $current->getKey())
                ->with('toast', [
                    'type' => 'info',
                    'message' => 'Tiada perubahan pada maklumat jawatan.',
                ]);
        }

        [$jawatan, $previous] = $this->createPlacement(
            $request,
            $payload,
            $current->getKey(),
        );

        AuditLogger::record(
            $request,
            'position.changed',
            'maklumatjawatan',
            $jawatan->getKey(),
            oldValues: $this->snapshot($previous ?? $current),
            newValues: $this->snapshot($jawatan),
        );

        return redirect()
            ->route('jawatan.show', $jawatan->getKey())
            ->with('toast', [
                'type' => 'success',
                'message' => 'Pertukaran jawatan berjaya disimpan. Rekod terdahulu dikekalkan dalam sejarah.',
            ]);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $jawatan = DB::connection('ibco')->transaction(function () use ($id, $request) {
            $jawatan = MaklumatJawatan::query()
                ->whereKey($id)
                ->where('rcd_enable', 1)
                ->lockForUpdate()
                ->firstOrFail();

            MaklumatPekerja::query()
                ->whereKey($jawatan->id_pekerja)
                ->lockForUpdate()
                ->firstOrFail();

            $jawatan->forceFill([
                'rcd_enable' => 0,
                'mdf_dt' => now()->toDateString(),
                'mdf_by' => $this->actorName($request),
            ])->save();

            return $jawatan;
        });

        AuditLogger::record(
            $request,
            'position.terminated',
            'maklumatjawatan',
            $jawatan->getKey(),
            oldValues: ['rcd_enable' => 1],
            newValues: [
                'rcd_enable' => 0,
                'mdf_dt' => $jawatan->mdf_dt,
            ],
        );

        return redirect()
            ->route('jawatan.index', ['status' => 'history'])
            ->with('toast', [
                'type' => 'success',
                'message' => 'Jawatan aktif telah ditamatkan dan dikekalkan dalam sejarah.',
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{0: MaklumatJawatan, 1: MaklumatJawatan|null}
     */
    private function createPlacement(
        Request $request,
        array $payload,
        ?int $expectedPositionId = null,
    ): array {
        return DB::connection('ibco')->transaction(function () use (
            $request,
            $payload,
            $expectedPositionId,
        ) {
            MaklumatPekerja::query()
                ->whereKey($payload['id_pekerja'])
                ->where('rcd_enable', 1)
                ->lockForUpdate()
                ->firstOrFail();

            $activePositions = MaklumatJawatan::query()
                ->where('id_pekerja', $payload['id_pekerja'])
                ->where('rcd_enable', 1)
                ->lockForUpdate()
                ->orderByDesc('id')
                ->get();

            if (
                $expectedPositionId !== null
                && ! $activePositions->contains(
                    fn (MaklumatJawatan $position) => $position->getKey() === $expectedPositionId,
                )
            ) {
                abort(404);
            }

            /** @var MaklumatJawatan|null $previous */
            $previous = $activePositions->first();

            if ($activePositions->isNotEmpty()) {
                MaklumatJawatan::query()
                    ->whereIn('id', $activePositions->modelKeys())
                    ->update([
                        'rcd_enable' => 0,
                        'mdf_dt' => now()->toDateString(),
                        'mdf_by' => $this->actorName($request),
                    ]);
            }

            $jawatan = MaklumatJawatan::query()->create([
                ...$payload,
                'rcd_enable' => 1,
                'crt_dt' => now()->toDateString(),
                'crt_by' => $this->actorName($request),
                'mdf_dt' => null,
                'mdf_by' => null,
            ]);

            return [$jawatan, $previous];
        });
    }

    private function findActivePosition(int $id): MaklumatJawatan
    {
        return MaklumatJawatan::query()
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
            'employees' => DB::connection('ibco')
                ->table('maklumatpekerja')
                ->where('rcd_enable', 1)
                ->orderBy('nama')
                ->get(['id', 'employeeID', 'nama'])
                ->map(fn ($employee) => [
                    'value' => (string) $employee->id,
                    'label' => trim(
                        ($employee->employeeID ? "{$employee->employeeID} — " : '')
                        . ($employee->nama ?? "Pekerja #{$employee->id}"),
                    ),
                ])
                ->values()
                ->all(),
            'departments' => $this->referenceOptions('xdepartment'),
            'banks' => $this->referenceOptions('xbank'),
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

    /**
     * @return array<int, string>
     */
    private function positionFields(bool $canViewPayroll): array
    {
        $fields = [
            'j.id',
            'j.id_pekerja',
            'p.employeeID as employee_id',
            'p.nama as nama_pekerja',
            'j.jawatan',
            'j.id_department',
            'd.description as jabatan',
            'j.date_lapordiri as tarikh_berkuat_kuasa',
            'j.date_tempohcubaan as tarikh_tamat_tempoh_cubaan',
            'j.jumlahcuti as kelayakan_cuti',
            'j.rcd_enable as aktif',
            'j.crt_dt as tarikh_dicipta',
            'j.crt_by as dicipta_oleh',
            'j.mdf_dt as tarikh_tamat',
            'j.mdf_by as ditamatkan_oleh',
        ];

        if ($canViewPayroll) {
            array_push(
                $fields,
                'j.salary as gaji_asas',
                'j.id_bank',
                'b.description as bank',
                'j.noakaun as no_akaun',
                'j.noepf as no_kwsp',
                'j.nosocso as no_perkeso',
            );
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(MaklumatJawatan $jawatan): array
    {
        return $jawatan->only([...self::EDITABLE_FIELDS, 'rcd_enable']);
    }

    private function actorName(Request $request): string
    {
        return Str::limit($request->user()?->name ?? 'System', 12, '');
    }
}
