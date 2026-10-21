<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Auction;
use App\Models\AuctionLine;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Services\AuctionPostingService;
use App\Services\TransactionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuctionController extends Controller
{
    public function index()
    {
        return view('exchange.auctions.index', [
            'branches' => Branch::orderBy('name')->get(),
            'accounts' => Account::where('is_active', true)->orderBy('full_name')->get(),
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function data()
    {
        return datatables()
            ->eloquent(
                Auction::with([
                    'branch',
                    'account',
                    'lines.giveCurrency',
                    'lines.getCurrency',
                ])->orderByDesc('date')->orderByDesc('id')
            )
            ->addIndexColumn()
            ->addColumn('type_badge', function (Auction $auction) {
                $map = [
                    'purchase_from_dab' => 'primary',
                    'sell_to_customer' => 'success',
                    'sell_to_dab' => 'warning',
                ];
                $label = ucfirst(str_replace('_', ' ', $auction->type));

                return '<span class="badge bg-' . ($map[$auction->type] ?? 'secondary') . '">' . e($label) . '</span>';
            })
            ->addColumn('branch', fn (Auction $auction) => $auction->branch?->name ?? '-')
            ->addColumn('counterparty', fn (Auction $auction) => $auction->account?->full_name ?? '-')
            ->addColumn('summary', function (Auction $auction) {
                $give = [];
                $get = [];

                foreach ($auction->lines as $line) {
                    $giveCode = $line->giveCurrency?->code ?? '?';
                    $getCode = $line->getCurrency?->code ?? '?';
                    $give[$giveCode] = ($give[$giveCode] ?? 0) + (float) $line->give_amount;
                    $get[$getCode] = ($get[$getCode] ?? 0) + (float) $line->get_amount;
                }

                $format = fn (array $values) => implode(' + ', array_map(
                    fn (string $code, float $amount) => number_format($amount, 4) . ' ' . e($code),
                    array_keys($values),
                    $values
                ));

                return '<div><strong>Give:</strong> ' . $format($give)
                    . '<br><strong>Get:</strong> ' . $format($get) . '</div>';
            })
            ->addColumn('lines_count', fn (Auction $auction) => $auction->lines->count())
            ->addColumn('date', fn (Auction $auction) => $auction->date?->format('Y-m-d'))
            ->addColumn('actions', fn (Auction $auction) =>
                '<button class="btn btn-sm btn-primary editAuction" data-id="' . $auction->id . '"><i class="bx bx-edit"></i></button>
                 <button class="btn btn-sm btn-danger deleteAuction" data-id="' . $auction->id . '"><i class="bx bx-trash"></i></button>'
            )
            ->rawColumns(['type_badge', 'summary', 'actions'])
            ->toJson();
    }

    public function store(Request $request)
    {
        $validated = $this->validatedData($request);

        try {
            DB::transaction(function () use ($validated) {
                $cashCoaId = $this->cashCoaId();

                $auction = Auction::create([
                    'type' => $validated['type'],
                    'branch_id' => $validated['branch_id'],
                    'account_id' => $validated['account_id'],
                    'chart_of_account_id' => $cashCoaId,
                    'date' => $validated['date'],
                    'description' => $validated['description'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                $this->replaceLines($auction, $validated['lines']);
                $auction->load('lines.giveCurrency', 'lines.getCurrency');

                TransactionService::postMany(AuctionPostingService::entries($auction, $cashCoaId));
            });

            return response()->json(['success' => true, 'message' => 'Auction saved successfully.']);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    public function show(Auction $auction)
    {
        return response()->json(
            $auction->load('lines.giveCurrency', 'lines.getCurrency')
        );
    }

    public function update(Request $request, Auction $auction)
    {
        $validated = $this->validatedData($request);

        try {
            DB::transaction(function () use ($validated, $auction) {
                $cashCoaId = $this->cashCoaId();

                $auction->update([
                    'type' => $validated['type'],
                    'branch_id' => $validated['branch_id'],
                    'account_id' => $validated['account_id'],
                    'chart_of_account_id' => $cashCoaId,
                    'date' => $validated['date'],
                    'description' => $validated['description'] ?? null,
                ]);

                $this->replaceLines($auction, $validated['lines']);
                $auction->load('lines.giveCurrency', 'lines.getCurrency');

                TransactionService::repost('auction', $auction->id, function () use ($auction, $cashCoaId) {
                    return AuctionPostingService::entries($auction, $cashCoaId);
                });
            });

            return response()->json(['success' => true, 'message' => 'Auction updated successfully.']);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    public function destroy(Auction $auction)
    {
        DB::transaction(function () use ($auction) {
            TransactionService::delete('auction', $auction->id);
            $auction->delete();
        });

        return response()->json(['success' => true, 'message' => 'Auction deleted successfully.']);
    }

    private function validatedData(Request $request): array
    {
        if ($request->filled('lines_json')) {
            $decoded = json_decode((string) $request->input('lines_json'), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $request->merge(['lines' => $decoded]);
            }
        }

        $cleanLines = collect($request->input('lines', []))
            ->filter(fn ($line) =>
                ! empty($line['give_currency_id'])
                && ! empty($line['get_currency_id'])
                && (float) ($line['give_amount'] ?? 0) > 0
                && (float) ($line['get_amount'] ?? 0) > 0
            )
            ->values()
            ->all();

        $request->merge(['lines' => $cleanLines]);

        $validated = $request->validate([
            'type' => 'required|in:purchase_from_dab,sell_to_customer,sell_to_dab',
            'branch_id' => 'required|exists:branches,id',
            'account_id' => 'required|exists:accounts,id',
            'date' => 'required|date',
            'description' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1',
            'lines.*.give_currency_id' => 'required|exists:currencies,id',
            'lines.*.give_amount' => 'required|numeric|min:0.0001',
            'lines.*.get_currency_id' => 'required|exists:currencies,id',
            'lines.*.rate_get_per_give' => 'nullable|numeric|min:0.000001',
            'lines.*.get_amount' => 'required|numeric|min:0.0001',
            'lines.*.note' => 'nullable|string|max:1000',
        ]);

        foreach ($validated['lines'] as $index => $line) {
            if ((int) $line['give_currency_id'] === (int) $line['get_currency_id']) {
                throw ValidationException::withMessages([
                    "lines.{$index}.get_currency_id" => 'Give and get currencies must be different.',
                ]);
            }
        }

        return $validated;
    }

    private function replaceLines(Auction $auction, array $lines): void
    {
        $auction->lines()->delete();

        foreach ($lines as $line) {
            $giveAmount = (float) $line['give_amount'];
            $getAmount = (float) $line['get_amount'];

            AuctionLine::create([
                'auction_id' => $auction->id,
                'give_currency_id' => $line['give_currency_id'],
                'give_amount' => $giveAmount,
                'get_currency_id' => $line['get_currency_id'],
                'rate_get_per_give' => $line['rate_get_per_give'] ?? round($getAmount / $giveAmount, 8),
                'get_amount' => $getAmount,
                'note' => $line['note'] ?? null,
            ]);
        }
    }

    private function cashCoaId(): int
    {
        $id = ChartOfAccount::where('code', '1000')->value('id');
        if (! $id) {
            throw new \RuntimeException('COA code 1000 (Cash on Hand) was not found. Run the chart of accounts seeder first.');
        }

        return (int) $id;
    }
}
