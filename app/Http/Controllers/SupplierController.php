<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Setup;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $authLayout = $this->getAuthLayout($request->route()->getName());

        if ($request->ajax()) {
            $suppliers = Supplier::with('user')->applyFilters($request)->get()->mapWithKeys(function ($item) {
                return [
                    $item->id => [
                        'id' => $item->id,
                        'image' => $item->user->profile_picture,
                        'name' => $item->supplier_name,
                        'details' => [
                            'Urdu Title' => $item->urdu_title,
                            'Phone' => $item->phone_number,
                            'Balance' => $item->balance,
                        ],
                        'user' => $item->user,
                        'oncontextmenu' => 'generateContextMenu(event)',
                        'onclick' => 'generateModal(this)',
                        'date' => $item->date,
                        'data' => $item,
                        'categories' => $item->Categories,
                        'profile' => true,
                        'visible' => true,
                    ]
                ];
            })->values();
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

        $suppliers = Supplier::with('user')->orderBy('id', 'desc')->get();

        $supplier_categories = Setup::where('type','supplier_category')->get();

        $categories_options = [];
        foreach ($supplier_categories as $supplier_category) {
            $categories_options[(int)$supplier_category->id] = ['text' => $supplier_category->title];
        }

        return view("suppliers.index", compact('suppliers', 'categories_options', 'authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

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
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $validator = Validator::make($request->all(), [
            'supplier_name' => 'required|string|max:255|unique:suppliers,supplier_name',
            'urdu_title' => 'nullable|string|max:255',
            'person_name' => 'required|string|max:255',
            'username' => 'required|string|min:6|max:255|regex:/^[a-z0-9]+$/|unique:users,username',
            'password' => 'required|string|min:3',
            'phone_number' => 'required|string|max:255',
            'image_upload' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            'date' => 'required|string',
            'categories_array' => 'required|json',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->all();
        $data['password'] = Hash::make($data['password']);

        // Create user
        $user = User::where('username', $request->username)->first();
        if (!$user) {
            $data['image'] = "default_avatar.png";

            $user = User::create([
                'name' => $data['supplier_name'],
                'username' => $data['username'],
                'password' => $data['password'],
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
        if(!$this->checkRole(['developer', 'owner', 'admin']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        return view('suppliers.edit', compact('supplier'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Supplier $supplier)
    {if(!$this->checkRole(['developer', 'owner', 'admin']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|max:255',
            'image_upload' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        // Check for validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->all();

        $user = User::where('username', $supplier->user->username)->first();

        if ($user) {
            if ($request->hasFile('image_upload')) {
                $file = $request->file('image_upload');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs('uploads/images', $fileName, 'public'); // Store in public disk

                $data['image'] = $fileName; // Save the file path in the database
            } else {
                $data['image'] = "default_avatar.png";
            }

            // Update the user
            $user->update([
                'profile_picture' => $data['image'],
            ]);
        } else {
            return redirect()->back()->with('error', 'This user does not exist.')->withInput();
        }

        // Update the customer
        $supplier->update([
            'phone_number' => $data['phone_number'],
        ]);

        // Update worker's phone if exists
        $worker = $supplier->worker; // assuming hasOne relation
        if ($worker) {
            $worker->update([
                'phone_number' => $data['phone_number'],
            ]);
        }

        return redirect()->route('suppliers.index')->with('success', 'Supplier updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        //
    }
    public function updateSupplierCategory(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        // Validate input first
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'integer|required|exists:suppliers,id',
            'categories_array' => 'required|json',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->all();

        Supplier::where('id', $request->supplier_id)->update(['categories_array' => $data['categories_array']]);

        return redirect()->route('suppliers.index')->with('success', 'Categoies updated successfully');
    }
}
