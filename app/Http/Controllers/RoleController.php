<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function index()
    {
        $permissions = Permission::all();
        return view('configuration.roles.index', compact('permissions'));
    }

    public function data(Request $request)
    {
        $roles = Role::with('permissions:id,name')->select(['id', 'name']);

        return DataTables::of($roles)
            ->addIndexColumn()
            ->addColumn('permissions', function ($role) {
                $grouped = [];

                foreach ($role->permissions as $permission) {
                    $parts = explode(' ', strtolower($permission->name));
                    $action = $parts[0];
                    $entity = strtoupper(end($parts));
                    $grouped[$entity][] = ucfirst($action);
                }

                $order = ['View', 'Create', 'Edit', 'Delete', 'Approve', 'Reject'];
                $badgeIcons = [
                    'View' => '<i class="bx bx-show me-1"></i>',
                    'Create' => '<i class="bx bx-plus-circle me-1"></i>',
                    'Edit' => '<i class="bx bx-edit-alt me-1"></i>',
                    'Delete' => '<i class="bx bx-trash me-1"></i>',
                    'Approve' => '<i class="bx bx-check-circle me-1"></i>',
                    'Reject' => '<i class="bx bx-block me-1"></i>',
                ];

                $badgeColors = [
                    'View' => 'primary',
                    'Create' => 'success',
                    'Edit' => 'warning',
                    'Delete' => 'danger',
                    'Approve' => 'info',
                    'Reject' => 'secondary',
                ];


                $html = '<div class="row g-3" style="max-width: 100%;">';
                $count = 0;

                foreach ($grouped as $entity => $actions) {
                    $count++;
                    $class = $count > 3 ? 'd-none more-perms-' . $role->id : '';

                    $html .= '<div class="col-md-4 ' . $class . '">';
                    $html .= '<div class="border rounded p-2 h-100 shadow-sm">';
                    $html .= '<div class="fw-bold text-uppercase small text-muted mb-2">' . $entity . '</div>';
                    foreach ($order as $act) {
                        if (in_array($act, $actions)) {
                            $badgeClass = $badgeColors[$act] ?? 'secondary';
                            $icon = $badgeIcons[$act] ?? '';
                            $html .= '<span class="badge bg-' . $badgeClass . ' me-1 mb-1 d-inline-flex align-items-center">'
                                . $icon . $act . '</span>';
                        }
                    }
                    $html .= '</div></div>';
                }

                if (count($grouped) > 3) {
                    $html .= '<div class="col-12 mt-2">
                                <button type="button" class="btn btn-sm btn-link px-0" aria-expanded="false" onclick="togglePermissions(' . $role->id . ', this)">Show More</button>
                            </div>';
                }

                $html .= '</div>';
                return $html;
            })
            ->addColumn('actions', function ($row) {
                return view('configuration.roles.actions', compact('row'))->render();
            })
            ->rawColumns(['permissions', 'actions'])
            ->make(true);
    }


    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        abort_if($request->name === 'Super Admin', 403, 'The Super Admin role is reserved.');

        $role = Role::create(['name' => $request->name]);
        $role->syncPermissions($request->permissions);

        return response()->json(['message' => 'Role created successfully.']);
    }

    public function edit($id)
    {
        $role = Role::with('permissions')->findOrFail($id);
        return response()->json($role);
    }


    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        abort_if($role->name === 'Super Admin', 403, 'The Super Admin role cannot be modified.');
        abort_if($request->input('name') === 'Super Admin', 403, 'The Super Admin role name is reserved.');

        $request->validate([
            'name' => 'required|unique:roles,name,' . $role->id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions);

        return response()->json(['message' => 'Role updated successfully.']);
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        abort_if($role->name === 'Super Admin', 403, 'The Super Admin role cannot be deleted.');
        $role->syncPermissions([]);

        $role->delete();

        return response()->json(['message' => 'Role and its permissions removed successfully.']);
    }

}
