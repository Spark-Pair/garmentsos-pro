@extends('app')
@section('title', 'Add Article | ' . $client_company->name)
@section('content')
@php
    $categories_options = app('article')->categories;

    $seasons_options = app('article')->seasons;

    $sizes_options = app('article')->sizes;
@endphp
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-5xl mx-auto">
        <x-search-header heading="Add Article" link linkText="Show Articles" linkHref="{{ route('articles.index') }}"/>
        <x-progress-bar
            :steps="['Enter Details', 'Enter Rates', 'Upload Image']"
            :currentStep="1"
        />
    </div>

    <div class="row max-w-5xl mx-auto flex gap-4">
        <!-- Form -->
        <form id="form" action="{{ route('articles.store') }}" method="post" enctype="multipart/form-data"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 grow relative overflow-hidden">
            @csrf
            <x-form-title-bar title="Add Article" />
            <!-- Step 1: Basic Information -->
            <div class="step1 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- article_no -->
                    <x-input
                        label="Article No"
                        name="article_no"
                        id="article_no"
                        type="number"
                        placeholder="Enter article no"
                        required
                    />

                    <!-- date -->
                    <x-input
                        label="Date"
                        name="date"
                        id="date"
                        validateMin
                        min="2024-01-01"
                        validateMax
                        max="{{ now()->toDateString() }}"
                        type="date"
                        required
                    />

                    {{-- category --}}
                    <x-select
                        label="Category"
                        name="category"
                        id="category"
                        :options="$categories_options"
                        showDefault
                    />

                    {{-- size --}}
                    <x-select
                        label="Size"
                        name="size"
                        id="size"
                        :options="$sizes_options"
                        required
                        showDefault
                        searchable
                    />

                    {{-- season --}}
                    <x-select
                        label="Season"
                        name="season"
                        id="season"
                        :options="$seasons_options"
                        required
                        showDefault
                    />

                    {{-- quantity --}}
                    <x-input
                        label="Quantity - Pcs."
                        name="quantity"
                        id="quantity"
                        type="number"
                        placeholder="Enter quantity"
                    />

                    {{-- extra_pcs --}}
                    <x-input
                        label="Extra Pcs."
                        name="extra_pcs"
                        id="extra_pcs"
                        type="number"
                        placeholder="Enter extra pcs"
                    />

                    {{-- fabric_type --}}
                    <x-input
                        label="Fabric Type"
                        name="fabric_type"
                        id="fabric_type"
                        type="text"
                        placeholder="Enter fabric type"
                    />

                    {{-- master unit --}}
                    <x-select
                        label="Master Unit"
                        name="pcs_per_packet"
                        id="pcs_per_packet"
                        :options="$masterUnitOptions"
                        showDefault
                        dataClearable
                        addBtnLink="{{ route('setups.create') }}"
                    />
                </div>
            </div>

            <!-- Step 2: Production Details -->
            <div class="step2 hidden space-y-4">
                <div class="step2 hidden space-y-4 ">
                    <div class="flex justify-between gap-4">
                        {{-- title --}}
                        <div class="grow">
                            <x-input
                                id="title"
                                placeholder="Enter title"
                            />
                        </div>

                        {{-- rate --}}
                        <x-input
                            id="rate"
                            type="number"
                            placeholder="Enter rate"
                        />

                        {{-- add rate button --}}
                        <div class="form-group flex w-10 shrink-0">
                            <input type="button" value="+"
                                class="w-full bg-[var(--primary-color)] text-[var(--text-color)] rounded-lg cursor-pointer border border-[var(--primary-color)]"
                                onclick="addRate()" />
                        </div>
                    </div>
                    {{-- rate showing --}}
                    <div id="rate-table" class="w-full text-left text-sm">
                        <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                            <div class="grow ml-5">Title</div>
                            <div class="w-1/4">Rate</div>
                            <div class="w-[10%] text-center">Action</div>
                        </div>
                        <div id="rate-list" class="space-y-4 h-[250px] overflow-y-auto my-scrollbar-2">
                            <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Rates Added</div>
                        </div>
                    </div>
                    {{-- calc bottom --}}
                    <div id="calc-bottom" class="flex w-full gap-4 text-sm">
                        <div
                            class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                            <div>Total - Rs.</div>
                            <div class="text-right">0.0</div>
                        </div>
                        <div
                            class="final flex justify-between items-center bg-[var(--h-bg-color)] border border-gray-600 rounded-lg py-2 px-4 w-full">
                            <label for="sales_rate" class="text-nowrap grow">Sales Rate - Rs.</label>
                            <input type="text" required name="sales_rate" id="sales_rate" value="0.0"
                                class="text-right bg-transparent outline-none border-none w-[50%]" />
                        </div>
                    </div>
                    <input type="hidden" name="rates_array" id="rates_array" value="[]" />
                </div>
            </div>

            <!-- Step 3: Image -->
            <div class="step3 hidden space-y-4">
                <x-file-upload
                    id="image_upload"
                    name="image_upload"
                    placeholder="{{ asset('images/image_icon.png') }}"
                    uploadText="Upload article image"
                />
            </div>
        </form>

        <div
            class="bg-[var(--secondary-bg-color)] rounded-xl shadow-xl p-8 border border-[var(--glass-border-color)]/20 w-[35%] pt-14 relative overflow-hidden fade-in">
            <x-form-title-bar title="Last Record" />

            <!-- Step 1: Basic Information -->
            <div class="step1 space-y-4 ">
                @if ($lastRecord)
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-input
                            label="Article No"
                            value="{{ $lastRecord->article_no }}"
                            disabled
                        />
                        <x-input
                            label="Date"
                            value="{{ $lastRecord->date->format('d-M-Y, D') }}"
                            disabled
                        />
                        <x-input
                            label="Category"
                            value="{{ $lastRecord->category }}"
                            disabled
                        />
                        <x-input
                            label="Size"
                            value="{{ $lastRecord->size }}"
                            disabled
                        />
                        <x-input
                            label="Season"
                            value="{{ $lastRecord->season }}"
                            disabled
                        />
                        <x-input
                            label="Quantity-Pcs"
                            value="{{ $lastRecord->quantity }}"
                            disabled
                        />
                        <x-input
                            label="Extra Pcs"
                            value="{{ $lastRecord->extra_pcs }}"
                            disabled
                        />
                        <x-input
                            label="Fabric Type"
                            value="{{ $lastRecord->fabric_type }}"
                            disabled
                        />
                        <x-input
                            label="Master Unit"
                            value="{{ $lastRecord->pcs_per_packet ?: '' }}"
                            disabled
                        />
                    </div>
                @else
                    <div class="text-center text-xs text-[var(--border-error)]">No records found</div>
                @endif
            </div>

            <!-- Step 2: Production Details -->
            <div class="step2 hidden space-y-6  h-full flex flex-col">
                @if ($lastRecord)
                    <div class="w-full text-left grow">
                        <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                            <div class="grow ml-5">Title</div>
                            <div class="w-1/4">Rate</div>
                        </div>
                        <div id="rate-list" class="space-y-4 h-[250px] overflow-y-auto my-scrollbar-2">
                            @if (count($lastRecord->rates_array) === 0)
                                <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Rates Added
                                </div>
                            @else
                                @foreach ($lastRecord->rates_array as $rate)
                                    @php
                                        $lastRecord->total_rate += $rate['rate'];
                                    @endphp
                                    <div
                                        class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">
                                        <div class="grow ml-5">{{ $rate['title'] }}</div>
                                        <div class="w-1/4">{{ \App\Support\Money::format($rate['rate']) }}</div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-col w-full gap-4">
                        <div
                            class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 w-full">
                            <div class="grow">Total - Rs.</div>
                            <div class="w-1/4 text-right">{{ \App\Support\Money::format($lastRecord->total_rate) }}
                            </div>
                        </div>
                        <div
                            class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 w-full">
                            <div class="text-nowrap grow">Sales Rate - Rs.</div>
                            <div class="w-1/4 text-right">{{ \App\Support\Money::format($lastRecord->sales_rate) }}
                            </div>
                        </div>
                    </div>
                @else
                    <div class="text-center text-xs text-[var(--border-error)]">No records found</div>
                @endif
            </div>

            <!-- Step 3: Production Details -->
            <div class="step3 hidden space-y-6  text-sm">
                @if ($lastRecord)
                    <div class="grid grid-cols-1 md:grid-cols-1">
                        @if ($lastRecord->image == 'no_image_icon.png')
                            <x-file-upload
                                id="image_upload"
                                name="image_upload"
                                placeholder="{{ asset('images/no_image_icon.png') }}"
                                uploadText="Image"
                            />
                        @else
                            <x-file-upload
                                id="image_upload"
                                name="image_upload"
                                placeholder="{{ asset('storage/uploads/images/' . rawurlencode(html_entity_decode($lastRecord->image))) }}"
                                uploadText="Image"
                            />
                        @endif
                    </div>
                @else
                    <div class="text-center text-xs text-[var(--border-error)]">No records found</div>
                @endif
            </div>
        </div>
    </div>

@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/articles-create.js') }}"></script>
<script>
        window.__articlesData = @json($articles);
    </script>
@endpush
