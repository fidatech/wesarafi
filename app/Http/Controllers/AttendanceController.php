<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['Super Admin', 'Admin', 'Branch']), 403);
        abort_if($request->user()->branch_id && $request->filled('branch_id') && $request->integer('branch_id') !== (int) $request->user()->branch_id, 403);
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->month) ? $request->month : now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $branch = $request->branch_id ?: auth()->user()->branch_id;
        $employees = Employee::with(['branch', 'designation'])->where('is_active', true)
            ->when($branch, fn ($query) => $query->where('branch_id', $branch))->orderBy('name')->get();
        $records = Attendance::whereBetween('date', [$start, $end])->whereIn('employee_id', $employees->pluck('id'))
            ->get()->groupBy('employee_id')->map(fn ($items) => $items->keyBy(fn ($item) => $item->date->format('Y-m-d')));
        $days = collect(CarbonPeriod::create($start, $end))->map(fn ($date) => $date->copy());

        return view('hr.attendance.index', compact('month', 'branch', 'employees', 'records', 'days') + ['branches' => Branch::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['Super Admin', 'Admin', 'Branch']), 403);
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'], 'records' => ['required', 'array'],
            'records.*.*.status' => ['required', 'in:present,absent,leave,holiday,half_day'],
            'records.*.*.overtime_hours' => ['nullable', 'numeric', 'min:0'],
            'records.*.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($data['records'] as $employeeId => $dates) {
            $employee = Employee::findOrFail($employeeId);
            abort_if($request->user()->branch_id && (int) $employee->branch_id !== (int) $request->user()->branch_id, 403);
            foreach ($dates as $date => $row) {
                abort_unless(str_starts_with($date, $data['month'].'-'), 422, 'Attendance date is outside the selected month.');
                Attendance::updateOrCreate(['employee_id' => $employeeId, 'date' => $date], $row + [
                    'branch_id' => $employee->branch_id, 'created_by' => auth()->id(),
                ]);
            }
        }

        return back()->with('success', __('Monthly attendance saved.'));
    }
}
