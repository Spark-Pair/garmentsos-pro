@extends('app')
@section('title', 'Add Employee | ' . $client_company->name)
@section('content')
@php
    $categories_options = [
        'staff' => ['text' => 'Staff'],
        'worker' => ['text' => 'Worker'],
    ]
@endphp
    <!-- Progress Bar -->
    <div class="mb-5 max-w-3xl mx-auto">
        <x-search-header heading="Add Employee" link linkText="Show Employees" linkHref="{{ route('employees.index') }}"/>
        <x-progress-bar :steps="['Enter Details', 'Upload Image']" :currentStep="1" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('employees.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-3xl mx-auto  relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Add Employee" />

        <!-- Step1 : Basic Information -->
        <div class="step1 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- employee_category --}}
                <x-select
                    label="Category"
                    name="category"
                    id="category"
                    :options="$categories_options"
                    onchange="trackCategoryChange()"
                    required
                    showDefault
                />

                {{-- employee_type --}}
                <x-select
                    label="Type"
                    name="type_id"
                    id="type"
                    required
                />

                <!-- employee_name -->
                <x-input
                    label="Employee Name"
                    name="employee_name"
                    id="employee_name"
                    placeholder="Enter employee name"
                    required
                    capitalized
                    dataValidate="required|letters"
                />

                <!-- urdu_title -->
                <x-input
                    label="Urdu Title"
                    name="urdu_title"
                    id="urdu_title"
                    placeholder="Enter urdu title"
                />

                {{-- employee_phone_number --}}
                <x-input
                    label="Phone Number"
                    name="phone_number"
                    id="phone_number"
                    placeholder="Enter phone number"
                    required
                />

                {{-- employee_joining_date --}}
                <x-input
                    label="Joining Date"
                    name="joining_date"
                    id="joining_date"
                    min="2024-01-01"
                    validateMin
                    max="{{ now()->toDateString() }}"
                    validateMax
                    type="date"
                    required
                />

                {{-- employee_cnic --}}
                <x-input
                    label="C.N.I.C No."
                    name="cnic_no"
                    id="cnic_no"
                    placeholder="Enter C.N.I.C No."
                    capitalized
                />

                {{-- employee_salary --}}
                <x-input
                    label="Salary"
                    name="salary"
                    id="salary"
                    placeholder="Enter salary"
                    type="amount"
                    dataValidate="amount"
                    disabled
                    capitalized
                />
            </div>
        </div>

        <!-- Step 2: Production Details -->
        <div class="step2 hidden space-y-6 ">
            <x-file-upload id="profile_picture" name="profile_picture" placeholder="{{ asset('images/image_icon.png') }}"
                uploadText="Upload Profile Picture" />
        </div>
    </form>

    <script>
        function formatPhoneNo(input) {
            let value = input.value.replace(/\D/g, ''); // Remove all non-numeric characters

            if (value.length > 4) {
                value = value.slice(0, 4) + '-' + value.slice(4, 11); // Insert hyphen after 4 digits
            }

            input.value = value; // Update the input field
        }

        document.getElementById('phone_number').addEventListener('input', function() {
            formatPhoneNo(this);
        });

        function formatCnicNo(input) {
            let value = input.value.replace(/\D/g, ''); // Remove all non-numeric characters

            if (value.length > 5 && value.length <= 12) {
                value = value.slice(0, 5) + '-' + value.slice(5);
            }
            if (value.length > 12) {
                value = value.slice(0, 5) + '-' + value.slice(5, 12) + '-' + value.slice(12, 13);
            }

            input.value = value; // Update the input field
        }

        document.getElementById('cnic_no').addEventListener('input', function () {
            formatCnicNo(this);
        });


        const allTypes = @json($all_types);
        const categorySelectDom = document.getElementById('category');
        const typeSelectDom = document.getElementById('type');
        const salaryInpDom = document.getElementById('salary');
        const salaryLabelDom = document.querySelector(`label[for="${salaryInpDom.id}"]`);

        function trackCategoryChange() {
            let clutter = '';
            if (categorySelectDom.value == 'Staff') {
                const typeArray = allTypes.staff_type

                if (typeArray.length > 0) {
                    clutter = `
                        <li data-for="type" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)]">
                            -- Select Type --
                        </li>
                    `;
                    typeSelectDom.disabled = false;
                }

                salaryInpDom.disabled = false;
                salaryInpDom.required = true;
                salaryLabelDom.textContent = "Salary"

                typeArray.forEach(type => {
                    clutter += `
                        <li data-for="type" data-value="${type.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">
                            ${type.title}
                        </li>
                    `;
                });
            } else if (categorySelectDom.value == 'Worker') {
                const typeArray = allTypes.worker_type

                if (typeArray.length > 0) {
                    clutter = `
                        <li data-for="type" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] selected">
                            -- Select Type --
                        </li>
                    `;
                    typeSelectDom.disabled = false;
                }

                salaryInpDom.disabled = true;
                salaryInpDom.required = false;
                salaryLabelDom.textContent = "Salary"

                typeArray.forEach(type => {
                    clutter += `
                        <li data-for="type" data-value="${type.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">
                            ${type.title}
                        </li>
                    `;
                });
            } else {
                salaryInpDom.disabled = true;
                salaryInpDom.required = false;
                salaryLabelDom.textContent = "Salary"
                clutter = `
                    <li data-for="type" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] selected">
                        -- No options available --
                    </li>
                `;
                typeSelectDom.disabled = true;
            }

            typeSelectDom.parentElement.parentElement.parentElement.querySelector('ul').innerHTML = clutter;
            selectThisOption(typeSelectDom.parentElement.parentElement.parentElement.querySelector('ul li'));
        }

        function validateForNextStep() {
            formatAmountInput(document.querySelector('#salary'));
            return true;
        }
    </script>
@endsection
