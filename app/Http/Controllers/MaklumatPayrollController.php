<?php

namespace App\Http\Controllers;

use App\Models\MaklumatPayroll;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MaklumatPayrollController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->trim()->toString();

        $records = DB::connection('ibco')->table('maklumatpayroll as py')
            ->where('py.rcd_enable', 1)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('py.nama', 'like', "%{$search}%")
                        ->orWhere('py.employeeID', 'like', "%{$search}%")
                        ->orWhere('py.nric', 'like', "%{$search}%")
                        ->orWhere('py.bulan', 'like', "%{$search}%");
                });
            })
            ->select([
                'py.id',
                'py.employeeID as employee_id',
                'py.nama',
                'py.nric',
                'py.pay_period as tempoh_gaji',
                'py.bulan',
                'py.no_kwsp',
                'py.no_socso',
                'py.no_akaun',
            ])
            ->orderByDesc('py.pay_period')
            ->orderBy('py.nama')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('MaklumatPayroll/Index', [
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
    public function show(MaklumatPayroll $maklumatPayroll)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MaklumatPayroll $maklumatPayroll)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MaklumatPayroll $maklumatPayroll)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MaklumatPayroll $maklumatPayroll)
    {
        //
    }
}
