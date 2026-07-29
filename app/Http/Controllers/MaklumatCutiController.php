<?php

namespace App\Http\Controllers;

use App\Models\MaklumatCuti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MaklumatCutiController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->trim()->toString();

        $records = DB::connection('ibco')->table('maklumatcuti as c')
            ->leftJoin('maklumatpekerja as p', 'c.id_pekerja', '=', 'p.id')
            ->leftJoin('xsenaraicuti as sc', 'c.jenis_cuti', '=', 'sc.id')
            ->leftJoin('xstatuscuti as st', 'c.status_permohonan', '=', 'st.id')
            ->where('c.rcd_enable', 1)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('p.nama', 'like', "%{$search}%")
                        ->orWhere('p.employeeID', 'like', "%{$search}%")
                        ->orWhere('sc.description', 'like', "%{$search}%")
                        ->orWhere('st.description', 'like', "%{$search}%")
                        ->orWhere('c.title', 'like', "%{$search}%")
                        ->orWhere('c.tahun', 'like', "%{$search}%");
                });
            })
            ->select([
                'c.id',
                'p.employeeID as employee_id',
                'p.nama as nama_pekerja',
                'sc.description as jenis_cuti',
                'c.date_mulacuti as tarikh_mula',
                'c.date_tamatcuti as tarikh_tamat',
                'c.bil_cutidipohon as bilangan_hari',
                'st.description as status_permohonan',
            ])
            ->orderByDesc('c.date_mulacuti')
            ->orderByDesc('c.id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('MaklumatCuti/Index', [
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
    public function show(MaklumatCuti $maklumatCuti)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MaklumatCuti $maklumatCuti)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MaklumatCuti $maklumatCuti)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MaklumatCuti $maklumatCuti)
    {
        //
    }
}
