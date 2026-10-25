<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class FinancialAuditHistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $isSuper = $user->hasRole('Super Admin');
        abort_unless($isSuper || $user->can('manage business day'), 403);

        $filters = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);
        abort_if(! $isSuper && ! $user->branch_id, 403);
        $branchId = (int) ($filters['branch_id'] ?? ($user->branch_id ?: 1));
        abort_unless($isSuper || $branchId === (int) $user->branch_id, 403);

        $branches = $isSuper ? Branch::orderBy('name')->get(['id', 'name']) : Branch::whereKey($branchId)->get(['id', 'name']);
        // Only expose identities of users who requested exports for this branch.
        $actorIds = Activity::query()->where('log_name', 'financial-audit')
            ->where('description', 'Historical closing integrity report export requested')
            ->where('properties->branch_id', $branchId)
            ->where('causer_type', $user->getMorphClass())
            ->whereNotNull('causer_id')->distinct()->pluck('causer_id');
        $users = User::whereIn('id', $actorIds)->orderBy('name')->get(['id', 'name']);
        if (isset($filters['user_id']) && ! $actorIds->contains((int) $filters['user_id'])) {
            abort(403, 'Selected audit actor is outside this branch history.');
        }
        $events = Activity::query()->with('causer')
            ->where('log_name', 'financial-audit')
            ->where('description', 'Historical closing integrity report export requested')
            ->where('properties->branch_id', $branchId)
            ->when(isset($filters['user_id']), fn ($q) => $q->where('causer_type', $user->getMorphClass())->where('causer_id', $filters['user_id']))
            ->when(isset($filters['from']), fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->latest('id')->paginate(25)->withQueryString();

        return view('financial_reports.audit-export-history', compact('events', 'branches', 'users', 'branchId', 'filters'));
    }
}
