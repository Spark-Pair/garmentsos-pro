@extends('app')
@section('title', 'Edit Issued Fabric | ' . $client_company->name)
@section('content')
    @php
        $selectedIssuedFabricFields = [
            'tag' => old('tag', $issuedFabric->tag),
            'worker' => old('worker_id', $issuedFabric->worker_id),
        ];
    @endphp

    <div class="mb-5 max-w-3xl mx-auto">
        <x-search-header heading="Edit Issued Fabric" link linkText="Show Fabrics" linkHref="{{ route('fabrics.index') }}" />
    </div>

    <div class="row max-w-3xl mx-auto flex gap-4">
        <form id="form" action="{{ route('fabrics.issued.update', ['issuedFabric' => $issuedFabric->id]) }}" method="post"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 grow relative overflow-hidden">
            @csrf
            @method('PUT')
            <x-form-title-bar title="Edit Issued Fabric" />

            @if (session('error'))
                <div class="mb-4 rounded-lg border border-[var(--border-error)] bg-[var(--border-error)]/10 px-4 py-3 text-[var(--border-error)]">
                    {{ session('error') }}
                </div>
            @endif

            <div class="step1 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input label="Date" name="date" id="date" validateMin min="2024-01-01" validateMax max="{{ now()->toDateString() }}" type="date" required value="{{ old('date', $issuedFabric->date?->format('Y-m-d')) }}" />

                    <x-select label="Tag" name="tag" id="tag" :options="$tags_options" :value="old('tag', $issuedFabric->tag)" required showDefault onchange="trackTagSelect(this)" />

                    <x-select label="Worker" name="worker_id" id="worker" :options="$workers_options" :value="old('worker_id', $issuedFabric->worker_id)" required showDefault />

                    <x-input label="Unit" name="unit" id="unit" placeholder="Unit" capitalized disabled />

                    <x-input label="Available Stock" name="avalaible_stock" id="avalaible_stock" type="number" placeholder="Available Stock" disabled />

                    <x-input label="Quantity" name="quantity" id="quantity" type="number" placeholder="Enter quantity" required step="0.01" oninput="trackQuantity(this)" value="{{ old('quantity', $issuedFabric->quantity) }}" />

                    <div class="col-span-full">
                        <x-input label="Remarks" name="remarks" id="remarks" type="text" placeholder="Enter remarks" value="{{ old('remarks', $issuedFabric->remarks) }}" />
                    </div>
                </div>
            </div>

            <div class="w-full flex justify-end gap-3 mt-4">
                <a href="{{ route('fabrics.index') }}" class="bg-[var(--h-bg-color)] border border-gray-600 px-4 py-2 rounded-lg hover:bg-[var(--h-secondary-bg-color)] transition-all">
                    Cancel
                </a>
                <button type="submit"
                    class="px-6 py-2 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all 0.3s ease-in-out cursor-pointer">
                    <i class='fas fa-save mr-1'></i> Save
                </button>
            </div>
        </form>
    </div>
@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/fabrics-issue.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const selected = @json($selectedIssuedFabricFields);
        for (const [field, value] of Object.entries(selected)) {
            const option = document.querySelector(`li[data-for="${field}"][data-value="${value}"]`);
            if (option) {
                selectThisOption(option);
            }
        }

        const tagInput = document.getElementById('tag');
        if (tagInput) {
            trackTagSelect(tagInput);
        }
    });
</script>
@endpush
