<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Currency;
use App\Models\Designation;
use App\Models\Employee;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class EmployeeController extends Controller
{
    public function index()
    {
        $branches = Branch::all();
        $designations = Designation::all();
        $currencies = Currency::all();
        return view('hr.employees.index', compact('branches', 'designations', 'currencies'));
    }

    public function data(Request $request)
    {
        $employees = Employee::with(['branch', 'currency', 'designation'])
            ->when($request->branch_id, fn($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->designation_id, fn($q) => $q->where('designation_id', $request->designation_id))
            ->when($request->filled('is_active'), fn($q) => $q->where('is_active', $request->is_active));

        return DataTables::of($employees)
            ->addIndexColumn()
            ->addColumn('profile_picture', fn($e) => $e->profile_picture ?? 'uploads/no_image.jpg')
            ->addColumn('branch', fn($e) => $e->branch->name ?? '-')
            ->addColumn('currency', fn($e) => $e->currency->code ?? '-')
            ->addColumn('designation', fn($e) => $e->designation->name_en ?? '-')
            ->addColumn('actions', function ($e) {
                return '
                    <button class="btn btn-sm btn-info me-1" onclick="editEmployee(' . $e->id . ')">
                        <i class="bx bx-edit-alt"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteEmployee(' . $e->id . ')">
                        <i class="bx bx-trash"></i>
                    </button>
                ';
            })
            ->editColumn('is_active', fn($e) => $e->is_active
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>')
            ->rawColumns(['actions', 'is_active'])
            ->make(true);
    }


    public function store(Request $request, Employee $employee = null)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'father_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'salary' => 'nullable|numeric',
            'joining_date' => 'nullable|date',
            'national_id' => 'nullable|string|max:255',
            'tin_number' => 'nullable|string|max:255',
            'currency_id' => 'nullable|exists:currencies,id',
            'branch_id' => 'nullable|exists:branches,id',
            'designation_id' => 'nullable|exists:designations,id',
            'profile_picture' => 'nullable|image|max:2048',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $filename = uniqid('employee_') . '.' . $file->getClientOriginalExtension();
            $path = 'uploads/employees/' . $filename;
            $file->move(public_path('uploads/employees'), $filename);
            $data['profile_picture'] = $path;
        }

        Employee::updateOrCreate(['id' => $request->id], $data);

        return response()->json(['message' => 'Employee saved successfully.']);
    }

    public function edit(Employee $employee)
    {
        return response()->json($employee);
    }

    public function destroy(Employee $employee)
    {
        if ($employee->profile_picture && $employee->profile_picture !== 'uploads/no_image.jpg') {
            @unlink(public_path($employee->profile_picture));
        }

        $employee->delete();
        return response()->json(['message' => 'Employee deleted successfully.']);
    }
}
