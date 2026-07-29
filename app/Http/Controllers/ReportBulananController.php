<?php

namespace App\Http\Controllers;

use App\Models\ReportBulanan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportBulananController extends Controller
{
    public function index(Request $request)
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
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ReportBulanan $reportBulanan)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ReportBulanan $reportBulanan)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ReportBulanan $reportBulanan)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReportBulanan $reportBulanan)
    {
        //
    }
}
