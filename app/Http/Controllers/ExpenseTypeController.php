<?php

namespace App\Http\Controllers;

use App\Models\ExpenseType;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class ExpenseTypeController extends Controller
{
    public function index()
    {
        return view('configuration.expense-types.index');
    }

    public function data()
    {
        $data = ExpenseType::query();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('actions', function ($row) {
                $editBtn = "<button onclick='editExpenseType($row->id)' class='btn btn-sm btn-warning me-1'>
                                <i class='bx bx-edit'></i>
                            </button>";

                $deleteBtn = "<button onclick='confirmDelete($row->id)' class='btn btn-sm btn-danger'>
                                <i class='bx bx-trash'></i>
                            </button>";

                return $editBtn . $deleteBtn;
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

        ExpenseType::updateOrCreate(
            ['id' => $request->id],
            $validated
        );

        return response()->json(['success' => 'Saved successfully.']);
    }

    public function edit(ExpenseType $expenseType)
    {
        return response()->json($expenseType);
    }

    public function destroy(ExpenseType $expenseType)
    {
        $expenseType->delete();
        return response()->json(['success' => 'Deleted successfully.']);
    }
}
