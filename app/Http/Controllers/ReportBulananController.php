<?php

namespace App\Http\Controllers;

use App\Support\AuditLogger;
use App\Support\MonthlyHrReportBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportBulananController extends Controller
{
    public function __construct(
        private readonly MonthlyHrReportBuilder $reportBuilder,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->validatedFilters($request);
        $report = $this->reportBuilder->build(
            $filters['period'],
            $filters['department_id'],
            $filters['office_location_id'],
        );

        return Inertia::render('MonthlyReports/Index', [
            'report' => $report,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $report = $this->reportBuilder->build(
            $filters['period'],
            $filters['department_id'],
            $filters['office_location_id'],
        );
        AuditLogger::record(
            $request,
            'report.monthly_exported',
            'monthly_hr_report',
            $filters['period'],
            newValues: [
                'period' => $filters['period'],
                'department_id' => $filters['department_id'],
                'office_location_id' => $filters['office_location_id'],
            ],
        );

        return response()->streamDownload(
            function () use ($report) {
                $stream = fopen('php://output', 'w');

                if ($stream === false) {
                    return;
                }

                fwrite($stream, "\xEF\xBB\xBF");
                fputcsv($stream, ['Laporan HR Bulanan', $report['period_label']]);
                fputcsv($stream, ['Dijana pada', $report['generated_at']]);
                fputcsv($stream, []);
                fputcsv($stream, ['RINGKASAN']);
                fputcsv($stream, ['Metrik', 'Nilai']);

                $summary = $report['summary'];
                $summaryRows = [
                    ['Pekerja aktif', $summary['active_employees']],
                    ['Hari bekerja', $summary['working_days']],
                    ['Rekod kehadiran', $summary['attendance_days']],
                    ['Kadar kehadiran (%)', $summary['attendance_rate']],
                    ['Purata jam bekerja', $summary['average_work_hours']],
                    ['Rekod keluar tidak lengkap', $summary['incomplete_clock_out']],
                    ['Permohonan cuti diluluskan', $summary['leave_requests']],
                    ['Hari cuti diluluskan', $summary['leave_days']],
                    ['Permohonan OT diluluskan', $summary['overtime_requests']],
                    ['Jam OT diluluskan', $summary['overtime_hours']],
                    ['Kelulusan menunggu', $summary['pending_actions']],
                    ['Status payroll', $summary['payroll']['status']],
                    ['Gaji kasar', $summary['payroll']['gross_pay']],
                    ['Jumlah potongan', $summary['payroll']['deductions']],
                    ['Gaji bersih', $summary['payroll']['net_pay']],
                    [
                        'Caruman majikan',
                        $summary['payroll']['employer_contributions'],
                    ],
                ];

                foreach ($summaryRows as $row) {
                    fputcsv($stream, $row);
                }

                fputcsv($stream, []);
                fputcsv($stream, ['PECAHAN JABATAN']);
                fputcsv($stream, [
                    'Jabatan',
                    'Pekerja',
                    'Hari Kehadiran',
                    'Hari Cuti',
                    'Jam OT',
                    'Gaji Bersih',
                ]);

                foreach ($report['departments'] as $department) {
                    fputcsv($stream, [
                        $department['name'],
                        $department['employee_count'],
                        $department['attendance_days'],
                        $department['leave_days'],
                        $department['overtime_hours'],
                        $department['net_pay'],
                    ]);
                }

                fputcsv($stream, []);
                fputcsv($stream, ['TREND ENAM BULAN']);
                fputcsv($stream, [
                    'Bulan',
                    'Kadar Kehadiran (%)',
                    'Hari Cuti',
                    'Jam OT',
                    'Gaji Bersih',
                ]);

                foreach ($report['trend'] as $month) {
                    fputcsv($stream, [
                        $month['period'],
                        $month['attendance_rate'],
                        $month['leave_days'],
                        $month['overtime_hours'],
                        $month['net_pay'],
                    ]);
                }

                fclose($stream);
            },
            "laporan-hr-bulanan-{$filters['period']}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function legacy(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $records = DB::connection('ibco')->table('reportbulanan as r')
            ->leftJoin('maklumatpekerja as p', 'r.id_pekerja', '=', 'p.id')
            ->where('r.rcd_enable', 1)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('p.nama', 'like', "%{$search}%")
                        ->orWhere('p.employeeID', 'like', "%{$search}%")
                        ->orWhere('r.laporan', 'like', "%{$search}%");
                });
            })
            ->select([
                'r.id',
                'p.employeeID as employee_id',
                'p.nama as nama_pekerja',
                'r.date_mula as tarikh_mula',
                'r.date_akhir as tarikh_akhir',
                'r.laporan',
            ])
            ->orderByDesc('r.date_mula')
            ->orderByDesc('r.id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('ReportBulanan/Index', [
            'records' => $records,
            'filters' => ['search' => $search],
        ]);
    }

    /**
     * @return array{
     *     period: string,
     *     department_id: ?int,
     *     office_location_id: ?int
     * }
     */
    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'date_format:Y-m'],
            'department_id' => ['nullable', 'integer', 'min:1'],
            'office_location_id' => [
                'nullable',
                'integer',
                'exists:office_locations,id',
            ],
        ]);
        $departmentId = isset($validated['department_id'])
            ? (int) $validated['department_id']
            : null;

        if (
            $departmentId !== null
            && ! DB::connection('ibco')
                ->table('xdepartment')
                ->where('id', $departmentId)
                ->where('rcd_enable', 1)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'department_id' => 'Jabatan yang dipilih tidak sah.',
            ]);
        }

        return [
            'period' => $validated['period'] ?? now()->format('Y-m'),
            'department_id' => $departmentId,
            'office_location_id' => isset($validated['office_location_id'])
                ? (int) $validated['office_location_id']
                : null,
        ];
    }
}
