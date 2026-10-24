<?php

namespace App\Http\Middleware;

use App\Services\DailyBalanceState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceDailyBalanceClose
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->isMethod('GET') || $request->isMethod('HEAD') || $request->isMethod('OPTIONS')) {
            return $next($request);
        }

        // Sign-out, recovery and branch closing itself must remain possible.
        if ($request->routeIs('logout', 'fx.rates.close', 'fx.rates.open', 'configuration.backups.*')) {
            return $next($request);
        }

        $branchId = (int) ($request->user()->branch_id ?: 1);
        $state = DailyBalanceState::forBranch($branchId);
        $financialWrite = $request->routeIs(
            'journal.transactions.*', 'journal.approvals.*', 'remittances.*',
            'exchange.auctions.*', 'exchange.customers.*',
            'accounting.*', 'hr.payroll.post'
        );
        $requiresOpening = config('business_day.require_open_for_posting', true)
            && $financialWrite && $state['status'] !== 'open';
        if (!$state['closed'] && !$requiresOpening) return $next($request);

        $message = $state['closed']
            ? 'The '.$state['date'].' business day is closed. Data changes are locked until the next business day.'
            : 'Open the business day before recording financial transactions.';
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $message, 'business_day_closed' => true], 423);
        }

        return redirect()->back()->withErrors(['business_day' => $message]);
    }
}
