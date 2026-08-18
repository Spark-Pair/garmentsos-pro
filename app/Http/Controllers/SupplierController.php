<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Setup;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
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
            $suppliers = app(ModuleBranchService::class)->applyScope(Supplier::with('user'), 'suppliers')
                ->orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $suppliers, 'authLayout' => $authLayout]);
        }

        // AJAX request for suppliers options
        // if ($request->ajax()) {
        //     $suppliers_options = Supplier::with([
        //         'payments' => fn($q) => $q
        //             ->where('method', 'program')
        //             ->whereNull('voucher_id')
        //             ->with([
        //                 'program.customer.city'
        //             ]),
        //         'expenses:id,supplier_id,amount,date'
        //     ])
        //     ->whereHas('user', fn($q) => $q->where('status', 'active'))
        //     ->select('id', 'supplier_name', 'date')
        //     ->get()
        //     ->map(function($supplier) {
        //         // Convert to plain array to reduce JSON size
        //         return [
        //             'id' => $supplier->id,
        //             'text' => $supplier->supplier_name,
        //             'data_option' => [
        //                 'id' => $supplier->id,
        //                 'supplier_name' => $supplier->supplier_name,
        //                 'date' => $supplier->date,
        //                 'balance' => $supplier->balance,
        //                 'payments' => $supplier->payments,
        //                 'expenses' => $supplier->expenses,
        //             ]
        //         ];
        //     })
        //     ->keyBy('id')
        //     ->toArray();

        //     return response()->json($suppliers_options);
        // }

        // $suppliers = Supplier::with('user')->orderBy('id', 'desc')->get();

        $supplier_categories = Setup::where('type','supplier_category')->get();

        $categories_options = [];
        foreach ($supplier_categories as $supplier_category) {
            $categories_options[(int)$supplier_category->id] = ['text' => $supplier_category->title];
        }

        return view("suppliers.index", compact( 'categories_options', 'authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $suppliers = Supplier::with('user')->get();
        $supplier_categories = Setup::where('type','supplier_category')->get();

        $categories_options = [];
        foreach ($supplier_categories as $supplier_category) {
            $categories_options[(int)$supplier_category->id] = [
                'text' => $supplier_category->title,
            ];
        }

        $usernames = User::pluck('username')->toArray();

        return view('suppliers.create', compact('categories_options', 'suppliers', 'usernames'));
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
            'supplier_name' => 'required|string|max:255|unique:suppliers,supplier_name',
            'urdu_title' => 'nullable|string|max:255',
            'person_name' => 'required|string|max:255',
            'username' => 'required|string|min:6|max:255|regex:/^[a-z0-9]+$/|unique:users,username',
            'password' => 'required|string|min:3',
            'phone_number' => self::PHONE_RULE,
            'image_upload' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            'date' => 'required|date',
            'categories_array' => 'required|json',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $hashedPassword = Hash::make($data['password']);

        // Create user
        $user = User::where('username', $request->username)->first();
        if (!$user) {
            $data['image'] = "default_avatar.png";

            $user = User::create([
                'name' => $data['supplier_name'],
                'branch_id' => app(ModuleBranchService::class)->branchIdForCreate('users'),
                'username' => $data['username'],
                'password' => $hashedPassword,
                'role' =>'supplier',
                'profile_picture' => $data['image'],
            ]);
        } else {
            return redirect()->back()->with('error', 'This user already exists.')->withInput();
        }

        // Decode category IDs
        $categoryIds = json_decode($data['categories_array'], true);

        // Fetch Setup records
        $setupRecords = Setup::whereIn('id', $categoryIds)->get();

        // Filter relevant categories by title
        $relevantCategories = $setupRecords->filter(function($setup) {
            return in_array($setup->title, ['CMT', 'Cut to Pack', 'Stitching', 'Print', 'Embroidery']);
        });

        // Check if employee with this supplier_name already exists
        if ($relevantCategories->isNotEmpty() && Employee::where('employee_name', $data['supplier_name'])->exists()) {
            $user->delete(); // clean up
            return redirect()->back()->withErrors([
                'supplier_name' => 'An employee with this supplier name already exists.'
            ])->withInput();
        }

        // Create supplier
        $supplier = Supplier::create([
            'user_id' => $user->id,
            'branch_id' => app(ModuleBranchService::class)->branchIdForCreate('suppliers'),
            'supplier_name' => $data['supplier_name'],
            'urdu_title' => $data['urdu_title'],
            'person_name' => $data['person_name'],
            'phone_number' => $data['phone_number'],
            'date' => $data['date'],
            'categories_array' => $data['categories_array'],
        ]);

        // Create employees for relevant categories
        $firstWorkerId = null;
        foreach ($relevantCategories as $category) {
            $type = Setup::where('type', 'worker_type')->where('title', $category->title . ' | E')->first();

            $worker = Employee::create([
                'category' => 'worker',
                'branch_id' => app(ModuleBranchService::class)->branchIdForCreate('employees') ?: $supplier->branch_id,
                'type_id' => $type->id,
                'employee_name' => $supplier->supplier_name,
                'urdu_title' => $supplier->urdu_title,
                'phone_number' => $supplier->phone_number,
                'joining_date' => $supplier->date,
            ]);

            if (!$firstWorkerId) {
                $firstWorkerId = $worker->id;
            }
        }

        if ($firstWorkerId) {
            $supplier->worker_id = $firstWorkerId;
            $supplier->save();
        }

        Cache::forget('category_data:supplier');
        return redirect()->route('suppliers.create')->with('success', 'Supplier created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Supplier $supplier)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
            return $resp;
        }

        $isDeveloper = Auth::user()->role == 'developer' || app_can('suppliers', 'override');

        return view('suppliers.edit', compact('supplier', 'isDeveloper'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Supplier $supplier)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
            return $resp;
        }

        $isDeveloper = Auth::user()->role == 'developer' || app_can('suppliers', 'override');

        $rules = [
            'person_name' => 'required|string|max:255',
            'phone_number' => self::PHONE_RULE,
            'date' => 'required|date',
            'image_upload' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ];

        if ($isDeveloper) {
            $rules = array_merge($rules, [
                'supplier_name' => 'required|string|max:255|unique:suppliers,supplier_name' . $supplier->id,
                'urdu_title' => 'nullable|string|max:255',
            ]);
        }

        $validator = Validator::make($request->all(), $rules);

        // Check for validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user = User::where('username', $supplier->user->username)->first();

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

        $updateData = [
            'person_name' => $request->person_name,
            'phone_number' => $request->phone_number,
            'date' => $request->date,
            'joining_date' => $request->date,
        ];

        if ($isDeveloper) {
            $updateData['supplier_name'] = $request->supplier_name;
            $updateData['urdu_title'] = $request->urdu_title;
        }

        // Update the customer
        $supplier->update($updateData);

        // Update worker's phone if exists
        $worker = $supplier->worker; // assuming hasOne relation
        if ($worker) {
            $worker->update($updateData);
        }

        Cache::forget('category_data:supplier');
        return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($supplier, 'suppliers');

        $dependencies = $this->dependencyCounts([
            'expenses' => ['expenses', 'supplier_id', $supplier->id],
            'supplier payments' => ['supplier_payments', 'supplier_id', $supplier->id],
            'vouchers' => ['vouchers', 'supplier_id', $supplier->id],
            'inventory transactions' => ['inventory_transactions', 'supplier_id', $supplier->id],
            'fabrics' => ['fabrics', 'supplier_id', $supplier->id],
            'credit records' => ['cr', 'supplier_id', $supplier->id],
            'statement adjustments' => fn () => $supplier->statementAdjustments()->count(),
            'payment programs' => fn () => $supplier->paymentPrograms()->count(),
            'bank accounts' => fn () => $supplier->bankAccounts()->count(),
        ]);

        if ($dependencies !== []) {
            return redirect()->back()->with('error', $this->dependencyBlockMessage('Supplier', $dependencies));
        }

        DB::transaction(function () use ($supplier) {
            $user = $supplier->user;
            $supplier->delete();
            $user?->delete();
        });

        Cache::forget('category_data:supplier');

        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted successfully.');
    }
    public function updateSupplierCategory(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        // Validate input first
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'integer|required|exists:suppliers,id',
            'categories_array' => 'required|json',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        Supplier::where('id', $request->supplier_id)->update(['categories_array' => $request->categories_array]);
        Cache::forget('category_data:supplier');

        return redirect()->route('suppliers.index')->with('success', 'Categoies updated successfully');
    }
}
