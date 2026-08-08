@extends('app')
@section('title', 'Edit Article | ' . $client_company->name)
@section('content')
@php
    $categories_options = app('article')->categories;

    $seasons_options = app('article')->seasons;

    $sizes_options = app('article')->sizes;

    $isDeveloper = Auth::user()?->role === 'developer' || app_can('articles', 'override');
    $canArticleOverride = $canArticleOverride ?? $isDeveloper;
    $connectedCount = collect($developerImpact ?? [])->sum('count');
@endphp
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-3xl mx-auto">
        <x-search-header heading="Edit Article" link linkText="Show Articles" linkHref="{{ route('articles.index') }}"/>
        <x-progress-bar
            :steps="['Enter Details', 'Enter Rates', 'Upload Image']"
            :currentStep="1"
        />
    </div>

    <div class="row max-w-3xl mx-auto flex gap-4">
        <!-- Form -->
        <form id="form" action="{{ route('articles.update', ['article' => $article->id]) }}" method="POST" enctype="multipart/form-data"
            class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 grow relative overflow-hidden">
            @csrf
            @method('PUT')
            <x-form-title-bar title="Edit Article" />

            <!-- Step 1: Basic Information -->
            <div class="step1 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- article_no -->
                    <x-input
                        label="Article No"
                        value="{{ $article->article_no }}"
                        :disabled="!$canArticleOverride"
                        name="article_no"
                    />
                    @unless($canArticleOverride)
                        <input type="hidden" name="article_no" id="article_no" value="{{ $article->article_no }}" />
                    @endunless

                    <!-- date -->
                    <x-input
                        label="Date"
                        value="{{ $article->date }}"
                        disabled
                    />
                    <input type="hidden" name="date" id="date" value="{{ $article->date }}" />

                    {{-- category --}}
                    <x-select
                        label="Category"
                        name="category"
                        id="category"
                        :options="$categories_options"
                        showDefault
                        value="{{ $article->category }}"
                    />

                    {{-- size --}}
                    <x-select
                        label="Size"
                        name="size"
                        id="size"
                        :options="$sizes_options"
                        required
                        showDefault
                        value="{{ $article->size }}"
                    />

                    {{-- season --}}
                    <x-select
                        label="Season"
                        name="season"
                        id="season"
                        :options="$seasons_options"
                        required
                        showDefault
                        value="{{ $article->season }}"
                    />

                    {{-- quantity --}}
                    <x-input
                        label="Quantity"
                        name="quantity"
                        id="quantity"
                        type="number"
                        value="{{ $article->quantity }}"
                        placeholder="Enter quantity"
                        required
                    />

                    {{-- extra_pcs --}}
                    <x-input
                        label="Extra Pcs"
                        name="extra_pcs"
                        id="extra_pcs"
                        type="number"
                        value="{{ $article->extra_pcs }}"
                        placeholder="Enter extra pcs"
                        required
                    />

                    {{-- fabric_type --}}
                    <x-input
                        label="Fabric Type"
                        name="fabric_type"
                        id="fabric_type"
                        type="text"
                        value="{{ $article->fabric_type }}"
                        placeholder="Enter fabric type"
                        required
                    />
                </div>

                @if ($canArticleOverride)
                    <div class="rounded-xl border border-gray-600 bg-[var(--h-bg-color)] p-4 text-sm text-left">
                        <div class="font-semibold text-[var(--text-color)]">Developer Mode</div>
                        <p class="mt-1 text-[var(--secondary-text)]">
                            This article can be edited by developer even when connected records exist. Review linked usage before changing article identity or deleting it.
                        </p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            @foreach (($developerImpact ?? []) as $impact)
                                <div class="flex justify-between rounded-lg border border-gray-600 px-3 py-2">
                                    <span>{{ $impact['label'] }}</span>
                                    <strong>{{ $impact['count'] }}</strong>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
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
                        @if(is_array($article->rates_array) && count($article->rates_array) > 0)
                            <div id="rate-list" class="space-y-4 h-[250px] overflow-y-auto my-scrollbar-2">
                                @php
                                    $article->totalRate = 0.00;
                                @endphp
                                @foreach ($article->rates_array as $rate)
                                    @php
                                        $article->totalRate += (float) $rate['rate'];
                                    @endphp
                                    <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">
                                        <div class="grow ml-5">{{ $rate['title'] }}</div>
                                        <div class="w-1/4">{{ \App\Support\Money::format($rate['rate']) }}</div>
                                        <div class="w-[10%] text-center">
                                            <button onclick="deleteRate(this)" type="button"
                                                class="text-[var(--danger-color)] text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out cursor-pointer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div id="rate-list" class="space-y-4 h-[250px] overflow-y-auto my-scrollbar-2">
                                <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Rates Added</div>
                            </div>
                        @endif
                    </div>
                    {{-- calc bottom --}}
                    <div id="calc-bottom" class="flex w-full gap-4 text-sm">
                        <div
                            class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full cursor-not-allowed">
                            <div>Total - Rs.</div>
                            <div class="text-right">{{ $article->totalRate }}</div>
                        </div>
                        <div
                            class="final flex justify-between items-center bg-[var(--h-bg-color)] border border-gray-600 rounded-lg py-2 px-4 w-full">
                            <label for="sales_rate" class="text-nowrap grow">Sales Rate - Rs.</label>
                            <input type="text" required name="sales_rate" id="sales_rate" value="{{ $article->sales_rate }}"
                                class="text-right bg-transparent outline-none border-none w-[50%]" />
                        </div>
                    </div>
                    @if(is_array($article->rates_array) && count($article->rates_array) > 0)
                        <input type="hidden" name="rates_array" id="rates_array" value="{{ json_encode($article->rates_array) }}" />
                    @else
                        <input type="hidden" name="rates_array" id="rates_array" value="[]" />
                    @endif
                </div>
            </div>

            <!-- Step 3: Image -->
            <div class="step3 hidden space-y-4">
                @if ($article->image == 'no_image_icon.png')
                    <x-file-upload
                        id="image_upload"
                        name="image_upload"
                        placeholder="{{ asset('images/image_icon.png') }}"
                        uploadText="Upload article image"
                    />
                @else
                    <x-file-upload
                        id="image_upload"
                        name="image_upload"
                        placeholder="{{ asset('storage/uploads/images/' . $article->image) }}"
                        uploadText="Preview"
                    />
                    
                @endif
            </div>
        </form>

        @if ($canArticleOverride)
            <form action="{{ route('articles.destroy', $article) }}" method="POST"
                class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-5 border border-[var(--glass-border-color)]/20 h-fit w-72">
                @csrf
                @method('DELETE')
                <div class="font-semibold text-[var(--text-color)]">Delete Article</div>
                <p class="mt-2 text-[var(--secondary-text)]">
                    @if ($connectedCount > 0)
                        This article has connected records. Force delete may remove related line records through database relationships.
                    @else
                        No connected records were found for this article.
                    @endif
                </p>
                @if ($connectedCount > 0)
                    <label class="mt-4 flex items-start gap-2 text-[var(--secondary-text)]">
                        <input type="checkbox" name="confirm_delete_connected" value="1" class="mt-1">
                        <span>I reviewed connected records and want to force delete.</span>
                    </label>
                @endif
                <button type="submit"
                    class="mt-4 w-full rounded-lg bg-[var(--danger-color)] px-4 py-2 font-semibold text-white transition hover:bg-[var(--h-danger-color)]">
                    Delete Article
                </button>
            </form>
        @endif
    </div>

@endsection


@push('page-scripts')
<script>
                        // moved to public/js/pages/articles-edit.js
                    </script>
<script defer src="{{ asset('js/pages/articles-edit.js') }}"></script>
<script>
    </script>
@endpush
