<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountSubCategory;
use App\Models\Branch;
use App\Models\IncomeSource;
use App\Models\Nationality;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\DataTables;

class AccountController extends Controller
{
    public function allCustomers()
    {
        return view('accounts.all-customers.index', [
            'categories' => AccountSubCategory::where('type', 'customer')->orderBy('name_en')->get(),
            'currencies' => \App\Models\Currency::orderBy('code')->get(),
        ]);
    }

    public function allCustomersData(Request $request, \App\Services\CustomerListingService $listing)
    {
        return DataTables::of($listing->query($request))
            ->addIndexColumn()
            ->addColumn('balance', fn ($account) => $listing->balanceHtml($account, $request->integer('currency_id') ?: null))
            ->addColumn('sub_category', fn ($account) => $account->subCategory?->name_en ?? '—')
            ->rawColumns(['balance'])
            ->make(true);
    }

    public function allCustomersExport(Request $request, \App\Services\CustomerListingService $listing, \App\Services\ReportExportService $exporter)
    {
        $request->validate(['format' => ['required', 'in:pdf,xlsx']]);
        $accounts = $listing->query($request)->orderBy('account_code')->get();
        $rows = $accounts->map(fn ($account) => [
            $account->account_code, $account->full_name, $account->phone, $account->email,
            $account->subCategory?->name_en ?? '—', $listing->balanceText($account, $request->integer('currency_id') ?: null),
            $account->is_active ? 'Active' : 'Inactive',
        ]);
        $headers = ['Code', 'Customer', 'Phone', 'Email', 'Type', 'Balances by currency', 'Status'];
        if ($request->format === 'xlsx') {
            return $exporter->xlsx('All Customers', $headers, $rows, 'all-customers');
        }
        return \Barryvdh\DomPDF\Facade\Pdf::loadView('financial_reports.export', [
            'title' => 'All Customers', 'headers' => $headers, 'rows' => $rows,
            'meta' => ['Branch' => $request->user()->branch?->name ?? 'All authorized branches',
                'Balance filter' => $request->balance ?: 'All', 'Status' => $request->filled('status') ? ($request->status ? 'Active' : 'Inactive') : 'All'],
        ])->setPaper('a4', 'landscape')->download('all-customers.pdf');
    }

    public function regularCustomers()
    {
        $incomeSources = IncomeSource::all();
        $nationalities = Nationality::all();

        return view('accounts.regular-customers.index', compact('incomeSources', 'nationalities'));
    }


    public function countRegularCustomers()
    {
        $subcategory = AccountSubCategory::where('name_en', 'Regular Customer')->first();

        $query = Account::where('type', 'customer')
            ->where('account_sub_category_id', optional($subcategory)->id);

        return $query->count();
    }
    public function regularCustomersData(Request $request, \App\Services\CustomerListingService $listing)
    {
        $subcategory = AccountSubCategory::where('name_en', 'Regular Customer')->first();

        $query = Account::with('subCategory', 'branch')
            ->where('type', 'customer')
            ->where('account_sub_category_id', optional($subcategory)->id)
            ->latest();


        $listing->withBalances($query, $request);

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('balance', fn ($row) => $listing->balanceHtml($row, $request->integer('currency_id') ?: null))
            ->addColumn('actions', function ($row) {
                $viewBtn = "<button onclick='viewCustomer($row->id)' class='btn btn-outline-dark me-1'>
                                <i class='bx bx-show'></i>
                            </button>";

                $editBtn = "<button onclick='editCustomer($row->id)' class='btn btn-outline-dark me-1'>
                                <i class='bx bx-edit'></i>
                            </button>";

                $deleteBtn = "<button onclick='confirmDelete($row->id)' class='btn btn-outline-dark'>
                                <i class='bx bx-trash'></i>
                            </button>";

                $downloadBtn = '';
                if ($row->id_card_file) {
                    $url = e(asset($row->id_card_file));
                    $downloadBtn = "<a href='{$url}' download class='btn btn-outline-primary me-1' title='Download ID card' aria-label='Download ID card'>
                                        <i class='bx bx-download' aria-hidden='true'></i>
                                    </a>";
                }

                return $viewBtn . $editBtn . $downloadBtn . $deleteBtn;
            })
            ->rawColumns(['actions', 'balance'])
            ->make(true);
    }

    public function corporateCustomers()
    {
        $incomeSources = IncomeSource::all();
        $nationalities = Nationality::all();
        return view('accounts.corporate-customers.index', compact('incomeSources', 'nationalities'));
    }

