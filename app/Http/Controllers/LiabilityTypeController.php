<?php

namespace App\Http\Controllers;

use App\Models\LiabilityType;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class LiabilityTypeController extends Controller
{
    public function index()
    {
        return view('configuration.liability-types.index');
    }

    public function data()
    {
        $data = LiabilityType::query();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('actions', function ($row) {
                return "<button onclick='editLiabilityType($row->id)' class='btn btn-sm btn-warning me-1'>
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
        ]);

        LiabilityType::updateOrCreate(
            ['id' => $request->id],
            $validated
        );

        return response()->json(['success' => 'Liability Type saved successfully.']);
    }

    public function edit(LiabilityType $liabilityType)
    {
        return response()->json($liabilityType);
    }

    public function destroy(LiabilityType $liabilityType)
    {
        $liabilityType->delete();
        return response()->json(['success' => 'Deleted successfully.']);
    }
}
