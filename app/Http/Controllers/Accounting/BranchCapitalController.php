<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\BranchCapitalTransfer;
use App\Models\Currency;
use App\Services\BranchCapitalService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchCapitalController extends Controller
{
    private function scoped()
    {
        return BranchCapitalTransfer::query()->when(auth()->user()->branch_id, fn ($q) => $q->where(fn ($q) => $q->where('source_branch_id', auth()->user()->branch_id)->orWhere('destination_branch_id', auth()->user()->branch_id)));
    }

    public function index(Request $request)
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'branch_id' => 'nullable|exists:branches,id', 'currency_id' => 'nullable|exists:currencies,id', 'status' => 'nullable|in:outstanding,repaid']);
        $query = $this->scoped()->whereNull('advance_id')->with(['source', 'destination', 'currency'])->withSum('repayments', 'amount')
            ->when($request->from, fn ($q) => $q->whereDate('date', '>=', $request->from))->when($request->to, fn ($q) => $q->whereDate('date', '<=', $request->to))
            ->when($request->branch_id, fn ($q) => $q->where(fn ($q) => $q->where('source_branch_id', $request->branch_id)->orWhere('destination_branch_id', $request->branch_id)))
            ->when($request->currency_id, fn ($q) => $q->where('currency_id', $request->currency_id));
        if ($request->status) {
            $query->whereRaw('amount '.($request->status === 'repaid' ? '<=' : '>').' (select coalesce(sum(r.amount),0) from branch_capital_transfers r where r.advance_id = branch_capital_transfers.id)');
        }

        return view('accounting.branch-capital.index', ['items' => $query->latest('date')->latest('id')->paginate(25)->withQueryString(), 'branches' => Branch::orderBy('name')->get(), 'currencies' => Currency::orderBy('code')->get()]);
    }

    public function create()
    {
        return view('accounting.branch-capital.create', ['branches' => Branch::orderBy('name')->get(), 'currencies' => Currency::where('is_active', true)->orderByDesc('is_default')->get()]);
    }

    public function store(Request $request, BranchCapitalService $service)
    {
        $data = $request->validate($this->rules() + ['source_branch_id' => ['required', Rule::exists('branches', 'id'), function ($attribute, $value, $fail) {
            if (auth()->user()->branch_id && (int) $value !== (int) auth()->user()->branch_id) {
                $fail('Select your own branch as the funding branch.');
            }
        }], 'destination_branch_id' => 'required|exists:branches,id|different:source_branch_id', 'currency_id' => ['required', Rule::exists('currencies', 'id')->where('is_active', true)]]);
        $this->normalizeRate($data, Currency::findOrFail($data['currency_id']));
        $transfer = $service->create($data);

        return redirect()->route('accounting.branch-capital.show', $transfer)->with('success', 'Capital shared and branch debt recorded.');
    }

    public function show(int $branchCapital)
    {
        $item = $this->scoped()->whereNull('advance_id')->with(['source', 'destination', 'currency', 'repayments.creator'])->findOrFail($branchCapital);

        return view('accounting.branch-capital.show', compact('item'));
    }

    public function repay(Request $request, int $branchCapital, BranchCapitalService $service)
    {
        $item = $this->scoped()->whereNull('advance_id')->findOrFail($branchCapital);
        $data = $request->validate($this->rules());
        $this->normalizeRate($data, $item->currency);
        $service->create($data, $item);

        return redirect()->route('accounting.branch-capital.show', $item)->with('success', 'Repayment recorded and outstanding debt reduced.');
    }

    private function rules(): array
    {
        return ['amount' => 'required|numeric|min:0.01|max:999999999999|decimal:0,4', 'date' => 'required|date_format:Y-m-d', 'exchange_rate_afn' => 'nullable|numeric|min:0.00000001|max:999999999|decimal:0,8', 'description' => 'nullable|string|max:2000'];
    }

    private function normalizeRate(array &$data, Currency $currency): void
    {
        if ($currency->code === 'AFN') {
            $data['exchange_rate_afn'] = 1;
        } elseif (empty($data['exchange_rate_afn'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['exchange_rate_afn' => 'Enter the AFN rate for this foreign-currency transfer.']);
        }
    }
}
