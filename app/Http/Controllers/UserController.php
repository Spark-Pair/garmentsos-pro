<?php

namespace App\Http\Controllers;

use App\Events\NewNotificationEvent;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $authLayout = $this->getAuthLayout($request->route()->getName());

        if ($request->ajax()) {
            $users = User::whereNotIn('role', ['supplier', 'customer', 'developer'])
                ->orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $users, 'authLayout' => $authLayout]);
        }

        // $users = User::whereNotIn('role', ['supplier', 'customer', 'developer'])->limit(1)->get();
        // return view("user.index", compact('users', 'authLayout'));
        return view("user.index", compact( 'authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if(!$this->checkRole(['developer', 'owner', 'manager', 'admin']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $usernames = User::pluck('username')->toArray();
        return view('user.create', compact('usernames'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'manager', 'admin']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        // Validation rules
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:4',
            'role' => 'required|string|in:admin,accountant,guest,owner,store_keeper',
            'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        // If validation fails, return with errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->all();
        $data['password'] = Hash::make($data['password']); // Hash the password

        // Handle the image upload if present
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads/images', $fileName, 'public'); // Store in public disk

            $data['profile_picture'] = $fileName; // Save the file path in the database
        }

        User::create($data);
        return redirect()->back()->with('success', 'User added successfully! You can now manage their details.'); // Redirect to dashboard
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        //
    }

    public function updateStatus(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'manager', 'admin']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $user = User::find($request->user_id);

        if ($request->status == 'active') {
            if ($user->id != Auth::id()) {
                $user->status = 'in_active';
                $user->save();

                $userSession = UserSession::where('user_id', $user->id)->latest()->first();
                if ($userSession) {
                    $userSession->delete();
                }

                if (app('pusher.enabled')) {
                    try {
                        event(new NewNotificationEvent([
                            'title' => 'Your Account Has Been Deactivated',
                            'message' => 'Your account is now inactive. Please contact admin for details.',
                            'id' => $user->id,
                            'type' => 'user_inactivated' // ✅ easy condition check
                        ]));
                    } catch (\Exception $e) {
                        return redirect()->back()->with('warning', "Status updated, but the user can't be logged out.");
                    }
                }
            } else {
                return redirect()->back()->with('error', 'Oops! You cannot deactivate yourself.');
            }
        } else {
            $user->status = 'active';
            $user->save();
        }
        return redirect()->back()->with('success', 'Status has been updated successfully!');
    }

    public function resetPassword(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'admin']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'password' => 'required|string|min:4',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $user = User::find($request->user_id);

        $user->password = Hash::make($request->password);
        $user->save();

        $userSession = UserSession::where('user_id', $user->id)->latest()->first();
        if ($userSession) {
            $userSession->delete();
        }

        if (app('pusher.enabled')) {
            try {
                event(new NewNotificationEvent([
                    'title' => 'Your Password Has Been Reset',
                    'message' => 'Your password was reset by ' . Auth::user()->name . '. Please use the new password to login.',
                    'id' => $user->id,
                    'type' => 'password_reset' // ✅ easy condition check
                ]));
            } catch (\Exception $e) {
                return redirect()->back()->with('warning', "Status updated, but the user can't be logged out.");
            }
        }

        return redirect()->back()->with('success', 'Password reset successfully!');
    }
}
