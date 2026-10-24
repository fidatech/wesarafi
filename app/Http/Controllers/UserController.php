<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\DataTables;
use Illuminate\Support\Facades\Storage;
use App\Models\Branch;

class UserController extends Controller
{
    public function index()
    {
        $roles = Role::all();
        $branches = Branch::all();
        return view('configuration.users.index', compact('roles', 'branches'));
    }

    public function data(Request $request)
    {
        $query = User::with(['roles', 'branch'])->select('users.*');

        if ($request->filled('search.value')) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%")
                  ->orWhere('username', 'like', "%$search%");
            });
        }

        if ($request->filled('role')) {
            $role = $request->input('role');
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('role', fn($row) => $row->roles->pluck('name')->join(', '))
            ->addColumn('branch', fn($row) => optional($row->branch)->name)
            ->addColumn('status', function ($row) {
                return $row->status
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-danger">Inactive</span>';
            })
            ->addColumn('actions', function ($row) {
                return view('configuration.users.actions', compact('row'))->render();
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'status' => 'required|boolean',
            'branch_id' => 'nullable|exists:branches,id',
            'role' => 'required|string|exists:roles,name',
            'image' => 'nullable|image|max:2048',
        ]);

        $user = new User();
        $user->fill($request->only('name', 'username', 'email', 'branch_id', 'status'));
        $user->password = Hash::make($request->password);

        if ($request->hasFile('image')) {
            $user->image = $request->file('image')->store('users', 'public');
        }

        $user->save();
        $user->assignRole($request->role);

        return response()->json(['message' => 'User created successfully.']);
    }

    public function edit(User $user)
    {
        $user->load('roles');
        return response()->json($user);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'email' => 'required|email|unique:users,email,' . $user->id,
            'status' => 'required|boolean',
            'branch_id' => 'nullable|exists:branches,id',
            'role' => 'required|string|exists:roles,name',
            'image' => 'nullable|image|max:2048',
        ]);

        $user->fill($request->only('name', 'username', 'email', 'branch_id', 'status'));

        if ($request->hasFile('image')) {
            if ($user->image && $user->image != 'no_image.png') {
                Storage::disk('public')->delete($user->image);
            }
            $user->image = $request->file('image')->store('users', 'public');
        }

        $user->save();
        $user->syncRoles([$request->role]);

        return response()->json(['message' => 'User updated successfully.']);
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->json(['message' => 'User deleted successfully.']);
    }
}
