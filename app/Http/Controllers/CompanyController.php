<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Item;
use App\Models\ProblemDelay;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $query = Company::query();

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('company_code', 'like', "%{$s}%")
                  ->orWhere('company_name', 'like', "%{$s}%");
            });
        }

        if ($request->filled('type_company')) {
            $query->where('type_company', $request->type_company);
        }

        $companies = $query->orderBy('company_code')->get();
        return view('companies.index', compact('companies'));
    }

    public function store(Request $request)
    {
        $request->merge([
            'company_code' => strtoupper(trim((string) $request->company_code)),
            'company_name' => trim((string) $request->company_name),
            'type_company' => strtoupper(trim((string) $request->type_company)),
        ]);

        $request->validate([
            'company_code' => 'required|string|max:50|unique:companies,company_code',
            'company_name' => 'required|string|max:255',
            'type_company' => 'required|in:SUPPLIER,INTERNAL,CUSTOMER',
        ]);

        Company::create($request->only(['company_code', 'company_name', 'type_company']));

        return redirect()->route('companies.index')->with('success', 'Perusahaan baru berhasil ditambahkan.');
    }

    public function update(Request $request, Company $company)
    {
        $request->merge([
            'company_code' => strtoupper(trim((string) $request->company_code)),
            'company_name' => trim((string) $request->company_name),
            'type_company' => strtoupper(trim((string) $request->type_company)),
        ]);

        $request->validate([
            'company_code' => 'required|string|max:50|unique:companies,company_code,' . $company->id,
            'company_name' => 'required|string|max:255',
            'type_company' => 'required|in:SUPPLIER,INTERNAL,CUSTOMER',
        ]);

        $company->update($request->only(['company_code', 'company_name', 'type_company']));

        return redirect()->route('companies.index')->with('success', 'Data perusahaan berhasil diperbarui.');
    }

    public function destroy(Company $company)
    {
        $isUsed = Item::withTrashed()->where('company_id', $company->id)->exists()
            || ProblemDelay::where('company_id', $company->id)->exists();

        if ($isUsed) {
            return back()->with('error', 'Perusahaan tidak dapat dihapus karena sudah terhubung dengan Master Item atau Log Delay.');
        }

        $company->delete();
        return redirect()->route('companies.index')->with('success', 'Perusahaan berhasil dihapus.');
    }
}