    public function countCorporateCustomers()
    {
        $subcategory = AccountSubCategory::where('name_en', 'Corporate Customer')->first();
        return Account::where('type', 'customer')->where('account_sub_category_id', optional($subcategory)->id)->count();
    }

    public function corporateCustomersData(Request $request, \App\Services\CustomerListingService $listing)
    {
        $subcategory = AccountSubCategory::where('name_en', 'Corporate Customer')->first();
        $query = Account::with('subCategory', 'branch')
            ->where('type', 'customer')
            ->where('account_sub_category_id', optional($subcategory)->id)
            ->latest();


        $listing->withBalances($query, $request);

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('balance', fn ($row) => $listing->balanceHtml($row, $request->integer('currency_id') ?: null))
            ->addColumn('actions', function ($row) {
                $viewBtn = "<button onclick='viewCustomer($row->id)' class='btn btn-outline-dark me-1'>
                            <i class='bx bx-show'></i>
                        </button>";

                $editBtn = "<button onclick='editCustomer($row->id)' class='btn btn-outline-dark me-1'>
                            <i class='bx bx-edit'></i>
                        </button>";

                $deleteBtn = "<button onclick='confirmDelete($row->id)' class='btn btn-outline-dark'>
                            <i class='bx bx-trash'></i>
                        </button>";

                $downloadBtn = '';
                if ($row->id_card_file) {
                    $url = e(asset($row->id_card_file));
                    $downloadBtn = "<a href='{$url}' download class='btn btn-outline-primary me-1' title='Download ID card' aria-label='Download ID card'>
                                        <i class='bx bx-download' aria-hidden='true'></i>
                                    </a>";
                }

                return $viewBtn . $editBtn . $downloadBtn . $deleteBtn;
            })
            ->rawColumns(['actions', 'balance'])
            ->make(true);
    }

    public function temporaryCustomers()
    {
        return view('accounts.temporary-customers.index');
    }

    public function countTemporaryCustomers()
    {
        $subcategory = AccountSubCategory::where('name_en', 'Temporary Customer')->first();
        return Account::where('type', 'customer')->where('account_sub_category_id', optional($subcategory)->id)->count();
    }

    public function temporaryCustomersData(Request $request, \App\Services\CustomerListingService $listing)
    {
        $subcategory = AccountSubCategory::where('name_en', 'Temporary Customer')->first();
        $query = Account::with('subCategory', 'branch')
            ->where('type', 'customer')
            ->where('account_sub_category_id', optional($subcategory)->id)
            ->latest();


        $listing->withBalances($query, $request);

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('balance', fn ($row) => $listing->balanceHtml($row, $request->integer('currency_id') ?: null))
            ->addColumn('actions', function ($row) {
                $viewBtn = "<button onclick='viewCustomer($row->id)' class='btn btn-outline-dark me-1'>
                            <i class='bx bx-show'></i>
                        </button>";

                $editBtn = "<button onclick='editCustomer($row->id)' class='btn btn-outline-dark me-1'>
                            <i class='bx bx-edit'></i>
                        </button>";

                $deleteBtn = "<button onclick='confirmDelete($row->id)' class='btn btn-outline-dark'>
                            <i class='bx bx-trash'></i>
                        </button>";

                return $viewBtn . $editBtn . $deleteBtn;
            })
            ->rawColumns(['actions', 'balance'])
            ->make(true);
    }

    public function streetVendors()
    {
        return view('accounts.street-vendors.index');
    }

    public function countstreetVendors()
    {
        $subcategory = AccountSubCategory::where('name_en', 'Street Vendor')->first();
        return Account::where('type', 'customer')->where('account_sub_category_id', optional($subcategory)->id)->count();
    }

    public function streetVendorsData(Request $request, \App\Services\CustomerListingService $listing)
    {
        $subcategory = AccountSubCategory::where('name_en', 'Street Vendor')->first();
        $query = Account::with('subCategory', 'branch')
            ->where('type', 'customer')
            ->where('account_sub_category_id', optional($subcategory)->id)
            ->latest();


        $listing->withBalances($query, $request);

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('balance', fn ($row) => $listing->balanceHtml($row, $request->integer('currency_id') ?: null))
            ->addColumn('actions', function ($row) {
                $viewBtn = "<button onclick='viewCustomer($row->id)' class='btn btn-outline-dark me-1'>
                            <i class='bx bx-show'></i>
                        </button>";

                $editBtn = "<button onclick='editCustomer($row->id)' class='btn btn-outline-dark me-1'>
                            <i class='bx bx-edit'></i>
                        </button>";

                $deleteBtn = "<button onclick='confirmDelete($row->id)' class='btn btn-outline-dark'>
                            <i class='bx bx-trash'></i>
                        </button>";

                return $viewBtn . $editBtn . $deleteBtn;
            })
            ->rawColumns(['actions', 'balance'])
            ->make(true);
    }


