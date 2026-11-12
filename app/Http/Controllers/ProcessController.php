<?php

namespace App\Http\Controllers;

use App\Models\Process;
use App\Models\Bom;
use App\Models\LotTracer;
use Illuminate\Http\Request;

class ProcessController extends Controller
{
    public function index(Request $request)
    {
        $query = Process::query();

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where('process_name', 'like', "%{$s}%");
        }

        $processes = $query->orderBy('process_name')->get();
        return view('processes.index', compact('processes'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'process_name' => strtoupper(trim((string) $request->process_name)),
        ]);

        $request->validate([
            'process_name' => 'required|string|max:255|unique:processes,process_name',
        ], [
            'process_name.unique' => 'Nama proses tersebut sudah terdaftar.',
        ]);

        Process::create([
            'process_name' => $request->process_name,
        ]);

        return redirect()->route('processes.index')->with('success', 'Proses manufaktur berhasil ditambahkan.');
    }

    public function update(Request $request, Process $process)
    {
        $request->merge([
            'process_name' => strtoupper(trim((string) $request->process_name)),
        ]);

        $request->validate([
            'process_name' => 'required|string|max:255|unique:processes,process_name,' . $process->id,
        ]);

        $process->update([
            'process_name' => $request->process_name,
        ]);

        return redirect()->route('processes.index')->with('success', 'Nama proses berhasil diperbarui.');
    }

    public function destroy(Process $process)
    {
        $isUsed = Bom::where('process_id', $process->id)->exists()
            || LotTracer::where('process_id', $process->id)->exists();

        if ($isUsed) {
            return back()->with('error', 'Proses tidak dapat dihapus karena sudah digunakan pada BOM atau Lot Tracer.');
        }

        $process->delete();
        return redirect()->route('processes.index')->with('success', 'Proses berhasil dihapus.');
    }
}