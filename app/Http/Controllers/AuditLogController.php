<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use App\Models\User;
use Illuminate\Support\Str;

class AuditLogController extends Controller
{
    public function index()
    {
        $users = User::select('id', 'name')->get();
        return view('configuration.auditlogs.index', compact('users'));
    }

    public function data(Request $request)
    {
        $query = Activity::with('causer')->latest();

        if ($request->filled('user_id')) {
            $query->where('causer_id', $request->user_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        if ($request->filled('action')) {
            $query->where('description', $request->action);
        }

        return datatables()->of($query)
            ->addIndexColumn()
            ->addColumn('user', fn($r) => $r->causer?->name ?? '-')
            ->addColumn('controller', fn($r) => $r->properties['controller'] ?? '-')
            ->addColumn('action', fn($r) => $r->properties['action'] ?? '-')
            ->addColumn('route', fn($r) => $r->properties['route'] ?? '-')
            ->addColumn('ip', fn($r) => $r->properties['ip'] ?? '-')
            ->addColumn('user_agent', fn($r) => Str::limit($r->properties['user_agent'] ?? '-', 50))
            ->editColumn('created_at', fn($r) => $r->created_at->format('Y-m-d H:i'))
            ->rawColumns(['user'])
            ->make(true);
    }
}