    public function allBranches()
    {
        return view('accounts.branches.index');
    }


    public function allBranchesData(Request $request, \App\Services\CustomerListingService $listing)
    {
        $subcategory = AccountSubCategory::where('name_en', 'Branch Account')->first();
        $query = Account::with('subCategory', 'branch')
            ->where('type', 'branch')
            ->where('account_sub_category_id', optional($subcategory)->id)
            ->latest();


        $listing->withBalances($query, $request);

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('balance', fn ($row) => $listing->balanceHtml($row, $request->integer('currency_id') ?: null))
            ->addColumn('actions', function ($row) {
                $viewBtn = "<button onclick='viewCustomer($row->id)' class='btn btn-outline-dark me-1'>
                            <i class='bx bx-show'></i>
                        </button>";

                return $viewBtn;
            })
            ->rawColumns(['actions', 'balance'])
            ->make(true);
    }

    public function allBanks()
    {
        $bankSubcategories = AccountSubCategory::query()
            ->where('type', 'bank')
            ->where('is_active', true)
            ->orderBy('name_en')
            ->get(['id', 'name_en']);

        return view('accounts.banks.index', compact('bankSubcategories'));
    }

    public function countBanks()
    {
        $subcategory = AccountSubCategory::whereIn('name_en', ['Domestic Bank', 'International Bank'])->first();
        return Account::where('type', 'bank')->where('account_sub_category_id', optional($subcategory)->id)->count();
    }

