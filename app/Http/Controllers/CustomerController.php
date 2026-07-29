<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Setup;
use App\Models\User;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    private const PHONE_RULE = ['required', 'string', 'max:255', 'regex:/^\+?[0-9][0-9\s\-()]*?(?:\s*,\s*\+?[0-9][0-9\s\-()]*)*$/'];

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        $authLayout = $this->getAuthLayout($request->route()->getName());

        if ($request->ajax()) {
            $customers = app(ModuleBranchService::class)->applyScope(Customer::orderByDesc('id'), 'customers')
                ->applyFilters($request);

            return response()->json(['data' => $customers, 'authLayout' => $authLayout]);
        }

        $cities_options = [];
        $allCities = app(ModuleBranchService::class)
            ->applyRelatedScope(Setup::where('type', 'city'), 'setups', 'customers')
            ->get();

        foreach ($allCities as $city) {
            $cities_options[(int)$city->id] = ['text' => $city->title];
        }

        // return $customers[0];
        return view("customers.index", compact( 'authLayout', 'cities_options'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $cities_options = [];
        $allCities = app(ModuleBranchService::class)
            ->applyRelatedScope(Setup::where('type', 'city'), 'setups', 'customers')
            ->get();

        foreach ($allCities as $city) {
            $cities_options[$city->id] = ['text' => $city->title];
        }

        $usernames = User::pluck('username')->toArray();
        return view('customers.create', compact('cities_options', 'usernames'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'customer_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('customers', 'customer_name')
                    ->where(function ($query) use ($request) {
                        $query->where('city_id', $request->city);

                        $branches = app(ModuleBranchService::class);
                        if ($branches->shouldFilterRecords('customers')) {
                            $branchId = $branches->branchIdForCreate('customers');
                            if ($branchId) {
                                $query->where('branch_id', $branchId);
                            }
                        }

                        return $query;
                    }),
            ],
            'person_name' => 'required|string|max:255',
            'urdu_title' => 'nullable|string|max:255',
            'username' => 'required|string|min:6|max:255|regex:/^[a-z0-9]+$/|unique:users,username',
            'password' => 'required|string|min:3',
            'phone_number' => self::PHONE_RULE,
            'image_upload' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            'date' => 'required|date',
            'category' => 'required|string|max:255',
            'city' => 'required|integer|exists:setups,id',
            'address' => 'required|string|max:255',
        ]);

        // Check for validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $hashedPassword = Hash::make($data['password']); // Hash the password

        $user = User::where('username', $request->username)->first();

        if (!$user) {
            // Upload the image if provided
            if ($request->hasFile('image_upload')) {
                $image = $request->file('image_upload');
                $image_name = time() . '.' . $image->getClientOriginalExtension();
                $image->move(public_path('uploads/suppliers'), $image_name);
                $data['image'] = $image_name;
            } else {
                $data['image'] = "default_avatar.png";
            }

            $user = User::create([
                'name' => $data['customer_name'],
                'branch_id' => app(ModuleBranchService::class)->branchIdForCreate('users'),
                'username' => $data['username'],
                'password' => $hashedPassword,
                'role' => 'customer',
                'profile_picture' => $data['image'],
            ]);
        } else {
            return redirect()->back()->with('error', 'This user already exists.')->withInput();
        }

        // Create a new customer
        $customer = Customer::create([
            'user_id' => $user->id,
            'branch_id' => app(ModuleBranchService::class)->branchIdForCreate('customers'),
            'customer_name' => $user->name,
            'person_name' => $data['person_name'],
            'phone_number' => $data['phone_number'],
            'urdu_title' => $data['urdu_title'],
            'date' => $data['date'],
            'category' => $data['category'],
            'city_id' => $data['city'],
            'address' => $data['address'],
        ]);

        Cache::forget('category_data:customer');
        return redirect()->route('customers.create')->with('success', 'Customer created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Customer $customer)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($customer, 'customers');

        return view('customers.edit', compact('customer'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($customer, 'customers');

        $validator = Validator::make($request->all(), [
            'person_name' => 'required|string|max:255',
            'urdu_title' => 'nullable|string|max:255',
            'phone_number' => self::PHONE_RULE,
            'date' => 'required|date',
            'category' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'image_upload' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        // Check for validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user = User::where('username', $customer->user->username)->first();

        if ($user) {
            $profileImage = "default_avatar.png";
            if ($request->hasFile('image_upload')) {
                $file = $request->file('image_upload');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('uploads/images', $fileName, 'public'); // Store in public disk

                $profileImage = $fileName; // Save the file path in the database
            }

            // Update the user
            $user->update([
                'profile_picture' => $profileImage,
            ]);
        } else {
            return redirect()->back()->with('error', 'This user does not exist.')->withInput();
        }

        // Update the customer
        $customer->update([
            'person_name' => $request->person_name,
            'urdu_title' => $request->urdu_title,
            'phone_number' => $request->phone_number,
            'date' => $request->date,
            'category' => $request->category,
            'address' => $request->address,
        ]);

        Cache::forget('category_data:customer');
        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($customer, 'customers');

        $dependencies = $this->dependencyCounts([
            'orders' => ['orders', 'customer_id', $customer->id],
            'shipments' => ['shipments', 'customer_id', $customer->id],
            'invoices' => ['invoices', 'customer_id', $customer->id],
            'customer payments' => ['customer_payments', 'customer_id', $customer->id],
            'payment programs' => ['payment_programs', 'customer_id', $customer->id],
            'debit records' => ['dr', 'customer_id', $customer->id],
            'statement adjustments' => fn () => $customer->statementAdjustments()->count(),
            'bank accounts' => fn () => $customer->bankAccounts()->count(),
        ]);

        if ($dependencies !== []) {
            return redirect()->back()->with('error', $this->dependencyBlockMessage('Customer', $dependencies));
        }

        DB::transaction(function () use ($customer) {
            $user = $customer->user;
            $customer->delete();
            $user?->delete();
        });

        Cache::forget('category_data:customer');

        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
    }
}
