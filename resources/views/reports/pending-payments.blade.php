@extends('app')
@section('title', 'Pending Payments | ' . $client_company->name)
@section('content')
@php
    $companyData = $pendingBranding ?? $client_company;
    $reportBranches = collect($reportBranches ?? []);
    $selectedBranchIds = collect($selectedBranchIds ?? $reportBranches->pluck('id')->all())->map(fn($id) => (int) $id)->all();
    $selectedBranchLabels = $selectedBranchLabels ?? ['All Branches'];
@endphp
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-4xl mx-auto">
        <x-search-header heading="Pending Payments"/>
        <x-progress-bar :steps="['Select Date', 'Preview']" :currentStep="1" />
    </div>
    <!-- Form -->
    <form id="form" action="{{ route('orders.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto  relative overflow-hidden">
        <x-form-title-bar title="Pending Payments" />

        <!-- Step 1: Select Date -->
        <div class="step1 space-y-4 ">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <!-- date -->
                    <x-input
                        label="Date"
                        name="date"
                        id="date"
                        type="date"
                        value="{{ request('date', now()->toDateString()) }}"
                        required
                    />
                </div>

                <div>
                    <x-select
                        label="City"
                        name="city"
                        id="city"
                        :options="$cities_options ?? []"
                        :value="$selectedCities ?? []"
                        showDefault
                        multiple
                    />
                </div>

            </div>
        </div>


        <!-- Step 2: view order -->
        <div class="step2 hidden space-y-4 text-black h-[35rem]">
            @if (isset($data))
                {{-- First Page (26 rows) --}}
                <div id="preview-container" class="h-full relative overflow-y-auto my-scrollbar-2">
                    <div id="preview-page" class="w-[210mm] mx-auto overflow-hidden relative bg-white rounded-md pt-6 pb-0">
                        <div class="mx-auto mb-2 w-[95%] rounded-md border border-gray-700 px-3 py-1 text-xs text-gray-800">
                            Branches: {{ implode(', ', $selectedBranchLabels) }}
                        </div>
                        <div id="preview" class="preview flex flex-col h-full">
                            <div id="preview-document" class="preview-document flex flex-col h-full px-2">
                                {{-- Table --}}
                                <div id="preview-body" class="preview-body w-[95%] grow mx-auto">
                                    {{-- Multiple Slips --}}
                                    @foreach ($data as $item)
                                        <div class="slip w-full border border-gray-700 rounded-lg p-1 text-xs tracking-wide">
                                            {{-- Header --}}
                                            <div class="head w-full px-4 py-1.5 border border-gray-700 text-center rounded-md mb-1">
                                                <div class="font-medium">{{ $item['customer'] }}</div>
                                            </div>

                                            <div class="table w-full">
                                                {{-- Table Header --}}
                                                <div class="thead w-full">
                                                    <div class="tr flex items-center w-full px-4 py-2 bg-[var(--primary-color)] text-white text-center rounded-md">
                                                        <div class="th w-[10%] font-medium">S.No</div>
                                                        <div class="th w-1/6 font-medium">Date</div>
                                                        <div class="th w-1/6 font-medium">Method</div>
                                                        <div class="th w-1/6 font-medium">Reff. No.</div>
                                                        <div class="th w-1/6 font-medium">Amount</div>
                                                        <div class="th w-1/6 font-medium">Received</div>
                                                        <div class="th w-1/6 font-medium">Balance</div>
                                                    </div>
                                                </div>

                                                {{-- Table Body --}}
                                                <div id="tbody" class="tbody w-full">
                                                    @foreach ($item['payments'] as $payment)
                                                        <div class="w-full px-4 py-1.5 text-center border-b border-gray-700 border-dashed last:border-0">
                                                            <div class="tr flex items-center">
                                                                <div class="td w-[10%]">{{ $loop->iteration }}</div>
                                                                <div class="td w-1/6">{{ \Carbon\Carbon::parse($payment['date'])->format('d-M-Y, D') }}</div>
                                                                <div class="td w-1/6 capitalize">{{ $payment['method'] }}</div>
                                                                <div class="td w-1/6">{{ $payment['reff_no'] }}</div>
                                                                <div class="td w-1/6">{{ \App\Support\Money::format($payment['amount']) }}</div>
                                                                <div class="td w-1/6">{{ \App\Support\Money::format($payment['received_amount']) }}</div>
                                                                <div class="td w-1/6">{{ \App\Support\Money::format($payment['balance']) }}</div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            {{-- footer --}}
                                            <div class="footer grid grid-cols-3 gap-1 border-t border-gray-700 pt-1">
                                                <div class="px-4 py-1.5 border border-gray-700 text-center rounded-md">
                                                    <div class="font-medium">Total Amount : {{ \App\Support\Money::format($item['totals']['amount']) }}</div>
                                                </div>
                                                <div class="px-4 py-1.5 border border-gray-700 text-center rounded-md">
                                                    <div class="font-medium">Total Received : {{ \App\Support\Money::format($item['totals']['received_amount']) }}</div>
                                                </div>
                                                <div class="px-4 py-1.5 border border-gray-700 text-center rounded-md">
                                                    <div class="font-medium">Balance : {{ \App\Support\Money::format($item['totals']['balance']) }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <hr class="w-[85%] mx-auto my-3 border-gray-700/60">
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </form>

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/reports-pending-payments.js') }}"></script>
<script>
        window.__reportsPendingPayments = {
            pendingUrl: @json(route('reports.pending-payments')),
            csrfToken: @json(csrf_token()),
        };
    </script>
@endpush