    public function allBanksData(Request $request, \App\Services\CustomerListingService $listing)
    {
        $subcategories = AccountSubCategory::whereIn('name_en', ['Domestic Bank', 'International Bank'])->pluck('id');
        $query = Account::with('subCategory', 'branch')
            ->where('type', 'bank')
            ->whereIn('account_sub_category_id', $subcategories)
            ->latest();


        $listing->withBalances($query, $request);

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('balance', fn ($row) => $listing->balanceHtml($row, $request->integer('currency_id') ?: null))
            ->addColumn('actions', function ($row) {
                $viewBtn = "<button onclick='viewCustomer($row->id)' class='btn btn-outline-dark me-1'>
                            <i class='bx bx-show'></i>
                        </button>";

                $editBtn = "<button onclick='editCustomer($row->id)' class='btn btn-outline-dark me-1'>
                            <i class='bx bx-edit'></i>
                        </button>";

                $deleteBtn = "<button onclick='confirmDelete($row->id)' class='btn btn-outline-dark'>
                            <i class='bx bx-trash'></i>
                        </button>";

                return $viewBtn . $editBtn . $deleteBtn;
            })
            ->rawColumns(['actions', 'balance'])
            ->make(true);
    }
    public function store(Request $request)
    {
        $customer_type = $request->customer_type;

        if (in_array($customer_type, ['regular', 'corporate'], true)
            || $request->routeIs('accounts.regular_customers.*', 'accounts.corporate_customers.*')) {
            $request->validate([
                'is_sanction_list_applied' => ['required', 'accepted'],
            ], [
                'is_sanction_list_applied.required' => 'Sanction list applied must be checked before saving.',
                'is_sanction_list_applied.accepted' => 'Sanction list applied must be checked before saving.',
            ]);
        }

        $subcategoryName = match ($customer_type) {
            'regular' => 'Regular Customer',
            'corporate' => 'Corporate Customer',
            'temporary' => 'Temporary Customer',
            'street_vendor' => 'Street Vendor',
            default => 'Regular Customer',
        };
        $subcategory = $customer_type === 'bank'
            ? null
            : AccountSubCategory::where('name_en', $subcategoryName)->first();


        $rules = [
            'account_code' => 'required|unique:accounts,account_code,' . $request->id,
            'full_name' => 'required',
            'is_active' => 'nullable|boolean',
        ];

        if ($customer_type === 'bank') {
            $rules['account_sub_category_id'] = 'required|exists:account_sub_categories,id';
        }

        $validated = $request->validate($rules);

        // File Uploads
        $profile_picture = $request->file('profile_picture');
        $id_card_file = $request->file('id_card_file');

        $profilePath = $profile_picture
            ? 'uploads/profiles/' . uniqid() . '.' . $profile_picture->getClientOriginalExtension()
            : 'uploads/no_image.jpg';

        $idCardPath = $id_card_file
            ? 'uploads/id_cards/' . uniqid() . '.' . $id_card_file->getClientOriginalExtension()
            : null;

        if ($profile_picture) {
            $profile_picture->move(public_path('uploads/profiles'), basename($profilePath));
        }

        if ($id_card_file) {
            $id_card_file->move(public_path('uploads/id_cards'), basename($idCardPath));
        }

        $data = [
            'account_code' => $validated['account_code'],
            'full_name' => $validated['full_name'],
            'branch_id' => Auth::user()->branch_id,
            'father_name' => $request->father_name ?? null,
            'phone' => $request->phone ?? null,
            'email' => $request->email ?? null,
            'date_of_birth' => $request->date_of_birth ?? null,
            'kyc_date' => $request->kyc_date ?? null,
            'occupation' => $request->occupation ?? null,
            'income_source_id' => $request->income_source_id ?? null,
            'monthly_income' => $request->monthly_income ?? null,
            'id_card_type' => $request->id_card_type ?? null,
            'id_card_number' => $request->id_card_number ?? null,
            'id_card_file' => $idCardPath ?? ($request->filled('id') ? Account::findOrFail($request->id)->id_card_file : null),
            'risk_level' => $request->risk_level ?? 'low',
            'address' => $request->address ?? null,
            'profile_picture' => $profilePath ?? null,
            'is_pep' => $request->has('is_pep') ? true : false,
            'is_sanction_list_applied' => $request->has('is_sanction_list_applied') ? true : false,
            'nationality_id' => $request->nationality_id ?? null,
            'transaction_volume' => $request->transaction_volume ?? null,
            'purpose' => $request->purpose ?? null,
            'geographical_area' => $request->geographical_area ?? null,
            'distribution_channels' => $request->distribution_channels ?? null,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($customer_type === 'corporate') {
            $data = array_merge($data, [
                'business_name' => $request->business_name ?? null,
                'tin' => $request->tin ?? null,
                'license_type' => $request->license_type ?? null,
                'license_number' => $request->license_number ?? null,
                'license_issue_date' => $request->license_issue_date ?? null,
                'license_expiry_date' => $request->license_expiry_date ?? null,
                'account_number' => $request->account_number ?? null,
                'shop_house_no' => $request->shop_house_no ?? null,
            ]);
        }

        if ($customer_type === 'bank') {
            $data = array_merge($data, [
                'account_number' => $request->account_number ?? null,
                'type' => 'bank',
                'account_sub_category_id' => $request->account_sub_category_id,
            ]);
        } else if ($customer_type === 'street_vendor') {
            $data = array_merge($data, [
                'type' => 'customer',
                'account_sub_category_id' => AccountSubCategory::where('name_en', 'Street Vendor')->value('id'),
            ]);
        } else {
            $data = array_merge($data, [
                'type' => 'customer',
                'account_sub_category_id' => optional($subcategory)->id,
            ]);
        }

        if ($request->filled('id')) {
            $account = Account::findOrFail($request->id);
            $account->update($data);
            $message = ucfirst($customer_type) . ' Customer updated successfully';
        } else {
            Account::create($data);
            $message = ucfirst($customer_type) . ' Customer created successfully';
        }

        return response()->json(['success' => true, 'message' => $message]);
    }


    public function update(Request $request, $id)
    {
        $request->merge(['id' => $id]);

        return $this->store($request);
    }

    public function show($id)
    {
        $account = Account::with('branch')->findOrFail($id);

        return response()->json([
            'full_name' => $account->full_name,
            'phone' => $account->phone,
            'email' => $account->email,
            'address' => $account->address,
            'account_number' => $account->account_number,
            'type' => $account->subCategory->name_en,
            'branch_name' => optional($account->branch)->name,
            'account_code' => $account->account_code,
        ]);
    }

    public function edit($id)
    {
        $account = Account::findOrFail($id);

        if ($account->id_card_file && str_starts_with($account->id_card_file, 'exchange-customer-ids/')) {
            $account->setAttribute('id_card_url', route('exchange.customers.id-card', $account));
        }
        return response()->json($account);
    }

    public function destroy($id)
    {
        $account = Account::findOrFail($id);

        if ($account->profile_picture && $account->profile_picture !== 'uploads/no_image.jpg') {
            @unlink(public_path($account->profile_picture));
        }

        $account->delete();

        return response()->json(['message' => 'Customer deleted']);
    }
}
