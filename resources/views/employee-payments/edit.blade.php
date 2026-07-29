@extends('app')
@section('title', 'Edit Employee Payment | ' . $client_company->name)
@section('content')
    @php
        $category_options = [
            'staff' => ['text' => 'Staff'],
            'worker' => ['text' => 'Worker'],
        ];
        $method_options = [
            'cash' => ['text' => 'Cash'],
            'adjustment' => ['text' => 'Adjustment'],
        ];
        $employee = $employeePayment->employee;
        $category = old('category', $employee?->category);
    @endphp

    <div class="mb-5 max-w-4xl mx-auto">
        <x-search-header heading="Edit Employee Payment" link linkText="Show Payments" linkHref="{{ route('employee-payments.index') }}"/>
    </div>

    <div class="row max-w-4xl mx-auto flex gap-4">
        <form id="form" action="{{ route('employee-payments.update', ['employee_payment' => $employeePayment->id]) }}" method="post"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-7 border border-[var(--h-bg-color)] pt-12 mx-auto relative overflow-hidden w-full">
            @csrf
            @method('PUT')
            <x-form-title-bar title="Edit Employee Payment" />

            <div class="step space-y-4 overflow-y-auto max-h-[65vh] p-1 my-scrollbar-2">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-select
                        label="Category"
                        name="category"
                        id="category"
                        :options="$category_options"
                        required
                        showDefault
                        onchange="trackCategoryState(this)"
                    />

                    <x-select
                        label="Employee Name"
                        name="employee_id"
                        id="employee"
                        required
                        showDefault
                        onchange="trackEmployeeState(this)"
                    />

                    <x-input label="Balance" placeholder="Select employee first" name="balance" type="amount" id="balance" dataValidate="amount" disabled />

                    <x-input label="Date" name="date" id="date" type="date" validateMax max="{{ now()->toDateString() }}" required value="{{ old('date', $employeePayment->date?->format('Y-m-d')) }}" />

                    <x-select
                        label="Method"
                        name="method"
                        id="method"
                        :options="$method_options"
                        required
                        showDefault
                    />

                    <x-input label="Amount" name="amount" id="amount" type="amount" required dataValidate="required|amount" value="{{ old('amount', \App\Support\Money::format($employeePayment->amount)) }}" placeholder="Enter amount" />
                </div>
            </div>
            <div class="w-full flex justify-end mt-4">
                <button type="submit"
                    class="px-10 py-2 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] hover:border-[var(--border-success)] hover:scale-90 transition-all duration-300 ease-in-out cursor-pointer">
                    <i class='fas fa-save mr-1'></i> Save
                </button>
            </div>
        </form>
    </div>
@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/employee-payments-create.js') }}"></script>
<script>
    window.__employeePaymentEdit = @json([
        'category' => $category,
        'employee_id' => old('employee_id', $employeePayment->employee_id),
        'method' => old('method', $employeePayment->method),
    ]);

    document.addEventListener('DOMContentLoaded', () => {
        const config = window.__employeePaymentEdit || {};
        const categoryOption = document.querySelector(`li[data-for="category"][data-value="${config.category}"]`);
        if (categoryOption) {
            selectThisOption(categoryOption);
        }
    });
</script>
@endpush
