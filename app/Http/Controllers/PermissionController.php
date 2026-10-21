<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Yajra\DataTables\DataTables;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::orderByRaw('SUBSTRING_INDEX(name, " ", -1) asc')->get();
        return view('configuration.permissions.index', compact('permissions'));
    }

    public function data()
    {
        $query = Permission::select(['id', 'name', 'guard_name']);

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('actions', function ($row) {
                return '
                    <button class="btn btn-sm btn-info me-1" onclick="editPermission(' . $row->id . ')">
                        <i class="bx bx-edit-alt"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="confirmDelete(' . $row->id . ')">
                        <i class="bx bx-trash"></i>
                    </button>';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name',
        ]);

        Permission::create(['name' => $request->name, 'guard_name' => 'web']);

        return response()->json(['success' => 'Permission created successfully.']);
    }


    public function edit($id)
    {
        $permission = Permission::findOrFail($id);
        return response()->json($permission);
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:permissions,name,' . $permission->id,
        ]);

        $permission->update(['name' => $request->name]);

        return response()->json(['success' => 'Permission updated successfully.']);
    }


    public function destroy($id)
    {
        Permission::destroy($id);
        return response()->json(['success' => 'Permission deleted successfully.']);
    }
}
