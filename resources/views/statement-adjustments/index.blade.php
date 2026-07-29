@extends('app')
@section('title', 'Balance Entries | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            'Id' => [
                'id' => 'id',
                'type' => 'text',
                'placeholder' => 'Enter id',
                'dataFilterPath' => 'id',
            ],
            'Category' => [
                'id' => 'category',
                'type' => 'select',
                'options' => $categoryOptions,
                'dataFilterPath' => 'category',
            ],
            'Name' => [
                'id' => 'name',
                'type' => 'text',
                'placeholder' => 'Enter name',
                'dataFilterPath' => 'name',
            ],
            'Entry Type' => [
                'id' => 'entry_type',
                'type' => 'select',
                'options' => $entryTypeOptions,
                'dataFilterPath' => 'entry_type',
            ],
            'Transaction' => [
                'id' => 'direction',
                'type' => 'select',
                'options' => $directionOptions,
                'dataFilterPath' => 'direction',
            ],
            'Date Range' => [
                'id' => 'date_range_start',
                'type' => 'date',
                'id2' => 'date_range_end',
                'type2' => 'date',
                'dataFilterPath' => 'date',
            ],
        ];
    @endphp

    <div class="w-[80%] mx-auto">
        <x-search-header heading="Balance Entries" :search_fields="$searchFields" />
    </div>

    <section class="text-center mx-auto">
        <div class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn layout="table" title="Balance Entries" resetSortBtn />

            <div class="absolute bottom-14 right-0 flex items-center justify-end gap-2 z-50 p-3 w-full pointer-events-none">
                <x-section-navigation-button link="{{ route('statement-adjustments.create') }}" title="Add Balance Entry" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 pb-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-8 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="cursor-pointer" onclick="sortByThis(this)">Id</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Category</div>
                            <div class="cursor-pointer col-span-2" onclick="sortByThis(this)">Name</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Entry</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Type</div>
                            <div class="cursor-pointer" onclick="sortByThis(this)">Amount</div>
                        </div>
                        <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/statement-adjustments-index.js') }}"></script>
<script>
    window.__statementAdjustmentsIndex = {
        currentUserRole: @json(Auth::user()->role),
        csrfToken: @json(csrf_token()),
    };
</script>
@endpush
