<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\GeoAttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->hasOnlyRole(UserRole::Employee)) {
            return redirect()->route('kehadiran.clock');
        }

        $connection = DB::connection('ibco');
        $user = $request->user();
        $statistics = [];

        $modules = [
            'pekerja' => ['employees.view', 'maklumatpekerja'],
            'jawatan' => ['positions.view', 'maklumatjawatan'],
            'cuti' => ['leave.view', 'maklumatcuti'],
            'kerja_lebih_masa' => ['overtime.view', 'maklumatot'],
            'payroll' => ['payroll.view', 'maklumatpayroll'],
            'laporan_bulanan' => ['reports.view', 'reportbulanan'],
        ];

        foreach ($modules as $key => [$permission, $table]) {
            if ($user->hasPermission($permission)) {
                $statistics[$key] = $connection->table($table)
                    ->where('rcd_enable', 1)
                    ->count();
            }
        }

        $recentAttendance = [];

        if ($user->hasPermission('attendance.view')) {
            $statistics['kehadiran'] = GeoAttendanceRecord::query()
                ->where('status', 'active')
                ->count();

            $geoAttendance = GeoAttendanceRecord::query()
                ->with('officeLocation:id,name')
                ->where('status', 'active')
                ->latest('clock_in_at')
                ->limit(5)
                ->get();
            $employees = $connection->table('maklumatpekerja')
                ->whereIn('id', $geoAttendance->pluck('employee_id'))
                ->get(['id', 'employeeID', 'nama'])
                ->keyBy(fn ($employee) => (string) $employee->id);

            $recentAttendance = $geoAttendance->map(function (
                GeoAttendanceRecord $record,
            ) use ($employees) {
                $employee = $employees[(string) $record->employee_id] ?? null;

                return [
                    'id' => $record->getKey(),
                    'employee_id' => $employee?->employeeID,
                    'nama_pekerja' => $employee?->nama,
                    'waktu_masuk' => $record->clock_in_at?->toIso8601String(),
                    'waktu_keluar' => $record->clock_out_at?->toIso8601String(),
                    'catatan' => $record->officeLocation?->name,
                ];
            });
        }

        $recentLeave = $user->hasPermission('leave.view')
            ? $connection->table('maklumatcuti as c')
                ->leftJoin('maklumatpekerja as p', 'c.id_pekerja', '=', 'p.id')
                ->leftJoin('xsenaraicuti as sc', 'c.jenis_cuti', '=', 'sc.id')
                ->leftJoin('xstatuscuti as st', 'c.status_permohonan', '=', 'st.id')
                ->where('c.rcd_enable', 1)
                ->select([
                    'c.id',
                    'p.employeeID as employee_id',
                    'p.nama as nama_pekerja',
                    'sc.description as jenis_cuti',
                    'c.date_mulacuti as tarikh_mula',
                    'c.date_tamatcuti as tarikh_tamat',
                    'st.description as status_permohonan',
                ])
                ->orderByDesc('c.date_mulacuti')
                ->orderByDesc('c.id')
                ->limit(5)
                ->get()
            : [];

        return Inertia::render('dashboard', [
            'statistics' => $statistics,
            'recentAttendance' => $recentAttendance,
            'recentLeave' => $recentLeave,
        ]);
    }
}
