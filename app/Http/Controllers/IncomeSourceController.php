<?php

namespace App\Http\Controllers;

use App\Models\IncomeSource;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class IncomeSourceController extends Controller
{
    public function index()
    {
        return view('configuration.income-sources.index');
    }

    public function data()
    {
        $data = IncomeSource::query();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('actions', function ($row) {
                return "<button onclick='editIncomeSource($row->id)' class='btn btn-sm btn-warning me-1'>
                            <i class='bx bx-edit'></i>
                        </button>
                        <button onclick='confirmDelete($row->id)' class='btn btn-sm btn-danger'>
                            <i class='bx bx-trash'></i>
                        </button>";
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name_en' => 'required|string|max:255',
            'name_fa' => 'nullable|string|max:255',
            'name_ps' => 'nullable|string|max:255',
            'level' => 'required|in:1,2,3',
        ]);

        IncomeSource::updateOrCreate(
            ['id' => $request->id],
            $validated
        );

        return response()->json(['success' => 'Income Source saved successfully.']);
    }

    public function edit(IncomeSource $incomeSource)
    {
        return response()->json($incomeSource);
    }

    public function destroy(IncomeSource $incomeSource)
    {
        $incomeSource->delete();
        return response()->json(['success' => 'Deleted successfully.']);
    }
}

