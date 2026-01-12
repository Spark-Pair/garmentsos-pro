@extends('app')
@section('title', 'Return Fabric | ' . $client_company->name)
@section('content')
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-3xl mx-auto">
        <x-search-header heading="Return Fabric" link linkText="Show Fabrics" linkHref="{{ route('fabrics.index') }}" />
    </div>

    <div class="row max-w-3xl mx-auto flex gap-4">
        <!-- Form -->
        <form id="form" action="{{ route('fabrics.returnPost') }}" method="post" enctype="multipart/form-data"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 grow relative overflow-hidden">
            @csrf
            <x-form-title-bar title="Return Fabric" />
            <!-- Step 1: Basic Information -->
            <div class="step1 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- worker --}}
                    <x-select label="Worker" name="worker_id" id="worker" :options="$workers_options" required showDefault onchange="trackWorkerState(this)" />

                    <!-- date -->
                    <x-input label="Date" name="date" id="date" validateMin min="2024-01-01" validateMax max="{{ now()->toDateString() }}" type="date" required disabled onchange="trackDateState(this)" />

                    {{-- tag --}}
                    <x-select label="Tag" name="tag" id="tag" :options="$tags_options" required showDefault onchange="trackTagSelect(this)" />

                    <!-- remaining_stock -->
                    <x-input label="Remaining Stock" name="remaining_stock" id="remaining_stock" type="number" placeholder="Remaining Stock" disabled />

                    <!-- quantity -->
                    <x-input label="Quantity" name="quantity" id="quantity" type="number" placeholder="Enter quantity" required step="0.01" oninput="trackQuantity(this)" />

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
    </div>

    <script>
        const workerSelectDom = document.getElementById('worker');
        const dateDom = document.getElementById('date');
        const remainingStockInpDom = document.getElementById('remaining_stock');
        const quantityErrorDom = document.getElementById('quantity-error');

        function trackWorkerState(elem) {
            if (elem.value === '') {
                dateDom.disabled = true;
                dateDom.value = '';
            } else {
                dateDom.disabled = false;
            }
        }

        function trackDateState(elem) {
            let selectedWorkerId = workerSelectDom.closest('.selectParent').querySelector('.dbInput').value;

            $.ajax({
                url: '{{ route("fabrics.return") }}',
                method: 'GET',
                data: {
                    worker_id: selectedWorkerId,
                    date: dateDom.value,
                },
                success: function(response) {
                    $('#tag').closest('.selectParent').html($(response).find('#tag').closest('.selectParent').html());

                },
                error: function(xhr) {
                    console.error(xhr.responseText);
                }
            });
        }

        function trackTagSelect(elem) {
            const selectedTag = JSON.parse(elem.closest('.selectParent').querySelector('li.selected').getAttribute('data-option') ?? '{}');

            remainingStockInpDom.value = selectedTag.remaining;
        }

        function trackQuantity(elem) {
            const maxStock = parseInt(remainingStockInpDom.value || '0');
            const currentVal = parseInt(elem.value || '0');

            if (currentVal > maxStock) {
                elem.value = maxStock;
                quantityErrorDom.textContent = 'Maximum';
                quantityErrorDom.classList.remove('hidden');
            } else {
                elem.value = currentVal;
            }
        }
    </script>
@endsection
