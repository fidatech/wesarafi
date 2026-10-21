<?php

namespace App\Http\Controllers;

use App\Models\Designation;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class DesignationController extends Controller
{
    public function index()
    {
        $lineManagers = Designation::all();
        return view('configuration.designations.index', compact('lineManagers'));
    }

public function data()
{
    return DataTables::of(Designation::query())
        ->addIndexColumn()
        ->addColumn('line_manager_name', function ($row) {
            return $row->lineManager->name_en ?? '-';
        })
        ->addColumn('actions', function ($row) {
            return view('configuration.designations.action', [
                'id' => $row->id,
                'editRoute' => 'configuration.designations.edit',
                'deleteRoute' => 'configuration.designations.destroy'
            ])->render();
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
        'line_manager' => 'nullable|string|max:255',
    ]);

    Designation::updateOrCreate(
        ['id' => $request->id], // if ID exists = update, else create
        $validated
    );

    return response()->json(['success' => 'Designation saved successfully.']);
}


public function edit(Designation $designation)
{
    return response()->json($designation);
}

public function destroy(Designation $designation)
{
    $designation->delete();
    return response()->json(['success' => 'Designation deleted.']);
}

}
