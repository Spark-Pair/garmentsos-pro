@php
    $authUser = Auth::user();
@endphp

@extends('app')
@section('title', 'Sales Return | ' . $client_company->name)
@section('content')
    <div class="mb-5 max-w-3xl mx-auto fade-in">
        <x-search-header heading="Sales Return" link linkText="Show Returns" linkHref="{{ route('sales-returns.index') }}"/>
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('sales-returns.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] rounded-xl shadow-lg p-8 border border-[var(--h-bg-color)] pt-12 max-w-3xl mx-auto relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Sales Return" />
        <!-- Step 1: Basic Information -->
        <div class="step1 space-y-6 ">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Customer --}}
                <x-select label="Customer" name="customer_id" id="customer" :options="$customerOptions" showDefault onchange="onCustomerSelect(this)" />

                {{-- Article --}}
                <x-select label="Article" name="article_id" id="article" :options="[]" showDefault disabled onchange="onArticleSelect(this)" />

                {{-- Invoice --}}
                <div class="col-span-2">
                    <x-select label="Invoice" name="invoice_id" id="invoice" :options="[]" showDefault disabled onchange="onInvoiceSelect(this)" />
                </div>

                <div class="grid grid-cols-3 col-span-full gap-4">
                    {{-- Date --}}
                    <x-input label="Date" name="date" id="date" type="date" max="{{ now()->toDateString() }}" required disabled />

                    {{-- Quantity --}}
                    <x-input label="Quantity" name="quantity" id="quantity" type="number" placeholder="Enter quantity" oninput="onQuantityInput(this)" required disabled dataValidate="required|numeric" />

                    {{-- Amount --}}
                    <x-input label="Amount" name="amount" id="amount" type="amount" placeholder="Amount" readonly dataValidate="required|amount" />
                </div>
            </div>
        </div>
        <div class="w-full flex justify-end mt-4">
            <button type="submit"
                class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all 0.3s ease-in-out cursor-pointer">
                <i class='fas fa-save mr-1'></i> Save
            </button>
        </div>
    </form>
    <script>
        let selectedInvoice = {};
        let selectedArticleId = 0;
        function onCustomerSelect(selectElement) {
            const selectedCustomerId = selectElement.value;
            if (selectedCustomerId) {
                $.ajax({
                    url: "{{ route('sales-returns.get-details') }}",
                    type: 'POST',
                    data: {
                        customer_id: selectedCustomerId,
                        getArticles: true,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        const articleSelect = document.getElementById('article');
                        articleSelect.disabled = false;

                        const articleSelectDropdown = articleSelect.parentElement.parentElement.parentElement.querySelector('.optionsDropdown');
                        articleSelectDropdown.innerHTML = '';
                        let clutter = '<li data-for="article" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)]" >-- Select Article --</li>';
                        response.forEach(article => {
                            clutter += `<li data-for="article" data-value="${article.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden hidden">${article.article_no}</li>`;
                        });
                        articleSelectDropdown.innerHTML = clutter;

                        const firstOption = articleSelectDropdown.querySelector('li');
                        if (firstOption) {
                            selectThisOption(firstOption);
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching details:', xhr);
                    }
                });
            } else {
                const articleSelect = document.getElementById('article');
                articleSelect.disabled = true;
            }
        }

        function onArticleSelect(selectElement) {
            selectedArticleId = parseInt(selectElement.value);
            const customerId = document.querySelector('.dbInput[data-for="customer"]').value;

            if (selectedArticleId && customerId) {
                $.ajax({
                    url: "{{ route('sales-returns.get-details') }}",
                    type: 'POST',
                    data: {
                        customer_id: customerId,
                        article_id: selectedArticleId,
                        getInvoices: true,
                        _token: '{{ csrf_token() }}'
                    },
                    success: function(response) {
                        const invoiceSelect = document.getElementById('invoice');
                        invoiceSelect.disabled = false;

                        const invoiceSelectDropdown = invoiceSelect.parentElement.parentElement.parentElement.querySelector('.optionsDropdown');
                        invoiceSelectDropdown.innerHTML = '';
                        let clutter = '<li data-for="invoice" data-value="" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)]" >-- Select Invoice --</li>';
                        response.forEach(invoice => {
                            clutter += `<li data-for="invoice" data-invoice-data='${JSON.stringify(invoice)}' data-value="${invoice.id}" onmousedown="selectThisOption(this)" class="py-2 px-3 cursor-pointer rounded-lg transition hover:bg-[var(--h-bg-color)] text-nowrap overflow-x-auto scrollbar-hidden hidden">${invoice.invoice_no} | ${invoice.articles_in_invoice[0].invoice_quantity} - PCs | ${invoice.discount}% | Rs. ${invoice.sales_rate}</li>`;
                        });
                        invoiceSelectDropdown.innerHTML = clutter;

                        const firstOption = invoiceSelectDropdown.querySelector('li');
                        if (firstOption) {
                            selectThisOption(firstOption);
                        }
                    },
                    error: function(xhr) {
                        console.error('Error fetching details:', xhr);
                    }
                });
            } else {
                const invoiceSelect = document.getElementById('invoice');
                invoiceSelect.disabled = true;
            }
        }

        function onInvoiceSelect(selectElement) {
            if (selectElement.value) {
                const invoiceData = JSON.parse(selectElement.parentElement.querySelector(`.optionsDropdown li.selected`).dataset.invoiceData);
                selectedInvoice = invoiceData;

                const invoiceDate = invoiceData.date;
                const dateInput = document.getElementById('date');
                dateInput.min = invoiceDate.split('T')[0];
                dateInput.disabled = false;
                dateInput.value = new Date().toISOString().split('T')[0];

                const quantityInput = document.getElementById('quantity');
                quantityInput.disabled = false;
            } else {
                document.getElementById('date').value = '';
                document.getElementById('date').disabled = true;

                document.getElementById('quantity').value = '';
                document.getElementById('quantity').disabled = true;
            }
        }

        function onQuantityInput(quantityInput) {
            selectedInvoice.articles_in_invoice.forEach(article => {
                if (article.id === selectedArticleId) {
                    document.getElementById('amount').value = (quantityInput.value * article.sales_rate) * (1 - selectedInvoice.discount / 100);
                }
            })
        }
    </script>
@endsection
