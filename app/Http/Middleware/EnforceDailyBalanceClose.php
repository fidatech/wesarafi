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
        if (!$state['closed']) return $next($request);

        $message = 'The '.$state['date'].' business day has been closed for your branch. Data entry and changes are locked until the next business day.';
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['message' => $message, 'business_day_closed' => true], 423);
        }

        return redirect()->back()->withErrors(['business_day' => $message]);
    }
}
