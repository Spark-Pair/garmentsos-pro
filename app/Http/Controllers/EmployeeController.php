<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Setup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends Controller
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
            $employees = Employee::whereHas('type', function ($query) {
                    $query->where('title', 'not like', '% | E%');
                })->orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $employees, 'authLayout' => $authLayout]);
        }

        // $employees = Employee::with('type')
        //     ->whereHas('type', function ($query) {
        //         $query->where('title', 'not like', '% | E%');
        //     })
        //     ->get();

        $staff = Setup::where('type', 'staff_type')->get()
            ->mapWithKeys(fn ($type) => [
                $type->id => [
                    'text' => $type->title,
                    'category' => 'staff',
                ],
            ])
            ->all();

        $worker = Setup::where('type', 'worker_type')->get()
            ->mapWithKeys(fn ($type) => [
                $type->id => [
                    'text' => $type->title,
                    'category' => 'worker',
                ],
            ])
            ->all();

        $all_types = $staff + $worker;

        return view("employees.index", compact('authLayout', 'all_types'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $all_types = [];

        $staff_types = Setup::where('type', 'staff_type')->get();
        $worker_types = Setup::workerTypesNotE()->get();

        $all_types['staff_type'] = $staff_types;
        $all_types['worker_type'] = $worker_types;

        return view('employees.create', compact('all_types'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!$this->checkRole(['developer', 'owner', 'admin', 'accountant'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $request->validate([
            'category' => 'required|string|in:staff,worker',
            'type_id' => 'required|exists:setups,id',
            'employee_name' => 'required|string|unique:employees,employee_name',
            'urdu_title' => 'nullable|string',
            'phone_number' => 'required|string',
            'joining_date' => 'required|date',
            'cnic_no' => 'nullable|string',
            'salary' => 'nullable|integer|min:1',
            'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        $data = $request->all();

        // Handle the image upload if present
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads/images', $fileName, 'public'); // Store in public disk

            $data['profile_picture'] = $fileName; // Save the file path in the database
        }

        Employee::create($data);

        return redirect()->route('employees.create')->with('success', 'Employee added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee)
    {
        if (!$this->checkRole(['developer', 'owner', 'admin'])) {
            return redirect()->back()->with('error', 'You do not have permission to access this page.');
        };

        $category = $employee->category == 'staff' ? ['staff_type'] : ['worker_type'];
        $types = Setup::whereIn('type', $category)->get();
        $types_options = $types->mapWithKeys(fn($type) => [
            $type->id => ['text' => $type->title]
        ])->toArray();
        return view('employees.edit', compact('employee', 'types_options'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Employee $employee)
    {
        if (!$this->checkRole(['developer', 'owner', 'admin'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $validator = Validator::make($request->all(), [
            'type_id' => 'required|integer|exists:setups,id',
            'urdu_title' => 'nullable|string|max:255',
            'phone_number' => 'required|string|max:255',
            'cnic_no' => 'nullable|string|max:255',
            'salary' => 'nullable|integer|min:1',
            'image_upload' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        // Check for validation errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->all();

        if ($request->hasFile('image_upload')) {
            $file = $request->file('image_upload');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads/images', $fileName, 'public'); // Store in public disk

            $data['profile_picture'] = $fileName; // Save the file path in the database
        } else {
            $data['profile_picture'] = "default_avatar.png";
        }

        // return $data;

        // Update the employee
        $employee->update($data);

        return redirect()->route('employees.index')->with('success', 'Employee updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        //
    }

    public function updateStatus(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'manager', 'admin']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $employee = Employee::find($request->user_id);

        if ($request->status == 'active') {
            $employee->status = 'in_active';
            $employee->save();
        } else {
            $employee->status = 'active';
            $employee->save();
        }
        return redirect()->back()->with('success', 'Status has been updated successfully!');
    }
}
