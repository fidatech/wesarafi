<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountSubCategory;
use App\Models\Branch;
use App\Models\Province;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Yajra\DataTables\DataTables;

class BranchController extends Controller
{
    public function index()
    {
        $provinces = Province::orderBy('name_en')->get();
        $users = User::select('id', 'name', 'email')->get();

        return view('configuration.branches.index', compact('provinces', 'users'));
    }

    public function data(Request $request)
    {
        $query = Branch::query();

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->province) {
            $query->whereHas('province', function ($q) use ($request) {
                $q->where('name_en', $request->province);
            });
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('province', fn ($row) => optional($row->province)->name_en)
            ->addColumn('actions', function ($row) {
                return '
                    <button class="btn btn-sm btn-primary me-1" onclick="editBranch('.$row->id.')">
                        <i class="bx bx-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="confirmDelete('.$row->id.')">
                        <i class="bx bx-trash"></i>
                    </button>
                ';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'province_id' => 'nullable|exists:provinces,id',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'type' => 'required|in:independent,dependent,agent',
            'user_name' => 'nullable|required_with:user_email,user_password|string|max:255',
            'user_email' => 'nullable|required_with:user_name,user_password|email|unique:users,email',
            'user_password' => 'nullable|required_with:user_name,user_email|string|min:6',
            'allows_direct_remittance' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            return DB::transaction(function () use ($request) {
                // Create Branch
                $branch = Branch::create([
                    'name' => $request->name,
                    'province_id' => $request->province_id,
                    'phone' => $request->phone,
                    'address' => $request->address,
                    'type' => $request->type,
                    'allows_direct_remittance' => $request->boolean('allows_direct_remittance', true),
                ]);

                if (! $request->id && $request->user_email) {
                    $user = User::create([
                        'name' => $request->user_name,
                        'username' => $request->user_name,
                        'email' => $request->user_email,
                        'password' => Hash::make($request->user_password),
                        'branch_id' => $branch->id,
                    ]);

                }
                $branch_account = Account::create([
                    'account_code' => sprintf('BRANCH-%03d', $branch->id),
                    'full_name' => $branch->name,
                    'email' => $request->user_email,
                    'phone' => $branch->phone,
                    'address' => $branch->address,
                    'branch_id' => $branch->id,
                    'account_sub_category_id' => AccountSubCategory::where('name_en', 'Branch Account')->value('id'),
                    'type' => 'branch',
                ]);
                if (isset($user)) {
                    $user->assignRole('Branch');
                    $permissions = Permission::where('name', 'LIKE', 'branch_%')->pluck('id');
                    $user->permissions()->sync($permissions);
                }

                return response()->json([
                    'message' => 'Branch and user created successfully!',
                    'branch' => $branch,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error creating branch or user: '.$e->getMessage());

            return response()->json(['error' => 'An error occurred while creating branch and user.'], 500);
        }
    }

    public function edit($id)
    {
        $branch = Branch::findOrFail($id);

        return response()->json($branch);
    }

    public function update(Request $request, $id)
    {
        $branch = Branch::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'province_id' => 'nullable|exists:provinces,id',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'type' => 'required|in:dependent,independent,agent',
            'allows_direct_remittance' => 'sometimes|boolean',
        ]);

        $branch->update($validated);

        return response()->json(['message' => 'Branch updated successfully.']);
    }

    public function destroy($id)
    {
        $branch = Branch::findOrFail($id);

        if (Transaction::where('branch_id', $branch->id)->exists()) {
            return response()->json([
                'message' => 'This branch has journal transactions and cannot be deleted. Deactivate it instead to preserve the audit trail.',
            ], 422);
        }

        DB::transaction(function () use ($branch) {
            User::where('branch_id', $branch->id)->delete();
            Account::where('branch_id', $branch->id)->delete();
            $branch->delete();
        });

        return response()->json(['message' => 'Branch and its unused records deleted successfully.']);
    }
}
