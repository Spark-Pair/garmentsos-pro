@extends('app')
@section('title', 'Add Bilty | ' . $client_company->name)
@section('content')
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="max-w-6xl mx-auto w-full">
        <x-search-header heading="Add Bilty" link linkText="Show Bilties" linkHref="{{ route('bilties.index') }}"/>
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('bilties.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-6xl mx-auto relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Add Bilty" />

        <div class="space-y-4 ">
            <div class="flex items-end gap-4">
                {{-- cargo date --}}
                <div class="grow">
                    <x-input label="Date" name="date" id="date" type="date" onchange="trackStateOfgenerateBtn(this)"
                        validateMax max='{{ now()->toDateString() }}' validateMin
                        min="2024-01-01" required />
                </div>

                <button id="generateListBtn" type="button"
                    class="bg-[var(--primary-color)] px-4 py-2 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out text-nowrap cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">Select Invoices</button>
            </div>
            {{-- cargo-list-table --}}
            <div id="cargo-list-table" class="w-full text-left text-sm">
                <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                    <div class="w-[7%]">S.No.</div>
                    <div class="w-1/6">Date</div>
                    <div class="w-[11%]">Bill No.</div>
                    <div class="w-[13%]">Cartons</div>
                    <div class="w-[17%]">Customer</div>
                    <div class="w-[10%]">City</div>
                    <div class="w-1/6">Bilty No.</div>
                    <div class="w-1/6">Cargo</div>
                    <div class="w-[8%] text-center">Action</div>
                </div>
                <div id="cargo-list" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-3 px-4">No Rates Added</div>
                </div>
            </div>

            <input type="hidden" name="invoices_array" id="invoices" value="">
            <div class="w-full grid grid-cols-1 text-sm mt-5 text-nowrap">
                <div class="total-qty flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Cartons</div>
                    <div id="finalTotalCartons">0</div>
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

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/bilties-add.js') }}"></script>
<script>
        window.__biltiesAdd = {
            invoices: @json($invoices),
            companyData: @json($client_company),
            companyLogoBase: @json(asset('images')),
        };
    </script>
@endpush
