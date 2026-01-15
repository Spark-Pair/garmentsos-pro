<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Setup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!$this->checkRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $authLayout = $this->getAuthLayout($request->route()->getName());

        if ($request->ajax()) {
            $customers = Customer::orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $customers, 'authLayout' => $authLayout]);
        }

        $cities_options = [];
        $allCities = Setup::where('type', 'city')->get();

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
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $cities_options = [];
        $allCities = Setup::where('type', 'city')->get();

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
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $validator = Validator::make($request->all(), [
            'customer_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('customers', 'customer_name')
                    ->where(function ($query) use ($request) {
                        return $query->where('city_id', $request->city);
                    }),
            ],
            'person_name' => 'required|string|max:255',
            'urdu_title' => 'nullable|string|max:255',
            'username' => 'required|string|min:6|max:255|regex:/^[a-z0-9]+$/|unique:users,username',
            'password' => 'required|string|min:3',
            'phone_number' => 'required|string|max:255',
            'image_upload' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
            'date' => 'required|string',
            'category' => 'required|string|max:255',
            'city' => 'required|integer|exists:setups,id',
            'address' => 'required|string|max:255',
        ]);

        // Check for validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->all();
        $data['password'] = Hash::make($data['password']); // Hash the password

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
                'username' => $data['username'],
                'password' => $data['password'],
                'role' => 'customer',
                'profile_picture' => $data['image'],
            ]);
        } else {
            return redirect()->back()->with('error', 'This user already exists.')->withInput();
        }

        // Create a new customer
        $customer = Customer::create([
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'person_name' => $data['person_name'],
            'phone_number' => $data['phone_number'],
            'urdu_title' => $data['urdu_title'],
            'date' => $data['date'],
            'category' => $data['category'],
            'city_id' => $data['city'],
            'address' => $data['address'],
        ]);

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
        if (!$this->checkRole(['developer', 'owner', 'admin'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        return view('customers.edit', compact('customer'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        if (!$this->checkRole(['developer', 'owner', 'admin'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $validator = Validator::make($request->all(), [
            'person_name' => 'required|string|max:255',
            'urdu_title' => 'nullable|string|max:255',
            'phone_number' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'image_upload' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        // Check for validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->all();

        $user = User::where('username', $customer->user->username)->first();

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
        $customer->update([
            'person_name' => $data['person_name'],
            'urdu_title' => $data['urdu_title'],
            'phone_number' => $data['phone_number'],
            'category' => $data['category'],
            'address' => $data['address'],
        ]);

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        //
    }
}
