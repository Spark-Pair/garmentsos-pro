@extends('app')
@section('title', 'Add Article | ' . $client_company->name)
@section('content')
    <!-- Main Content -->
    <!-- header -->
    <div class="mb-5 max-w-5xl mx-auto">
        <x-search-header heading="Add Expense" link linkText="Show Expenses" linkHref="{{ route('expenses.index') }}" />
    </div>

    <div class="row max-w-5xl mx-auto flex gap-4">
        <!-- Form -->
        <form id="form" action="{{ route('expenses.store') }}" method="post" enctype="multipart/form-data"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 grow relative overflow-hidden">
            @csrf
            <x-form-title-bar title="Add Expense" />
            <!-- Step 1: Basic Information -->
            <div class="step1 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- date -->
                    <x-input label="Date" name="date" id="date" validateMin
                        min="2024-01-01" validateMax max="{{ now()->toDateString() }}"
                        type="date" required />

                    {{-- supplier --}}
                    <x-select label="Supplier" name="supplier_id" id="supplier_id" :options="$suppliers_options" required showDefault
                        onchange="supplierSelected(this)" />

                    <!-- balance -->
                    <x-input label="Balance" id="balance" type="number" disabled placeholder="Balance" />

                    {{-- expense --}}
                    <x-select label="Expense" name="expense" id="expense" required showDefault />

                    <!-- reff_no -->
                    <x-input label="Reff. No." name="reff_no" id="reff_no" type="number" placeholder="Enter reff no"
                        required />

                    <!-- amount -->
                    <x-input label="Amount" name="amount" id="amount" type="amount" placeholder="Enter amount " dataValidate="required|amount"
                        required />

                    <!-- lot_no -->
                    <x-input label="Lot No." name="lot_no" id="lot_no" type="number" placeholder="Enter lot no" />

                    {{-- remarks --}}
                    <x-input label="Remarks" name="remarks" id="remarks" type="text" placeholder="Enter remarks" />
                </div>
            </div>

            <div class="w-full flex justify-end mt-4">
                <button type="submit"
                    class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all 0.3s ease-in-out cursor-pointer">
                    <i class='fas fa-save mr-1'></i> Save
                </button>
            </div>
        </form>

        <div
            class="bg-[var(--secondary-bg-color)] rounded-xl shadow-xl p-8 border border-[var(--glass-border-color)]/20 w-[35%] pt-14 relative overflow-hidden fade-in">
            <x-form-title-bar title="Last Record" />

            <!-- Step 1: Basic Information -->
            <div class="step1 space-y-4 ">
                @if ($lastExpense)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- id -->
                        <x-input label="ID No." name="id_no" id="id_no" disabled
                            value="{{ $lastExpense->id }}" />

                        <!-- date -->
                        <x-input label="Date" name="last_date" id="last_date" disabled
                            value="{{ $lastExpense->date->format('d-M-Y, D') }}" />

                        {{-- supplier --}}
                        <x-input label="Supplier" name="last_supplier" id="last_supplier" type="text" disabled
                            value="{{ $lastExpense->supplier->supplier_name }}" />

                        {{-- expense --}}
                        <x-input label="Expense" name="last_expense" id="last_expense" type="text" disabled
                            value="{{ $lastExpense->expenseSetups->title }}" />

                        <!-- reff_no -->
                        <x-input label="Reff. No." name="last_reff_no" id="last_reff_no" type="number" disabled
                            value="{{ $lastExpense->reff_no }}" />

                        <!-- amount -->
                        <x-input label="Amount" name="last_amount" id="last_amount" type="number" disabled
                            value="{{ $lastExpense->amount }}" />

                        <!-- lot_no -->
                        <x-input label="Lot No." name="last_lot_no" id="last_lot_no" type="number" disabled
                            value="{{ $lastExpense->lot_no ?? '-' }}" />

                        {{-- remarks --}}
                        <x-input label="Remarks" name="last_remarks" id="last_remarks" type="text" disabled
                            value="{{ $lastExpense->remarks ?? 'No Remarks' }}" />
                    </div>
                @else
                    <div class="text-center text-gray-500">
                        <p>No last record found.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        const expenseSelect = document.getElementById('expense');
        const balanceInput = document.getElementById('balance');

        function supplierSelected(supplierElem) {
            const selectedOptionDataset = supplierElem.parentElement.parentElement.parentElement?.querySelector('ul li.selected').dataset.option;
            if (selectedOptionDataset) {
                const selectedSupplierData = JSON.parse(selectedOptionDataset);

                balanceInput.value = selectedSupplierData.balance || '0.00';

                const supplierCategories = selectedSupplierData.categories;

                let expenseOptions = `
                    <li data-for="expense" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden">-- Select Expense --</li>
                `;

                supplierCategories.forEach(category => {
                    expenseOptions += `
                        <li data-for="expense" data-value="${category.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">${category.title}</li>
                    `;
                });
                expenseOptions += `
                    <li data-for="expense" data-value="adjustment" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden ">Adjustment</li>
                `;

                expenseSelect.parentElement.parentElement.parentElement.querySelector('ul').innerHTML = expenseOptions;
                expenseSelect.disabled = false;
            } else {
                expenseSelect.innerHTML = '<option value="">-- No options available --</option>';
                expenseSelect.disabled = true;

                balanceInput.value = 'Balance';
            }
        }
    </script>
@endsection
