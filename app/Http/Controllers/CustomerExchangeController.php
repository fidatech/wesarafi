<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountSubCategory;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CustomerExchange;
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
    public function index(Request $request)
    {
        $query = CustomerExchange::with(['customer', 'branch', 'fromCurrency', 'toCurrency'])->latest('date');
        if (auth()->user()->branch_id) $query->where('branch_id', auth()->user()->branch_id);
        foreach (['customer_id', 'from_currency_id', 'to_currency_id', 'status'] as $field) {
            if ($request->$field) $query->where($field, $request->$field);
        }
        if ($request->from_date) $query->whereDate('date', '>=', $request->from_date);
        if ($request->to_date) $query->whereDate('date', '<=', $request->to_date);

        return view('exchange.customers.index', [
            'temporaryCustomerCode' => 'TEMP-'.(Account::where('type', 'customer')->whereHas('subCategory', fn ($query) => $query->where('name_en', 'Temporary Customer'))->count() + 1),
            'exchanges' => $query->paginate(30)->withQueryString(),
            'customers' => Account::where('type', 'customer')->where('is_active', true)->whereNull('deleted_at')->when(auth()->user()->branch_id, fn ($query, $branchId) => $query->where('branch_id', $branchId))->orderBy('full_name')->get(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
            'branches' => auth()->user()->branch_id ? Branch::whereKey(auth()->user()->branch_id)->get() : Branch::orderBy('name')->get(),
        ]);
    }

    public function quote(Request $request)
    {
        $data = $request->validate([
            'date' => 'required|date', 'branch_id' => 'required|exists:branches,id',
            'from_currency_id' => 'required|different:to_currency_id|exists:currencies,id',
            'to_currency_id' => 'required|exists:currencies,id',
        ]);
        $fromRate = DailyValuationService::effectiveRate($data['date'], 'close', (int) $data['from_currency_id'], (int) $data['branch_id']);
        $toRate = DailyValuationService::effectiveRate($data['date'], 'close', (int) $data['to_currency_id'], (int) $data['branch_id']);

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
        $data['branch_id'] = auth()->user()->branch_id ?: $data['branch_id'];
        abort_unless($data['branch_id'], 422, 'Select a branch.');

        $fromRate = DailyValuationService::effectiveRate($data['date'], 'close', (int) $data['from_currency_id'], (int) $data['branch_id']);
        $toRate = DailyValuationService::effectiveRate($data['date'], 'close', (int) $data['to_currency_id'], (int) $data['branch_id']);
        $data['to_amount'] = $data['rate_operator'] === 'divide' ? $data['from_amount'] / $data['rate'] : $data['from_amount'] * $data['rate'];
        $data['system_rate'] = $data['system_rate'] ?? ($data['rate_operator'] === 'divide' ? $toRate / $fromRate : $fromRate / $toRate);
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
            DB::transaction(function () use ($data, $ledger, $fromRate, $toRate, $fromAfn, $toAfn, $customerData, $request, &$idCardPath) {
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
        abort_if(auth()->user()->branch_id && auth()->user()->branch_id != $account->branch_id, 403);
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

    public function destroy(CustomerExchange $exchange)
    {
        abort_if(auth()->user()->branch_id && auth()->user()->branch_id !== $exchange->branch_id, 403);
        DB::transaction(function () use ($exchange) { Transaction::where('linked_id', $exchange->linked_id)->delete(); $exchange->delete(); });
        return back()->with('success', 'Exchange deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer|exists:customer_exchanges,id']);
        $query = CustomerExchange::whereIn('id', $data['ids']);
        if (auth()->user()->branch_id) $query->where('branch_id', auth()->user()->branch_id);
        DB::transaction(function () use ($query) {
            $query->get()->each(function ($exchange) { Transaction::where('linked_id', $exchange->linked_id)->delete(); $exchange->delete(); });
        });
        return back()->with('success', 'Selected exchanges deleted.');
    }
}
