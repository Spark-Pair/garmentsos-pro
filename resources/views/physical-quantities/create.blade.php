@extends('app')
@section('title', 'Add Physical Quantities | ' . $client_company->name)
@section('content')
@php
    $category_options = [
        'a' => ['text'  => 'A'],
        'b' => ['text'  => 'B'],
        'c' => ['text'  => 'C'],
];
@endphp
    <!-- Main Content -->
    <div class="max-w-5xl mx-auto">
        <x-search-header heading="Add Physical Quantity" link linkText="Show Physical Quantities" linkHref="{{ route('physical-quantities.index') }}"/>
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('physical-quantities.store') }}" method="post"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-5xl mx-auto  relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Add Physical Quantity" />

        <div class="space-y-4 ">
            <div class="flex justify-between gap-4">
                {{-- article --}}
                <div class="grow">
                    <x-input label="Article" id="article" placeholder='Select Article' class="cursor-pointer" style="pointer-events: auto !important" withImg imgUrl="" readonly required />
                    <input type="hidden" name="article_id" id="article_id" value="" />
                </div>

                {{-- date --}}
                <div class="w-1/4">
                    <x-input label="Date" name="date" id="date" type="date" max="{{ Now()->toDateString() }}" value="{{ now()->toDateString() }}" required />
                </div>

                {{-- processed_by --}}
                <div class="w-1/4">
                    <x-input label="Processed By" name="processed_by" id="processed_by" placeholder="Enter processed by" required />
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-1 md:grid-cols-3 gap-4">
                {{-- pcs_per_packet  --}}
                <x-input label="Master Unit" name="pcs_per_packet" id="pcs_per_packet" type="number" placeholder="Enter master unit" required dataValidate="max:8|min:1" />

                {{-- packets --}}
                <x-input label="Packets" name="packets" id="packets" type="number" placeholder="Enter packet count" required />

                {{-- category --}}
                <x-select
                    label="Category"
                    name="category"
                    id="category"
                    :options="$category_options"
                    required
                />
            </div>

            <hr class="border-gray-600 my-3">

            <div class="w-full grid grid-cols-1 md:grid-cols-2 gap-4 text-sm mt-5 items-start">
                <div class="first w-full">
                    <div class="current-phys-qty flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4">
                        <div class="grow">Total Physical Stock - Pcs. / Pkts.</div>
                        <div id="currentPhysicalQuantity">0</div>
                    </div>
                </div>
                <div class="second w-full">
                    <div class="total-qty flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4">
                        <div class="grow">Total Quantity - Pcs. / Pkts.</div>
                        <div id="finalOrderedQuantity">0</div>
                    </div>
                    <div id="total-qty-error" class="text-[var(--border-error)] text-xs mt-1 hidden transition-all 0.3s ease-in-out"></div>
                </div>
                <div class="thered w-full">
                    <div class="final flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4">
                        <div class="grow">Remaining Quantity - Pcs. / Pkts.</div>
                        <div id="remainingquantity">0</div>
                    </div>
                </div>
                <div class="fourth w-full">
                    <div class="final flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4">
                        <div class="grow">Total Amount - Rs.</div>
                        <div id="finalOrderAmount">0.0</div>
                    </div>
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
<script defer src="{{ asset('js/pages/physical-quantities-create.js') }}"></script>
<script>
        window.__physicalQuantitiesCreate = {
            articles: @json($articles),
            articlesUrl: @json(route('physical-quantities.create')),
        };
    </script>
@endpush
