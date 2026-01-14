@props([
    'heading' => '',
    'search_fields' => [],
    'toFrom' => false,
    'toFrom_type' => 'text',
    'toFrom_label' => 'text',
    'link' => false,
    'linkText' => '',
    'linkHref' => '#',
    'hide' => false
])

<div class="header w-full flex items-center justify-between">
    @if (!$hide)
        <h5 id="page-name" class="text-3xl text-[var(--text-color)] uppercase font-semibold leading-none ml-1">
            {{ str_replace('_', ' ', $heading) }}</h5>
    @endif

    <div class="">
        @if (!$hide == true)
            @if ($toFrom)
                <!-- toFrom -->
                <div id="toFrom" class="toFrom-box flex items-center gap-4 shrink-0 grow w-full">
                    <label for="to"
                        class="block font-medium text-[var(--secondary-text)] grow text-nowrap">{{ $toFrom_label }}</label>
                    <div class="toFrom-inputs relative grid grid-cols-2 gap-4 w-full">
                        <x-input name="from" id="from" type="{{ $toFrom_type }}" placeholder="From" />
                        <x-input name="to" id="to" type="{{ $toFrom_type }}" placeholder="To" />
                    </div>
                </div>
            @endif

            @if ($toFrom && !empty($search_fields))
                <div class="separator w-0 border-r border-gray-600"></div>
            @endif
        @endif

        @if (!empty($search_fields))
            <!-- Search Form -->
            <div id="search-form" class="search-box shrink-0 grow w-full">
                <!-- Search Input -->
                <div class="search-input">
                    @if (!$hide == true)
                        {{-- <x-input name="search_box" id="search_box" oninput="searchData(this.value)" placeholder="🔍 Search {{ $heading }}..." withButton btnId="filter-btn" btnClass="dropdown-trigger" btnText='<i class="text-xs fa-solid fa-filter"></i>' /> --}}
                        <button id="filter-btn" type="button" onclick="openDropDown(event, this)"
                            class="dropdown-trigger bg-[var(--primary-color)] px-3 py-2.5 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out cursor-pointer flex gap-2 items-center font-semibold">
                            <i class="text-xs fa-solid fa-filter"></i> Search & Filter
                        </button>
                    @endif

                    <div class="dropdownMenu flex flex-col text-sm absolute top-2 bottom-2 right-2 hidden border border-gray-600 w-sm bg-[var(--h-secondary-bg-color)] text-[var(--text-color)] shadow-xl rounded-2xl opacity-0 transition-all duration-300 ease-in-out z-[100] p-4">
                        <div class="header flex justify-between items-center p-1">
                            <h6 class="text-2xl text-[var(--text-color)] font-semibold leading-none ml-1">Search & Filter</h6>
                            <div onclick="closeAllDropdowns()" class="text-sm transition-all duration-300 ease-in-out hover:scale-[0.95] cursor-pointer">
                                <button type="button" class="z-10 text-gray-400 hover:text-gray-600 hover:scale-[0.95] transition-all duration-300 ease-in-out cursor-pointer">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6" style="display: inline">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <hr class="border-gray-600 my-4 w-full">
                        <div class="grow overflow-y-auto my-scrollbar-2 p-1">
                            <div class="grid grid-cols-1 gap-4">
                                @foreach ($search_fields as $search_field => $value)
                                    @if ($value['type'] == "select")
                                        <x-select label="{{ $search_field }}" id="{{ $value['id'] }}" :options="$value['options']" :dataClearable="true" dataFilterPath="{{ $value['dataFilterPath'] }}" required showDefault />
                                    @elseif ($value['type'] == "text")
                                        <x-input label="{{ $search_field }}" id="{{ $value['id'] }}" type="{{ $value['type'] }}" :dataClearable="true" dataFilterPath="{{ $value['dataFilterPath'] }}" required placeholder="{{ $value['placeholder'] }}" />
                                    @elseif (isset($value['type2']) && isset($value['id2']))
                                        <x-input label="{{ $search_field }}" id="{{ $value['id'] }}" type="{{ $value['type'] }}" dualInput id2="{{ $value['id2'] }}" type2="{{ $value['type2'] }}" :dataClearable="true" dataFilterPath="{{ $value['dataFilterPath'] }}" value="{{ $value['value'] ?? '' }}" required/>
                                    @else
                                        <x-input label="{{ $search_field }}" id="{{ $value['id'] }}" type="{{ $value['type'] }}" :dataClearable="true" dataFilterPath="{{ $value['dataFilterPath'] }}" value="{{ $value['value'] ?? '' }}" required/>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                        <hr class="border-gray-600 my-4 w-full">
                        <div class="flex gap-4 p-1">
                            <button type="button" onclick="clearAllSearchFields()"
                                class="flex-1 px-4 py-2 bg-[var(--bg-error)] border border-[var(--bg-error)] text-[var(--text-error)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-error)] transition-all duration-300 ease-in-out cursor-pointer hover:scale-[0.95]">
                                Clear
                            </button>
                            <button type="button" onclick="applyFilters()"
                                class="flex-1 px-4 py-2 bg-[var(--secondary-bg-color)] border border-gray-600 text-[var(--secondary-text)] rounded-lg hover:bg-[var(--h-bg-color)] transition-all duration-300 ease-in-out cursor-pointer hover:scale-[0.95]">
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($link)
            <!-- link_in_header -->
            <div id="link_in_header" class="link_in_headerrom-box flex items-center gap-4 shrink-0">
                <a type="button" href="{{ $linkHref }}"
                    class="bg-[var(--primary-color)] px-4.5 py-1.5 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out cursor-pointer text-nowrap flex items-center">{{ $linkText }}</a>
            </div>
        @endif
    </div>
</div>

@if (!$hide == true)
    <hr class="border-gray-600 my-4 w-full">
@endif
