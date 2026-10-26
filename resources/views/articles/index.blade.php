@extends('app')
@section('title', 'Show Articles | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Article" => [
                "id" => "article_no",
                "type" => "text",
                "placeholder" => "Enter article no.",
                                "dataFilterPath" => "article_no",
            ],
            "Processed By" => [
                "id" => "processed_by",
                "type" => "text",
                "placeholder" => "Enter article no.",
                                "dataFilterPath" => "processed_by",
            ],
            "Category" => [
                "id" => "category",
                "type" => "select",
                "options" => app('article')->categories,
                                "dataFilterPath" => "category",
            ],
            "Season" => [
                "id" => "season",
                "type" => "select",
                "options" => app('article')->seasons,
                                "dataFilterPath" => "season",
            ],
            "Size" => [
                "id" => "size",
                "type" => "select",
                "options" => app('article')->sizes,
                                "dataFilterPath" => "size",
            ],
            "Date Range" => [
                "id" => "date_range_start",
                "type" => "date",
                "id2" => "date_range_end",
                "type2" => "date",
                                "dataFilterPath" => "date",
            ]
        ];
    @endphp

    {{-- header --}}
    <div class="w-[90%] mx-auto">
        <x-search-header heading="Articles" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[90%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn title="Show Articles" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn  />

            <div class="absolute bottom-0 right-0 flex items-center justify-between gap-2 w-fll z-50 p-3 w-full pointer-events-none">
                <x-section-navigation-button link="{{ route('articles.create') }}" title="Add New Article" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-6 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Article No</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Category</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Season</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Size</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Sales Rate</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Processed By</div>
                        </div>
                        <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/articles-index.js') }}"></script>
<script>
        @php
            $creatorButtonHtml = view('components.section-navigation-button', [
                'title' => 'Added by: __ARTICLE_CREATOR__',
                'icon' => 'fa-user',
            ])->render();
        @endphp
        window.__articlesIndex = {
            currentUserRole: @json(Auth::user()->role),
            authLayout: @json($authLayout),
            creatorButtonHtml: @json($creatorButtonHtml),
            addRateUrl: @json(route('add-rate')),
            updateImageUrl: @json(route('update-image')),
        };
    </script>
@endpush
