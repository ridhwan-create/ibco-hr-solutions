<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MaklumatKehadiranController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $records = DB::connection('ibco')->table('maklumatkehadiran as k')
            ->leftJoin('maklumatpekerja as p', 'k.id_pekerja', '=', 'p.id')
            ->leftJoin('xpilihanjam as pj', 'k.pilihan_jam', '=', 'pj.id')
            ->where('k.rcd_enable', 1)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('p.nama', 'like', "%{$search}%")
                        ->orWhere('p.employeeID', 'like', "%{$search}%")
                        ->orWhere('pj.description', 'like', "%{$search}%")
                        ->orWhere('k.catatan', 'like', "%{$search}%");
                });
            })
            ->select([
                'k.id',
                'p.employeeID as employee_id',
                'p.nama as nama_pekerja',
                'pj.description as pilihan_jam',
                'k.waktu_masuk',
                'k.waktu_keluar',
                'k.catatan',
            ])
            ->orderByDesc('k.waktu_masuk')
            ->orderByDesc('k.id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('MaklumatKehadiran/Index', [
            'records' => $records,
            'filters' => ['search' => $search],
        ]);
    }
}
