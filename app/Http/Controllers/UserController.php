<?php

namespace App\Http\Controllers;

use App\Events\NewNotificationEvent;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        $authLayout = $this->getAuthLayout($request->route()->getName()) ?? 'grid';

        if ($request->ajax()) {
            $users = app(ModuleBranchService::class)->applyScope(User::whereNotIn('role', ['supplier', 'customer', 'developer']), 'users')
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
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin'])) {
            return $resp;
        }

        $usernames = User::pluck('username')->toArray();
        return view('user.create', compact('usernames'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin'])) {
            return $resp;
        }

        // Validation rules
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|string|min:4',
            'role' => ['required', 'string', Rule::in($this->assignableRoles())],
            'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        // If validation fails, return with errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $hashedPassword = Hash::make($data['password']); // Hash the password

        // Handle the image upload if present
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads/images', $fileName, 'public'); // Store in public disk

            $data['profile_picture'] = $fileName; // Save the file path in the database
        }

        User::create([
            'name' => $data['name'],
            'branch_id' => app(ModuleBranchService::class)->branchIdForCreate('users'),
            'username' => $data['username'],
            'password' => $hashedPassword,
            'role' => $data['role'],
            'profile_picture' => $data['profile_picture'] ?? 'default_avatar.png',
        ]);
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
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($user, 'users');

        $usernames = User::whereKeyNot($user->id)->pluck('username')->toArray();

        return view('user.create', compact('usernames', 'user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($user, 'users');

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id)],
            'password' => 'nullable|string|min:4',
            'role' => ['required', 'string', Rule::in($this->assignableRoles())],
            'status' => 'required|string|in:active,in_active',
            'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();

        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('uploads/images', $fileName, 'public');
            $data['profile_picture'] = $fileName;
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        if ($user->status !== 'active') {
            UserSession::where('user_id', $user->id)->update([
                'is_active' => false,
                'last_activity' => now(),
            ]);
        }

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'You cannot delete your own account while logged in.');
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($user, 'users');

        UserSession::where('user_id', $user->id)->delete();
        $user->delete();

        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    public function updateStatus(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin'])) {
            return $resp;
        }

        $targetUser = User::find($request->user_id);
        if (!$targetUser) {
            return redirect()->back()->with('error', 'User not found.');
        }

        $authUser = Auth::user();
        $authRole = $authUser->role;
        $targetRole = $targetUser->role;

        // Nobody can deactivate themselves.
        if ($targetUser->id === $authUser->id && $request->status == 'active') {
            return redirect()->back()->with('error', 'Oops! You cannot deactivate yourself.');
        }

        // Guard critical roles from deactivation:
        // - owner cannot deactivate developer or self
        // - admin cannot deactivate owner, developer, or self
        if ($request->status == 'active') {
            if ($authRole === 'owner' && $targetRole === 'developer') {
                return redirect()->back()->with('error', 'Owner cannot deactivate developer.');
            }

            if ($authRole === 'admin' && in_array($targetRole, ['owner', 'developer'], true)) {
                return redirect()->back()->with('error', 'Admin cannot deactivate owner or developer.');
            }
        }

        if ($request->status == 'active') {
            $targetUser->status = 'in_active';
            $targetUser->save();

            UserSession::where('user_id', $targetUser->id)->update([
                'is_active' => false,
                'last_activity' => now(),
            ]);

            if (app('pusher.enabled')) {
                try {
                    event(new NewNotificationEvent([
                        'title' => 'Your Account Has Been Deactivated',
                        'message' => 'Your account is now inactive. Please contact admin for details.',
                        'id' => $targetUser->id,
                        'type' => 'user_inactivated' // ✅ easy condition check
                    ]));
                } catch (\Exception $e) {
                    return redirect()->back()->with('warning', "Status updated, but the user can't be logged out.");
                }
            }
        } else {
            $targetUser->status = 'active';
            $targetUser->save();
        }
        return redirect()->back()->with('success', 'Status has been updated successfully!');
    }

    public function resetPassword(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin'])) {
            return $resp;
        }

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

        UserSession::where('user_id', $user->id)->update([
            'is_active' => false,
            'last_activity' => now(),
        ]);

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

    private function assignableRoles(): array
    {
        return ['admin', 'accountant', 'guest', 'owner', 'store_keeper'];
    }
}
