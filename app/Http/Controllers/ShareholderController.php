<?php

namespace App\Http\Controllers;

use App\Models\Shareholder;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class ShareholderController extends Controller
{
    public function index()
    {
        return view('hr.shareholders.index');
    }

    public function data()
    {
        $shareholders = Shareholder::query();
        return DataTables::of($shareholders)
            ->addIndexColumn()
            ->addColumn('actions', function ($row) {
                return '
                    <button class="btn btn-sm btn-info me-1" onclick="editShareholder(' . $row->id . ')">
                        <i class=\'bx bx-edit-alt\'></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteShareholder(' . $row->id . ')">
                        <i class="bx bx-trash"></i>
                    </button>
                ';
            })
            ->editColumn('is_active', fn($row) => $row->is_active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>')
            ->rawColumns(['actions', 'is_active'])
            ->make(true);
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'father_name' => 'nullable|string',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'address' => 'nullable|string',
            'share_percentage' => 'nullable|numeric|min:0|max:100',
            'joining_date' => 'nullable|date',
            'national_id' => 'nullable|string',
            'profile_picture' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        if ($request->hasFile('profile_picture')) {
            $profile_picture = $request->file('profile_picture');
            $filename = uniqid('shareholder_') . '.' . $profile_picture->getClientOriginalExtension();
            $path = 'uploads/shareholders/' . $filename;

            $profile_picture->move(public_path('uploads/shareholders'), $filename);

            $data['profile_picture'] = $path;
        }


        $shareholder = Shareholder::updateOrCreate(['id' => $request->id], $data);
        return response()->json(['message' => 'Shareholder saved successfully.', 'shareholder' => $shareholder->only(['id', 'name', 'share_percentage'])]);
    }

    public function edit(Shareholder $shareholder)
    {
        return response()->json($shareholder);
    }

    public function destroy(Shareholder $shareholder)
    {
        if ($shareholder->profile_picture && file_exists(public_path($shareholder->profile_picture)) && $shareholder->profile_picture !== 'uploads/no_image.jpg') {
            @unlink(public_path($shareholder->profile_picture));
        }

        $shareholder->delete();

        return response()->json(['message' => 'Shareholder and associated data deleted successfully.']);
    }
}
