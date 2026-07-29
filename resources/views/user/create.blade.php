@php
    $authUser = Auth::user();
    $isEdit = isset($user);

    $roleOptions = [
        'guest' => ['text' => 'Guest'],
        'accountant' => ['text' => 'Accountant'],
        'store_keeper' => ['text' => 'Store Keeper '],
    ];

    if ($authUser->role == 'developer') {
        $roleOptions['admin'] = ['text' => 'Admin'];
        $roleOptions['owner'] = ['text' => 'Owner'];
    }

    if ($authUser->role == 'owner') {
        $roleOptions['admin'] = ['text' => 'Admin'];
    }
@endphp

@extends('app')
@section('title', ($isEdit ? 'Edit User' : 'Add User') . ' | ' . $client_company->name)
@section('content')
    <div class="mb-5 max-w-3xl mx-auto fade-in">
        <x-search-header heading="{{ $isEdit ? 'Edit User' : 'Add User' }}" link linkText="Show Users" linkHref="{{ route('users.index') }}"/>
        <x-progress-bar :steps="['Enter Details', 'Upload Image']" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ $isEdit ? route('users.update', $user) : route('users.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] rounded-xl shadow-lg p-8 border border-[var(--h-bg-color)] pt-12 max-w-3xl mx-auto relative overflow-hidden">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif
        <x-form-title-bar title="{{ $isEdit ? 'Edit User' : 'Add User' }}" />
        <!-- Step 1: Basic Information -->
        <div class="step1 space-y-6 ">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Name --}}
                <x-input label="Name" name="name" id="name" placeholder="Enter name" value="{{ old('name', $user->name ?? '') }}" required capitalized dataValidate="required|friendly" />

                <x-input
                    label="Username"
                    name="username"
                    id="username"
                    placeholder="Enter username"
                    value="{{ old('username', $user->username ?? '') }}"
                    required
                    data-validate="required|alphanumeric|lowercase|unique:username"
                    data-clean="lowercase|alphanumeric|no-space"
                />

                {{-- Password --}}
                <x-input label="Password" name="password" id="password" type="password" placeholder="{{ $isEdit ? 'Leave blank to keep current password' : 'Enter password' }}" :required="!$isEdit" dataValidate="{{ $isEdit ? 'min:4|alphanumeric|lowercase' : 'required|min:4|alphanumeric|lowercase' }}" />

                {{-- Role --}}
                <x-select label="Role" name="role" id="role" :options="$roleOptions" value="{{ old('role', $user->role ?? '') }}" />

                @if($isEdit)
                    <x-select label="Status" name="status" id="status" :options="['active' => ['text' => 'Active'], 'in_active' => ['text' => 'Inactive']]" value="{{ old('status', $user->status) }}" required />
                @endif
            </div>
        </div>

        <!-- Step 2: Production Details -->
        <div class="step2 hidden space-y-6 ">
            <x-file-upload id="profile_picture" name="profile_picture" placeholder="{{ asset('images/image_icon.png') }}"
                uploadText="Upload Profile Picture" />
        </div>
    </form>

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/users-create.js') }}"></script>
<script>
        window.__usersCreate = {
            usernames: @json($usernames),
        };
    </script>
@endpush
