@extends('app')
@php
    $isEdit = isset($bankAccount);
@endphp
@section('title', ($isEdit ? 'Edit Bank Account' : 'Add Bank Account') . ' | ' . $client_company->name)
@section('content')
@php
    $categories_options = [
        'self' => ['text' => 'Self'],
        'supplier' => ['text' => 'Supplier'],
        'customer' => ['text' => 'Customer'],
    ];
    $selectedSubCategory = old('sub_category', $isEdit ? $bankAccount->sub_category_id : null);
@endphp
    <div class="max-w-3xl mx-auto">
        <x-search-header :heading="$isEdit ? 'Edit Bank Account' : 'Add Bank Account'" link linkText="Show Bank Accounts" linkHref="{{ route('bank-accounts.index') }}"/>
    </div>
    <!-- Form -->
    <form id="form" action="{{ $isEdit ? route('bank-accounts.update', $bankAccount) : route('bank-accounts.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-3xl mx-auto  relative overflow-hidden">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif
        <x-form-title-bar :title="$isEdit ? 'Edit Bank Account' : 'Add Bank Account'" />

        <!-- Step 1: Basic Information -->
        <div class="step space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- category --}}
                <x-select
                    label="Category"
                    name="category"
                    id="category"
                    :options="$categories_options"
                    :value="old('category', $isEdit ? $bankAccount->category : '')"
                    onchange="getCategoryData(this.value)"
                    required
                    showDefault
                />

                <!-- account_no  -->
                <x-input
                    label="Account No."
                    name="account_no"
                    id="account_no"
                    type="number"
                    :value="old('account_no', $isEdit ? $bankAccount->account_no : '')"
                    placeholder="Enter account no."
                />

                {{-- sub_category --}}
                <x-select
                    label="Disabled"
                    name="sub_category"
                    id="subCategory"
                    :value="$selectedSubCategory"
                    disabled
                    showDefault
                />

                {{-- bank --}}
                <x-select
                    label="Bank"
                    name="bank_id"
                    id="bank"
                    :options="$bank_options"
                    :value="old('bank_id', $isEdit ? $bankAccount->bank_id : '')"
                    required
                    showDefault
                />

                <!-- account_title -->
                <x-input
                    label="Account Title"
                    name="account_title"
                    id="account_title"
                    :value="old('account_title', $isEdit ? $bankAccount->account_title : '')"
                    placeholder="Enter account title"
                    capitalized
                    required
                />

                <!-- date -->
                <x-input
                    label="Date"
                    name="date"
                    id="date"
                    type="date"
                    :value="old('date', $isEdit ? optional($bankAccount->date)->format('Y-m-d') : '')"
                    required
                />

                <!-- remarks -->
                <x-input
                    label="Remarks"
                    name="remarks"
                    id="remarks"
                    :value="old('remarks', $isEdit ? $bankAccount->remarks : '')"
                    placeholder="Enter remerks"
                />

                <!-- Cheque Book Serial Input -->
                <div id="cheque_book_serial" class="form-group">
                    <label for="cheque_book_serial_start" class="block font-medium text-[var(--secondary-text)] mb-2">
                        Cheque Book Serial (Start - End)
                    </label>

                    <div class="flex gap-4">
                        <!-- Start Serial Input -->
                        <input
                            type="number"
                            id="cheque_book_serial_start"
                            name="cheque_book_serial[start]"
                            value="{{ old('cheque_book_serial.start', $isEdit ? $bankAccount->chqbk_serial_start : '') }}"
                            placeholder="Start"
                            class="w-full rounded-lg bg-[var(--h-bg-color)] border-gray-600 text-[var(--text-color)] px-3 py-2 border focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300 ease-in-out"
                        />

                        <!-- End Serial Input -->
                        <input
                            type="number"
                            id="cheque_book_serial_end"
                            name="cheque_book_serial[end]"
                            value="{{ old('cheque_book_serial.end', $isEdit ? $bankAccount->chqbk_serial_end : '') }}"
                            placeholder="End"
                            class="w-full rounded-lg bg-[var(--h-bg-color)] border-gray-600 text-[var(--text-color)] px-3 py-2 border focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300 ease-in-out"
                        />
                    </div>

                    <!-- Error Message -->
                    <div id="cheque_book_serial_error" class="text-[var(--border-error)] text-xs mt-1 hidden"></div>
                </div>
            </div>
        </div>

        <div class="w-full flex justify-end mt-4">
            <button type="submit"
                class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all duration-300 ease-in-out cursor-pointer">
                <i class='fas fa-save mr-1'></i> {{ $isEdit ? 'Update' : 'Save' }}
            </button>
        </div>
    </form>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/bank-accounts-create.js') }}"></script>
<script>
        window.__bankAccountsCreate = {
            csrfToken: @json(csrf_token()),
            selectedCategory: @json(old('category', $isEdit ? $bankAccount->category : '')),
            selectedSubCategory: @json($selectedSubCategory),
        };
    </script>
@endpush
