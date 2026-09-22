@extends('app')
@section('title', 'Add Customer | ' . $client_company->name)
@section('content')
@php
    $categories_options = [
        'cash' => ['text' => 'Cash'],
        'regular' => ['text' => 'Regular'],
        'site' => ['text' => 'Site'],
        'other' => ['text' => 'Other'],
    ]
@endphp
    <!-- Progress Bar -->
    <div class="mb-5 max-w-3xl mx-auto">
        <x-search-header heading="Add Customer" link linkText="Show Customers" linkHref="{{ route('customers.index') }}"/>
        <x-progress-bar
            :steps="['Enter Details', 'Upload Image']"
            :currentStep="1"
        />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('customers.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-3xl mx-auto  relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Add Customer" />

        <!-- Step 1: Basic Information -->
        <div class="step1 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- customer_name -->
                <x-input
                    label="Customer Name"
                    name="customer_name"
                    id="customer_name"
                    placeholder="Enter supplire name"
                    required
                    capitalized
                    dataValidate="required|friendly"
                />

                <!-- urdu_title -->
                <x-input
                    label="Urdu Title"
                    name="urdu_title"
                    id="urdu_title"
                    placeholder="Enter urdu title"
                    required
                    dataValidate="required|urdu"
                />

                {{-- person name --}}
                <x-input
                    label="Person Name"
                    name="person_name"
                    id="person_name"
                    placeholder="Enter person name"
                    required
                    capitalized
                    dataValidate="required|friendly"
                />

                {{-- customer_registration_date --}}
                <x-input
                    label="Date"
                    name="date"
                    id="date"
                    min="2024-01-01"
                    validateMin
                    max="{{ now()->toDateString() }}"
                    validateMax
                    type="date"
                    required
                />

                {{-- customer_username --}}
                <x-input
                    label="Username"
                    name="username"
                    id="username"
                    type="username"
                    placeholder="Enter username"
                    required
                    data-validate="required|alphanumeric|lowercase|unique:username"
                    data-clean="lowercase|alphanumeric|no-space"
                />

                {{-- customer_password --}}
                <x-input
                    label="Password"
                    name="password"
                    id="password"
                    type="password"
                    placeholder="Enter password"
                    required
                    dataValidate="required|min:4|alphanumeric|lowercase"
                />

                {{-- customer_phone_number --}}
                <x-input
                    label="Phone Number"
                    name="phone_number"
                    id="phone_number"
                    placeholder="Enter phone number"
                    required
                    dataValidate="required|phone"
                />

                {{-- city --}}
                <x-select
                    label="City"
                    name="city"
                    id="city"
                    :options="$cities_options"
                    required
                    showDefault
                />

                {{-- customer_category --}}
                <x-select
                    label="Category"
                    name="category"
                    id="category"
                    :options="$categories_options"
                    required
                    showDefault
                />

                {{-- customer_address --}}
                <x-input
                    label="Address"
                    name="address"
                    id="address"
                    placeholder="Enter address"
                    required
                    capitalized
                    dataValidate="required|friendly"
                />
            </div>
        </div>

        <!-- Step 2: Production Details -->
        <div class="step2 hidden space-y-4">
            <x-file-upload
                id="image_upload"
                name="image_upload"
                placeholder="{{ asset('images/image_icon.png') }}"
                uploadText="Upload Customer's Picture"
            />
        </div>
    </form>

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/customers-create.js') }}"></script>
<script>
        window.__customersCreate = {
            usernames: @json($usernames),
        };
    </script>
@endpush
