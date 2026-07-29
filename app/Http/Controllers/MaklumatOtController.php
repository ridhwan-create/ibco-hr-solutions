<?php

namespace App\Http\Controllers;

use App\Models\MaklumatOt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MaklumatOtController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->trim()->toString();

        $records = DB::connection('ibco')->table('maklumatot as ot')
            ->leftJoin('maklumatpekerja as p', 'ot.id_pekerja', '=', 'p.id')
            ->leftJoin('xjenisot as jot', 'ot.jenis_ot', '=', 'jot.id')
            ->where('ot.rcd_enable', 1)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('p.nama', 'like', "%{$search}%")
                        ->orWhere('p.employeeID', 'like', "%{$search}%")
                        ->orWhere('jot.description', 'like', "%{$search}%")
                        ->orWhere('ot.catatan', 'like', "%{$search}%");
                });
            })
            ->select([
                'ot.id',
                'p.employeeID as employee_id',
                'p.nama as nama_pekerja',
                'jot.description as jenis_ot',
                'ot.tarikh',
                'ot.waktu_masuk',
                'ot.waktu_keluar',
                'ot.catatan',
            ])
            ->orderByDesc('ot.tarikh')
            ->orderByDesc('ot.id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('MaklumatOt/Index', [
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
    public function show(MaklumatOt $maklumatOt)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MaklumatOt $maklumatOt)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MaklumatOt $maklumatOt)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MaklumatOt $maklumatOt)
    {
        //
    }
}
