<?php

namespace App\Http\Controllers;

use App\Models\MaklumatPekerja;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MaklumatPekerjaController extends Controller
{
    public function index(Request $request)
    {
        $query = MaklumatPekerja::where('rcd_enable', 1);

        // Fungsi carian 
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('employeeID', 'like', "%{$search}%")
                    ->orWhere('nric', 'like', "%{$search}%");
            });
        }

        $pekerja = $query->latest('id')->paginate(10)->withQueryString();

        return Inertia::render('MaklumatPekerja/Index', [
            'pekerja' => $pekerja,
            'filters' => [
                'search' => $request->search ?? ''
            ]
        ]);
    }

    public function create()
    {
        return Inertia::render('MaklumatPekerja/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nric' => 'required|string|max:255',
            'employeeID' => 'required|string|max:15',
            'nama' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'notel' => 'nullable|string|max:20',
            // tambah validasi lain mengikut keperluan
        ]);

        $validated['crt_dt'] = now();
        $validated['crt_by'] = auth()->user()->name ?? 'System';

        MaklumatPekerja::create($validated);

        return redirect()->route('pekerja.index')->with('toast', ['type' => 'success', 'message' => 'Pekerja berjaya ditambah!']);
    }

    public function edit($id)
    {
        $pekerja = MaklumatPekerja::findOrFail($id);
        return Inertia::render('MaklumatPekerja/Edit', [
            'pekerja' => $pekerja
        ]);
    }

    public function update(Request $request, $id)
    {
        $pekerja = MaklumatPekerja::findOrFail($id);

        $validated = $request->validate([
            'nric' => 'required|string|max:255',
            'employeeID' => 'required|string|max:15',
            'nama' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'notel' => 'nullable|string|max:20',
        ]);

        $validated['mdf_dt'] = now();
        $validated['mdf_by'] = auth()->user()->name ?? 'System';

        $pekerja->update($validated);

        return redirect()->route('pekerja.index')->with('toast', ['type' => 'success', 'message' => 'Pekerja berjaya dikemaskini!']);
    }

    public function destroy($id)
    {
        $pekerja = MaklumatPekerja::findOrFail($id);

        // Soft delete cara manual
        $pekerja->update(['rcd_enable' => 0]);

        return redirect()->route('pekerja.index')->with('toast', ['type' => 'success', 'message' => 'Pekerja berjaya dipadam!']);
    }
}
