@extends('app')
@section('title', 'Edit Article | ' . $client_company->name)
@section('content')
    <!-- Main Content -->
    <!-- header -->
    <div class="mb-5 max-w-3xl mx-auto">
        <x-search-header heading="Edit Expense" link linkText="Show Expenses" linkHref="{{ route('expenses.index') }}" />
    </div>

    <div class="row max-w-3xl mx-auto flex gap-4">
        <!-- Form -->
        <form id="form" action="{{ route('expenses.update', ['expense' => $expense->id]) }}" method="post" enctype="multipart/form-data"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 grow relative overflow-hidden">
            @csrf
            @method('PUT')
            <x-form-title-bar title="Edit Expense" />
            <!-- Step 1: Basic Information -->
            <div class="step1 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- date -->
                    <x-input label="Date" id="date" value="{{ $expense->date->format('d-M-Y, D') }}" disabled />

                    <!-- supplier -->
                    <x-input label="Supplier" id="supplier_name" value="{{ $expense->supplier->supplier_name }}" disabled />
                    <input type="hidden" id="supplier" value='{{ json_encode($expense->supplier) }}' />

                    <!-- balance -->
                    <x-input label="Balance" id="balance" disabled value="{{ $expense->supplier->balance }}" />

                    {{-- expense --}}
                    <x-select label="Expense" name="expense" id="expense" required />

                    <!-- reff_no -->
                    <x-input label="Reff. No." name="reff_no" id="reff_no" type="number" placeholder="Enter reff no" required value="{{ $expense->reff_no }}" />

                    <!-- amount -->
                    <x-input label="Amount" name="amount" id="amount" type="amount" placeholder="Enter amount" required value="{{ $expense->amount }}" dataValidate="required|amount"/>

                    <!-- lot_no -->
                    <x-input label="Lot No." name="lot_no" id="lot_no" type="number" placeholder="Enter lot no" value="{{ $expense->lot_no }}" />

                    {{-- remarks --}}
                    <x-input label="Remarks" name="remarks" id="remarks" type="text" placeholder="Enter remarks" value="{{ $expense->remarks }}" />
                </div>
            </div>

            <div class="w-full flex justify-end mt-4">
                <button type="submit"
                    class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all 0.3s ease-in-out cursor-pointer">
                    <i class='fas fa-save mr-1'></i> Update
                </button>
            </div>
        </form>
    </div>

    <script>
        const selectedExpense = "{{ $expense->expense }}";

        function supplierSelected(supplier) {
            const expenseSelect = document.getElementById('expense');
            const selectedSupplierData = JSON.parse(supplier);

            const supplierCategories = selectedSupplierData.categories;

            let expenseOptions = `
                <li data-for="expense" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">-- Select Expense --</li>
            `;

            supplierCategories.forEach(category => {
                expenseOptions += `
                    <li data-for="expense" data-value="${category.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">${category.title}</li>
                `;
            });
            expenseOptions += `
                <li data-for="expense" data-value="adjustment" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">Adjustment</li>
            `;

            expenseSelect.parentElement.parentElement.parentElement.querySelector('ul').innerHTML = expenseOptions;
            expenseSelect.disabled = false;
        }

        supplierSelected(document.getElementById('supplier').value);

        window.onload = function () {
            selectThisOption(
                document.getElementById("expense")
                    .parentElement.parentElement.parentElement
                    .querySelector(`ul li[data-value="${selectedExpense}"]`)
            );
        };
    </script>
@endsection
