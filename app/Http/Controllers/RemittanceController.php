<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountSubCategory;
use App\Models\Branch;
use App\Models\Currency;
use App\Models\CustomerExchange;
use App\Models\Remittance;
use App\Models\RemittanceLeg;
use App\Models\Transaction;
use App\Services\RemittanceWorkflowService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RemittanceController extends Controller
{
    private function userBranch(): Branch
    {
        abort_unless(auth()->user()->branch_id, 403, 'Assign a branch to your user before managing remittances.');

        return Branch::findOrFail(auth()->user()->branch_id);
    }

    private function authorizeRecord(Remittance $r): void
    {
        $branch = $this->userBranch()->id;
        abort_unless(in_array($branch, [(int) $r->from_branch_id, (int) $r->to_branch_id, (int) $r->bridge_branch_id], true), 404);
    }

    private function authorizeApproval(Remittance $r): void
    {
        $this->authorizeRecord($r);
        $branch = $this->userBranch()->id;
        abort_unless($branch == $r->from_branch_id || $branch == $r->bridge_branch_id || ($r->direction === 'incoming' && $branch == $r->to_branch_id), 403, 'Only the sending, coordinating, or incoming recording branch can approve.');
    }

    private function canPay(Remittance $r): bool
    {
        $branch = $this->userBranch();

        return $branch->id == $r->to_branch_id
            || ($branch->is_main && ! $r->toBranch->users()->where('status', true)->exists());
    }

    public function index(Request $request, ?string $status = null)
    {
        $request->validate(['direction' => 'nullable|in:outgoing,incoming,transfer', 'status' => 'nullable|in:pending,approved,completed,rejected',
            'currency_id' => 'nullable|exists:currencies,id', 'from_date' => 'nullable|date', 'to_date' => 'nullable|date|after_or_equal:from_date', 'search' => 'nullable|string|max:100']);
        $query = Remittance::with(['fromBranch', 'toBranch', 'bridgeBranch', 'currency'])->forBranch($this->userBranch()->id)->latest('date')->latest('id');
        if (in_array($status, ['outgoing', 'incoming', 'transfer'])) {
            $query->where('direction', $status);
        } elseif ($status) {
            $query->where('status', $status);
        }
        foreach (['direction', 'status', 'currency_id'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        $query->when($request->from_date, fn ($q, $v) => $q->whereDate('date', '>=', $v))->when($request->to_date, fn ($q, $v) => $q->whereDate('date', '<=', $v));
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('reference', 'like', '%'.$request->search.'%')->orWhere('sender_name', 'like', '%'.$request->search.'%')->orWhere('receiver_name', 'like', '%'.$request->search.'%'));
        }

        return view('remittances.index', ['remittances' => $query->paginate(25)->withQueryString(), 'status' => $status, 'currencies' => Currency::orderBy('code')->get()]);
    }

    public function create()
    {
        $userBranch = $this->userBranch();

        return view('remittances.create', ['branches' => Branch::with('users')->orderByDesc('is_main')->orderBy('name')->get(),
            'currencies' => Currency::where('is_active', true)->orderByDesc('is_default')->get(), 'userBranch' => $userBranch, 'mainBranch' => Branch::where('is_main', true)->first()]);
    }

    public function customers(Request $request)
    {
        $request->validate(['branch_id' => 'required|integer|exists:branches,id', 'q' => 'nullable|string|max:100', 'id' => 'nullable|integer']);
        $owner = $this->userBranch();
        abort_unless($owner->is_main || $owner->id == $request->integer('branch_id'), 403);
        $rows = Account::where('type', 'customer')->where('is_active', true)->whereNull('deleted_at')->where('branch_id', $request->integer('branch_id'))
            ->when($request->q, fn ($q, $v) => $q->where(fn ($q) => $q->where('full_name', 'like', '%'.$v.'%')->orWhere('account_code', 'like', '%'.$v.'%')->orWhere('phone', 'like', '%'.$v.'%')))
            ->when($request->id, fn ($q, $id) => $q->whereKey($id))->orderBy('full_name')->paginate(30);

        return response()->json(['results' => $rows->map(fn ($a) => ['id' => $a->id, 'text' => $a->account_code.' — '.$a->full_name, 'name' => $a->full_name, 'father' => $a->father_name, 'phone' => $a->phone, 'tazkira' => $a->id_card_number]), 'pagination' => ['more' => $rows->hasMorePages()]]);
    }

    public function store(Request $request)
    {
        $owner = $this->userBranch();
        // Enforce the selected type's branch on the server, even if the browser is tampered with.
        if ($request->direction === 'outgoing') {
            $request->merge(['from_branch_id' => $owner->id]);
        }
        if ($request->direction === 'incoming') {
            $request->merge(['to_branch_id' => $owner->id]);
        }
        if ($request->direction === 'transfer' && ! ($owner->is_main && $request->routing_mode === 'via_main')) {
            $request->merge(['from_branch_id' => $owner->id]);
        }
        $data = $request->validate([
            'direction' => 'required|in:outgoing,incoming,transfer', 'routing_mode' => 'required|in:direct,via_main',
            'from_branch_id' => 'required|integer|exists:branches,id', 'to_branch_id' => 'required|integer|different:from_branch_id|exists:branches,id',
            'sender_account_id' => 'nullable|integer', 'receiver_account_id' => 'nullable|integer',
            'sender_name' => 'nullable|required_without:sender_account_id|string|max:255', 'sender_father_name' => 'nullable|string|max:255', 'sender_phone' => 'nullable|string|max:50', 'sender_tazkira_number' => 'nullable|string|max:100',
            'sender_tazkira_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'receiver_name' => 'nullable|required_without:receiver_account_id|string|max:255', 'receiver_father_name' => 'nullable|string|max:255', 'receiver_phone' => 'nullable|string|max:50', 'receiver_tazkira_number' => 'nullable|string|max:100',
            'currency_id' => ['required', Rule::exists('currencies', 'id')->where('is_active', true)],
            'amount' => 'required|numeric|min:0.01|max:999999999|decimal:0,4', 'exchange_rate_afn' => 'nullable|numeric|min:0.00000001|max:999999|decimal:0,8',
            'sender_branch_commission' => 'nullable|numeric|min:0|max:999999999|decimal:0,4', 'receiver_branch_commission' => 'nullable|numeric|min:0|max:999999999|decimal:0,4', 'bridge_commission' => 'nullable|numeric|min:0|max:999999999|decimal:0,4',
            'collection_method' => 'required|in:cash,account', 'date' => 'required|date_format:Y-m-d|before_or_equal:today', 'description' => 'nullable|string|max:2000',
        ]);
        $from = Branch::findOrFail($data['from_branch_id']);
        $to = Branch::findOrFail($data['to_branch_id']);
        if ($data['routing_mode'] === 'via_main') {
            $main = Branch::where('is_main', true)->first();
            if (! $main) {
                throw ValidationException::withMessages(['routing_mode' => 'Configure Main Branch before using this route.']);
            }
            if (in_array($main->id, [$from->id, $to->id])) {
                throw ValidationException::withMessages(['routing_mode' => 'Use Direct when Main Branch is the sender or receiver.']);
            }
            $data['bridge_branch_id'] = $main->id;
            abort_unless(in_array($owner->id, [$from->id, $to->id, $main->id]), 403);
        } else {
            $data['bridge_branch_id'] = null;
            if ((float) ($data['bridge_commission'] ?? 0) > 0) {
                throw ValidationException::withMessages(['bridge_commission' => 'Main Branch commission requires a route through Main Branch.']);
            }
            // Offline/restricted branches communicate through Main, unless Main is an endpoint.
            if (! $from->is_main && ! $to->is_main && (! $from->hasRemittanceAccess() || ! $to->hasRemittanceAccess())) {
                throw ValidationException::withMessages(['routing_mode' => 'This route requires Main Branch because a branch has no active direct access.']);
            }
        }
        foreach (['sender' => $from, 'receiver' => $to] as $party => $branch) {
            $key = $party.'_account_id';
            if (! empty($data[$key])) {
                abort_unless($owner->is_main || $owner->id == $branch->id, 403, 'Choose existing customers only from your authorized branch.');
                $account = Account::whereKey($data[$key])->where('branch_id', $branch->id)->where('type', 'customer')->where('is_active', true)->whereNull('deleted_at')->first();
                if (! $account) {
                    throw ValidationException::withMessages([$key => 'Select an active customer belonging to this branch.']);
                }
                foreach (['name' => 'full_name', 'father_name' => 'father_name', 'phone' => 'phone', 'tazkira_number' => 'id_card_number'] as $field => $column) {
                    $data[$party.'_'.$field] = $account->$column;
                }
                if ($party === 'sender') {
                    $data['sender_tazkira_file'] = $account->id_card_file;
                }
            }
        }
        if ($data['collection_method'] === 'account' && empty($data['sender_account_id'])) {
            throw ValidationException::withMessages(['sender_account_id' => 'Select an existing sender customer to debit their account.']);
        }
        $data['exchange_rate_afn'] = $this->rate($data, Currency::findOrFail($data['currency_id']));
        $data['amount_afn'] = round($data['amount'] * $data['exchange_rate_afn'], 2);
        if ($data['amount_afn'] <= 0) {
            throw ValidationException::withMessages(['exchange_rate_afn' => 'The AFN equivalent must be at least 0.01.']);
        }
        foreach (['sender_branch_commission', 'receiver_branch_commission', 'bridge_commission'] as $field) {
            $data[$field] = round((float) ($data[$field] ?? 0), 4);
            if ($data[$field] > 0 && round($data[$field] * $data['exchange_rate_afn'], 2) <= 0) {
                throw ValidationException::withMessages([$field => 'Commission must be at least 0.01 AFN equivalent, or zero.']);
            }
        }
        $data['commission'] = $data['sender_branch_commission'] + $data['receiver_branch_commission'] + $data['bridge_commission'];
        $data['status'] = 'pending';
        $data['workflow_version'] = 2;
        $data['created_by'] = auth()->id();
        $data['reference'] = 'REM-'.strtoupper(Str::random(12));
        $upload = null;
        try {
            if ($request->hasFile('sender_tazkira_file')) {
                $data['sender_tazkira_file'] = $upload = $request->file('sender_tazkira_file')->store('remittances/sender-tazkira', 'public');
            } else {
                $data['sender_tazkira_file'] = is_string($data['sender_tazkira_file'] ?? null) ? $data['sender_tazkira_file'] : null;
            }
            $r = DB::transaction(function () use ($data) {
                if (empty($data['sender_account_id'])) {
                    $data['sender_account_id'] = $this->createTemporaryCustomer($data);
                }
                $r = Remittance::create($data);
                $this->promoteCustomerWhenEligible($r->sender_account_id);

                return $r;
            });
        } catch (\Throwable $e) {
            if ($upload) {
                Storage::disk('public')->delete($upload);
            } throw $e;
        }

        return redirect()->route('remittances.show', $r)->with('success', 'Remittance stored. Review the route and amounts before approval.');
    }

    public function show(Remittance $remittance)
    {
        $this->authorizeRecord($remittance);
        $remittance->load(['fromBranch', 'toBranch', 'bridgeBranch', 'currency', 'legs.debtor', 'legs.creditor', 'legs.settlements.creator']);
        $branch = $this->userBranch();

        return view('remittances.show', ['remittance' => $remittance, 'canApprove' => $branch->id == $remittance->from_branch_id || $branch->id == $remittance->bridge_branch_id || ($remittance->direction === 'incoming' && $branch->id == $remittance->to_branch_id), 'canPay' => $this->canPay($remittance)]);
    }

    public function approve(Remittance $remittance, RemittanceWorkflowService $workflow)
    {
        $this->authorizeApproval($remittance);
        $workflow->approve($remittance);

        return back()->with('success', 'Approved. Branch debts, collection, and commission income are posted.');
    }

    public function reject(Request $request, Remittance $remittance)
    {
        $this->authorizeApproval($remittance);
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        DB::transaction(function () use ($remittance, $data) {
            $r = Remittance::lockForUpdate()->findOrFail($remittance->id);
            abort_unless($r->status === 'pending', 422, 'Only pending remittances can be rejected.');
            $r->update(['status' => 'rejected', 'rejection_reason' => $data['reason']]);
        });

        return back()->with('success', 'Remittance rejected.');
    }

    public function complete(Request $request, Remittance $remittance, RemittanceWorkflowService $workflow)
    {
        $this->authorizeRecord($remittance);
        abort_unless($this->canPay($remittance), 403, 'The receiving branch must confirm payout. Main Branch can record payment on behalf of an offline branch.');
        $data = $request->validate(['receiver_name' => 'required|string|max:255', 'receiver_father_name' => 'nullable|string|max:255', 'receiver_phone' => 'nullable|string|max:50', 'receiver_tazkira_number' => 'nullable|string|max:100',
            'receiver_tazkira_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240', 'receiver_live_photo' => 'nullable|string|max:15000000', 'receiver_fingerprint' => 'nullable|string|max:15000000']);
        $paths = [];
        try {
            if ($request->hasFile('receiver_tazkira_file')) {
                $paths[] = $data['receiver_tazkira_file'] = $request->file('receiver_tazkira_file')->store('remittances/receiver-tazkira', 'public');
            }
            if (! empty($data['receiver_live_photo'])) {
                $paths[] = $data['receiver_live_photo'] = $this->storeDataImage($data['receiver_live_photo']);
            }
            $workflow->complete($remittance, $data);
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($paths);
            throw $e;
        }

        return back()->with('success', 'Receiver paid. Treasury and receiver payable updated.');
    }

    public function settle(Request $request, Remittance $remittance, RemittanceLeg $leg, RemittanceWorkflowService $workflow)
    {
        $this->authorizeRecord($remittance);
        abort_unless($leg->remittance_id == $remittance->id, 404);
        abort_unless(in_array($this->userBranch()->id, [$leg->debtor_branch_id, $leg->creditor_branch_id]), 403);
        $data = $request->validate(['amount' => 'required|numeric|min:0.01|max:999999999|decimal:0,4', 'date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'exchange_rate_afn' => 'nullable|numeric|min:0.00000001|max:999999|decimal:0,8', 'reference' => 'nullable|string|max:255']);
        $data['exchange_rate_afn'] = $this->rate($data, $remittance->currency);
        if (round($data['amount'] * $data['exchange_rate_afn'], 2) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Payment must be at least 0.01 AFN equivalent.']);
        }
        $workflow->settle($leg, $data);

        return back()->with('success', 'Branch payment settled and both treasuries updated.');
    }

    public function slip(Request $request, Remittance $remittance)
    {
        $this->authorizeRecord($remittance);
        $remittance->load(['fromBranch', 'toBranch', 'bridgeBranch', 'currency', 'sender']);
        $view = view('remittances.slip', compact('remittance'));

        return $request->boolean('pdf') ? Pdf::loadHTML($view->render())->setPaper('a5')->download($remittance->reference.'.pdf') : $view;
    }

    private function rate(array $data, Currency $currency): float
    {
        if ($currency->code === 'AFN') {
            return 1;
        }
        if (empty($data['exchange_rate_afn'])) {
            throw ValidationException::withMessages(['exchange_rate_afn' => 'Enter AFN per unit for the foreign currency.']);
        }

        return (float) $data['exchange_rate_afn'];
    }

    private function createTemporaryCustomer(array $data): int
    {
        return Account::create(['account_code' => 'TMP-'.strtoupper(Str::random(12)), 'full_name' => $data['sender_name'], 'father_name' => $data['sender_father_name'] ?? null, 'phone' => $data['sender_phone'] ?? null,
            'id_card_type' => 'Tazkira', 'id_card_number' => $data['sender_tazkira_number'] ?? null, 'id_card_file' => $data['sender_tazkira_file'] ?? null, 'type' => 'customer',
            'account_sub_category_id' => AccountSubCategory::where('name_en', 'Temporary Customer')->value('id'), 'branch_id' => $data['from_branch_id'], 'is_active' => true, 'user_id' => auth()->id()])->id;
    }

    private function promoteCustomerWhenEligible(?int $id): void
    {
        $account = Account::with('subCategory')->find($id);
        if ($account?->subCategory?->name_en !== 'Temporary Customer') {
            return;
        }
        $count = Remittance::where('sender_account_id', $id)->count() + Remittance::where('receiver_account_id', $id)->count() + CustomerExchange::where('customer_id', $id)->count() + Transaction::where('account_id', $id)->distinct('linked_id')->count('linked_id');
        if ($count > 3) {
            $account->update(['account_sub_category_id' => AccountSubCategory::where('name_en', 'Regular Customer')->value('id')]);
        }
    }

    private function storeDataImage(string $data): string
    {
        if (! preg_match('/^data:image\/(jpeg|png);base64,(.+)$/s', $data, $matches) || ($bytes = base64_decode($matches[2], true)) === false || ! @getimagesizefromstring($bytes)) {
            throw ValidationException::withMessages(['receiver_live_photo' => 'Invalid camera image.']);
        }
        $path = 'remittances/receiver-photo/'.Str::uuid().'.'.$matches[1];
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }
}
