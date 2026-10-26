<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountSubCategory;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CustomerExchange;
use App\Models\FxRateUpdate;
use App\Models\Transaction;
use App\Services\DailyValuationService;
use App\Services\OperationalLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CustomerExchangeController extends Controller
{
    private function authorizedBranchId(): ?int
    {
        $user = auth()->user();
        abort_if(! $user->hasRole('Super Admin') && ! $user->branch_id, 403);
        return $user->hasRole('Super Admin') ? null : (int) $user->branch_id;
    }

    public function index(Request $request)
    {
        $query = CustomerExchange::with(['customer', 'branch', 'fromCurrency', 'toCurrency'])->latest('date');
        if ($branchId = $this->authorizedBranchId()) $query->where('branch_id', $branchId);
        foreach (['customer_id', 'from_currency_id', 'to_currency_id', 'status'] as $field) {
            if ($request->$field) $query->where($field, $request->$field);
        }
        if ($request->from_date) $query->whereDate('date', '>=', $request->from_date);
        if ($request->to_date) $query->whereDate('date', '<=', $request->to_date);

        return view('exchange.customers.index', [
            'temporaryCustomerCode' => 'TEMP-'.(Account::where('type', 'customer')->whereHas('subCategory', fn ($query) => $query->where('name_en', 'Temporary Customer'))->count() + 1),
            'exchanges' => $query->paginate(30)->withQueryString(),
            'exchangeReversals' => DB::table('journal_reversals')->join('customer_exchanges', 'customer_exchanges.linked_id', '=', 'journal_reversals.original_linked_id')->when($branchId, fn ($q, $branch) => $q->where('customer_exchanges.branch_id', $branch))->select('journal_reversals.*', 'customer_exchanges.id as exchange_id')->orderByDesc('journal_reversals.created_at')->limit(15)->get(),
            'customers' => Account::where('type', 'customer')->where('is_active', true)->whereNull('deleted_at')->when($branchId, fn ($query, $branch) => $query->where('branch_id', $branch))->orderBy('full_name')->get(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
            'branches' => $branchId ? Branch::whereKey($branchId)->get() : Branch::orderBy('name')->get(),
        ]);
    }

    private function midpointRate(string $date, int $currencyId, int $branchId): float
    {
        if (Currency::whereKey($currencyId)->value('code') === 'AFN') return 1.0;
        $latest = FxRateUpdate::where('branch_id', $branchId)->where('currency_id', $currencyId)
            ->whereDate('rate_date', '<=', $date)->where('recorded_at', '<=', now())->whereNotNull('buy_rate_afn')->whereNotNull('sell_rate_afn')
            ->orderByDesc('recorded_at')->orderByDesc('id')->first();
        if ($latest) return ((float) $latest->buy_rate_afn + (float) $latest->sell_rate_afn) / 2;
        return DailyValuationService::effectiveRate($date, 'close', $currencyId, $branchId);
    }

    public function quote(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date', 'branch_id' => 'required|exists:branches,id',
            'from_currency_id' => 'required|different:to_currency_id|exists:currencies,id',
            'to_currency_id' => 'required|exists:currencies,id',
        ]);
        $branchId = $this->authorizedBranchId();
        abort_if($branchId && $branchId !== (int) $data['branch_id'], 403);
        $fromRate = $this->midpointRate($data['date'], (int) $data['from_currency_id'], (int) $data['branch_id']);
        $toRate = $this->midpointRate($data['date'], (int) $data['to_currency_id'], (int) $data['branch_id']);

        return response()->json([
            'from_rate_afn' => $fromRate, 'to_rate_afn' => $toRate,
            'multiply_rate' => $fromRate / $toRate, 'divide_rate' => $toRate / $fromRate,
        ]);
    }

    public function store(Request $request, OperationalLedgerService $ledger)
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id', 'date' => 'required|date',
            'customer_id' => ['nullable', Rule::exists('accounts', 'id')->where('type', 'customer')->where('is_active', true)->whereNull('deleted_at')],
            'from_currency_id' => 'required|different:to_currency_id|exists:currencies,id',
            'to_currency_id' => 'required|exists:currencies,id', 'from_amount' => 'required|numeric|min:.0001',
            'system_rate' => 'nullable|numeric|min:.00000001',
            'rate' => 'required|numeric|min:.00000001', 'rate_operator' => 'required|in:multiply,divide',
            'commission_amount' => 'nullable|numeric|min:0', 'description' => 'nullable|string|max:1000',
        ]);
        $customerData = [];
        if (empty($data['customer_id'])) {
            $customerData = $request->validate([
                'full_name' => 'required|string|max:255',
                'father_name' => 'nullable|string|max:255',
                'address' => 'nullable|string|max:2000',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:255',
                'id_card_type' => 'nullable|in:etazkira,paper_tazkira,passport,other',
                'id_card_number' => 'nullable|string|max:255',
                'is_sanction_list_applied' => 'required|accepted',
                'id_card_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            ]);
            unset($customerData['id_card_file']);
        }
        $branchId = $this->authorizedBranchId();
        if ($branchId) {
            abort_if(! empty($data['branch_id']) && (int) $data['branch_id'] !== $branchId, 403);
            $data['branch_id'] = $branchId;
        }
        abort_unless($data['branch_id'] ?? null, 422, 'Select a branch.');
        if (! empty($data['customer_id'])) {
            abort_unless(Account::whereKey($data['customer_id'])->where('branch_id', $data['branch_id'])->exists(), 403);
        }
        $fromRate = $this->midpointRate($data['date'], (int) $data['from_currency_id'], (int) $data['branch_id']);
        $toRate = $this->midpointRate($data['date'], (int) $data['to_currency_id'], (int) $data['branch_id']);
        $data['to_amount'] = $data['rate_operator'] === 'divide' ? $data['from_amount'] / $data['rate'] : $data['from_amount'] * $data['rate'];
        $defaultRate = $data['rate_operator'] === 'divide' ? $toRate / $fromRate : $fromRate / $toRate;
        $data['system_rate'] = $data['system_rate'] ?? $defaultRate;
        $data['system_to_amount'] = $data['rate_operator'] === 'divide' ? $data['from_amount'] / $data['system_rate'] : $data['from_amount'] * $data['system_rate'];
        $data['rate_variance_percent'] = $data['system_rate'] ? (($data['rate'] - $data['system_rate']) / $data['system_rate']) * 100 : 0;
        $fromAfn = $data['from_amount'] * $fromRate;
        $toAfn = $data['to_amount'] * $toRate;
        $data['profit_loss_afn'] = $fromAfn - $toAfn;
        $data['linked_id'] = (string) Str::uuid();
        $data['created_by'] = auth()->id();
        $data['status'] = 'approved';
        $data['commission_amount'] ??= 0;

        $idCardPath = null;
        try {
            DB::transaction(function () use ($data, $ledger, $fromRate, $toRate, $fromAfn, $toAfn, $customerData, $request, $defaultRate, &$idCardPath) {
                if (empty($data['customer_id'])) {
                    $category = AccountSubCategory::where('name_en', 'Temporary Customer')->where('type', 'customer')->lockForUpdate()->firstOrFail();
                    $customerCode = 'TEMP-'.(Account::where('type', 'customer')->where('account_sub_category_id', $category->id)->count() + 1);
                    if (Account::where('account_code', $customerCode)->exists()) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['full_name' => 'The next temporary customer code is already in use. Please review temporary customer numbering.']);
                    }
                    if ($request->hasFile('id_card_file')) {
                        $idCardPath = $request->file('id_card_file')->store('exchange-customer-ids', 'local');
                        throw_unless($idCardPath, \RuntimeException::class, 'Unable to store ID card.');
                    }
                    $customer = Account::create(array_merge($customerData, [
                        'account_code' => $customerCode,
                        'type' => 'customer', 'account_sub_category_id' => $category->id,
                        'branch_id' => $data['branch_id'], 'is_active' => true,
                        'is_sanction_list_applied' => true, 'kyc_date' => $data['date'],
                        'id_card_file' => $idCardPath,
                    ]));
                    $data['customer_id'] = $customer->id;
                }
                $exchange = CustomerExchange::create($data);
                if (abs((float) $data['rate'] - $defaultRate) > 0.00000001) {
                    // Preserve the quoted currency pair and operator. The normalized AFN
                    // rate is an observation, not an official buy/sell valuation quote.
                    $normalized = $data['rate_operator'] === 'divide'
                        ? $toRate / (float) $data['rate']
                        : $toRate * (float) $data['rate'];
                    FxRateUpdate::create([
                        'branch_id' => $data['branch_id'], 'currency_id' => $data['from_currency_id'],
                        'to_currency_id' => $data['to_currency_id'], 'rate_date' => $data['date'],
                        'rate_afn_per_unit' => $normalized, 'quoted_rate' => $data['rate'],
                        'rate_operator' => $data['rate_operator'], 'source' => 'customer_exchange',
                        'customer_exchange_id' => $exchange->id, 'created_by' => auth()->id(),
                        'recorded_at' => now(),
                    ]);
                }
                $base = ['linked_id' => $data['linked_id'], 'date' => $data['date'], 'branch_id' => $data['branch_id'], 'module_name' => 'customer_exchange', 'module_id' => $exchange->id, 'created_by' => auth()->id(), 'account_id' => $data['customer_id'], 'accountable_type' => $data['customer_id'] ? Account::class : null, 'accountable_id' => $data['customer_id'], 'description' => $data['description'] ?? 'Customer currency exchange'];
                $lines = [
                    ['currency_id' => $data['from_currency_id'], 'chart_of_account_id' => $ledger->coa('1000'), 'amount' => $data['from_amount'], 'exchange_rate_afn' => $fromRate, 'amount_afn' => $fromAfn, 'direction' => 'debit', 'is_safe' => 1],
                    ['currency_id' => $data['to_currency_id'], 'chart_of_account_id' => $ledger->coa('1000'), 'amount' => $data['to_amount'], 'exchange_rate_afn' => $toRate, 'amount_afn' => $toAfn, 'direction' => 'credit', 'is_safe' => 1],
                ];
                $difference = $toAfn - $fromAfn;
                if (abs($difference) > .01) {
                    $afnId = Currency::where('code', 'AFN')->value('id') ?: $data['from_currency_id'];
                    $lines[] = ['currency_id' => $afnId, 'chart_of_account_id' => $ledger->coa($difference > 0 ? '5016' : '4005'), 'amount' => abs($difference), 'exchange_rate_afn' => 1, 'amount_afn' => abs($difference), 'direction' => $difference > 0 ? 'debit' : 'credit'];
                }
                $ledger->post($base, $lines);
            });

        } catch (\Throwable $exception) {
            if ($idCardPath) Storage::disk('local')->delete($idCardPath);
            throw $exception;
        }

        return back()->with('success', 'Customer exchange saved and posted.');
    }

    public function customerDetails(Account $account)
    {
        $this->authorizeCustomerDetails($account);
        $details = $account->only(['account_code', 'full_name', 'father_name', 'address', 'email', 'phone', 'id_card_type', 'id_card_number', 'is_sanction_list_applied']);
        $details['id_card_url'] = $this->customerIdPath($account)
            ? route('exchange.customers.id-card', $account) : null;
        return response()->json($details)->header('Cache-Control', 'private, no-store');
    }

    private function authorizeCustomerDetails(Account $account): void
    {
        abort_unless($account->type === 'customer' && $account->is_active && !$account->deleted_at, 404);
        $branchId = $this->authorizedBranchId();
        abort_if($branchId && $branchId !== (int) $account->branch_id, 403);
    }

    private function customerIdPath(Account $account): ?string
    {
        $path = $account->id_card_file;
        if (!$path) return null;
        $root = str_starts_with($path, 'exchange-customer-ids/')
            ? Storage::disk('local')->path('exchange-customer-ids')
            : public_path('uploads/id_cards');
        if (!str_starts_with($path, 'exchange-customer-ids/') && !str_starts_with($path, 'uploads/id_cards/')) return null;
        $resolved = realpath(str_starts_with($path, 'exchange-customer-ids/') ? Storage::disk('local')->path($path) : public_path($path));
        $root = realpath($root);
        return $root && $resolved && str_starts_with($resolved, $root.DIRECTORY_SEPARATOR) && is_file($resolved) ? $resolved : null;
    }

    public function customerIdCard(Account $account)
    {
        $this->authorizeCustomerDetails($account);
        $path = $this->customerIdPath($account);
        abort_unless($path, 404);
        return response()->download($path, basename($path), ['Cache-Control' => 'private, no-store']);
    }

    public function reverse(Request $request, CustomerExchange $exchange)
    {
        abort_unless($request->user()->hasRole('Super Admin') || $request->user()->can('edit journal'), 403);
        $branchId = $this->authorizedBranchId();
        abort_if($branchId && $branchId !== (int) $exchange->branch_id, 403);
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);
        $day = \App\Services\DailyBalanceState::forBranch((int) $exchange->branch_id);
        abort_if($day['closed'] || (config('business_day.require_open_for_posting', true) && $day['status'] !== 'open'),
            423, 'The exchange branch business day must be open before posting a reversal.');
        $reference = DB::transaction(function () use ($request, $exchange, $data) {
            $exchange = CustomerExchange::lockForUpdate()->findOrFail($exchange->id);
            abort_if(DB::table('journal_reversals')->where('original_linked_id', $exchange->linked_id)->exists(), 422, 'Exchange already reversed.');
            $original = Transaction::where('linked_id', $exchange->linked_id)->lockForUpdate()->get();
            abort_unless($original->count() >= 2 && $original->every(fn ($line) => $line->module_name === 'customer_exchange'
                && (int) $line->module_id === (int) $exchange->id && (int) $line->branch_id === (int) $exchange->branch_id
                && $line->is_approved && $line->approval_status === 'approved'), 422, 'Invalid exchange journal.');
            abort_unless(abs($original->where('direction', 'debit')->sum('amount_afn') - $original->where('direction', 'credit')->sum('amount_afn')) <= 0.02, 422, 'Unbalanced exchange journal.');
            $reference = (string) Str::uuid();
            DB::table('journal_reversals')->insert(['original_linked_id' => $exchange->linked_id, 'reversal_linked_id' => $reference,
                'reason' => $data['reason'], 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            $entries = $original->map(fn ($line) => [
                'branch_id' => $line->branch_id, 'date' => now('Asia/Kabul')->toDateString(), 'currency_id' => $line->currency_id,
                'chart_of_account_id' => $line->chart_of_account_id, 'account_id' => $line->account_id,
                'accountable_type' => $line->accountable_type, 'accountable_id' => $line->accountable_id,
                'is_safe' => $line->is_safe, 'amount' => $line->amount, 'amount_afn' => $line->amount_afn,
                'exchange_rate_afn' => $line->exchange_rate_afn,
                'direction' => $line->direction === 'debit' ? 'credit' : 'debit',
                'module_name' => 'customer_exchange_reversal', 'module_id' => $exchange->id,
                'created_by' => $request->user()->id, 'description' => 'Exchange reversal '.$exchange->linked_id.': '.$data['reason'],
            ])->all();
            \App\Services\TransactionService::postMany($entries, $reference);
            $exchange->status = 'reversed';
            $exchange->save();
            return $reference;
        });
        return redirect()->route('exchange.customers.index')->with('success', 'Exchange reversed: '.$reference);
    }

    public function destroy(CustomerExchange $exchange)
    {
        $branchId = $this->authorizedBranchId();
        abort_if($branchId && $branchId !== (int) $exchange->branch_id, 403);
        abort_if(Transaction::where('linked_id', $exchange->linked_id)->exists(), 422,
            'Posted exchanges cannot be deleted. Use an approved accounting reversal workflow.');
        DB::transaction(fn () => $exchange->delete());
        return back()->with('success', 'Exchange deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer|exists:customer_exchanges,id']);
        $query = CustomerExchange::whereIn('id', $data['ids']);
        if ($branchId = $this->authorizedBranchId()) $query->where('branch_id', $branchId);
        DB::transaction(function () use ($query, $data) {
            $exchanges = $query->lockForUpdate()->get();
            abort_unless($exchanges->count() === count(array_unique($data['ids'])), 403);
            abort_if(Transaction::whereIn('linked_id', $exchanges->pluck('linked_id'))->exists(), 422,
                'Selection includes posted exchanges. No exchanges were deleted.');
            $exchanges->each(fn ($exchange) => $exchange->delete());
        });
        return back()->with('success', 'Selected exchanges deleted.');
    }
}
