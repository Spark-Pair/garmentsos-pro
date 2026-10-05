@extends('app')
@section('title', 'Physical Quantity Report | ' . $client_company->name)
@section('content')
@php
    $companyData = $physicalBranding ?? (object) app(\App\Services\Branches\ModuleBranchService::class)->documentBranding('reports_physical_quantity');
    $selectedBranchLabels = $selectedBranchLabels ?? ['All Branches'];
    $modeLabels = [
        'all_articles' => 'All Articles',
        'article_wise' => 'Article-wise',
        'proceed_by_wise' => 'Proceed By-wise',
    ];
    $reportTypeLabels = [
        'stock' => 'Stock',
        'altration' => 'Altration',
    ];
@endphp
    <div class="switch-btn-container flex absolute top-3 md:top-17 left-3 md:left-5 z-4">
        <div class="switch-btn relative flex border-3 border-[var(--secondary-bg-color)] bg-[var(--secondary-bg-color)] rounded-2xl overflow-hidden">
            <div id="reportTypeHighlight" class="absolute h-full rounded-xl bg-[var(--bg-color)] transition-all duration-300 ease-in-out z-0"></div>

            <button id="stockBtn" type="button" class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300" onclick="setPhysicalQuantityReportType(this, 'stock')">
                <div class="hidden md:block">Stock</div>
                <div class="block md:hidden"><i class="fas fa-boxes-stacked text-xs"></i></div>
            </button>
            <button id="altrationBtn" type="button" class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300" onclick="setPhysicalQuantityReportType(this, 'altration')">
                <div class="hidden md:block">Altration</div>
                <div class="block md:hidden"><i class="fas fa-scissors text-xs"></i></div>
            </button>
        </div>
    </div>

    <div class="mb-5 max-w-5xl mx-auto">
        <x-search-header heading="Physical Quantity Report" />
        <x-progress-bar :steps="['Select Options', 'Preview']" :currentStep="1" />
    </div>

    <form id="form" action="#" method="get"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-5xl mx-auto relative overflow-hidden">
        <x-form-title-bar title="Generate Physical Quantity Report" />

        <div class="step1 space-y-4">
            <input type="hidden" id="report_type" name="report_type" value="{{ $reportType }}" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-select
                    label="Articles"
                    name="article_id"
                    id="article_id"
                    :options="$articleOptions"
                    :value="old('article_id', $data['filters']['article_id'] ?? '')"
                    multiple
                    showDefault
                />
                <x-select label="Season" name="season" id="season" :options="$filterOptions['seasons']" :value="old('season', $data['filters']['season'] ?? '')" multiple showDefault />
                <x-select label="Size" name="size" id="size" :options="$filterOptions['sizes']" :value="old('size', $data['filters']['size'] ?? '')" multiple showDefault />
                <x-select label="Category" name="category" id="category" :options="$filterOptions['categories']" :value="old('category', $data['filters']['category'] ?? '')" multiple showDefault />
                <x-select label="Proceed By" name="processed_by" id="processed_by" :options="$filterOptions['proceed_by']" :value="old('processed_by', $data['filters']['processed_by'] ?? '')" multiple showDefault />
                <x-select label="Shipment" name="shipment" id="shipment" :options="$filterOptions['shipments']" :value="old('shipment', $data['filters']['shipment'] ?? '')" multiple showDefault />
            </div>
        </div>

        <div class="step2 hidden space-y-4 text-black h-[35rem] overflow-y-auto my-scrollbar-2">
            @if (isset($data))
                <div id="preview-container" class="h-full relative">
                    @php
                        $totalPages = $data['pages']->count();
                        $activeReportType = $data['report_type'] ?? $reportType ?? 'altration';
                        $reportHeading = $activeReportType === 'stock'
                            ? 'Physical Quantity Stock Report'
                            : 'Physical Quantity Altration Report';
                        $primaryLabel = $activeReportType === 'stock' ? 'Ordered' : 'Received';
                        $secondaryLabel = $activeReportType === 'stock' ? 'Current' : 'Remaining';
                    @endphp
                    @forelse ($data['pages'] as $page)
                        @php
                            $serial = 1; // har page pe reset
                        @endphp
                        <div class="preview-page w-[210mm] h-[297mm] mx-auto overflow-hidden relative bg-white rounded-md">
                            <div id="preview" class="preview flex flex-col h-full">
                                <div id="preview-document" class="preview-document flex flex-col h-full">
                                    <div id="preview-banner" class="preview-banner w-full flex justify-between items-center px-8 pr-8">
                                        <div class="flex items-center gap-3">
                                            @if($companyData->logo_url)
                                                <div class="w-[13.5rem] flex items-center justify-start gap-2.5" style="height: 2.5rem">
                                                    <img
                                                        src="{{ $companyData->logo_url }}"
                                                        alt="garmentsos-pro"
                                                        class="max-h-full max-w-full object-contain"
                                                    />
                                                    @if($companyData->logo_text)
                                                        <h1 class="text-md font-bold tracking-wide">
                                                            {{ $companyData->logo_text }}
                                                        </h1>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                        <div class="right">
                                            <div>
                                                <h1 class="text-lg font-medium text-[var(--primary-color)] pr-2 capitalize">{{ $reportHeading }}</h1>
                                                <div class="total-bill leading-none text-xs">Total Records: {{ $data['rows']->count() }} | Branches: {{ implode(', ', $selectedBranchLabels) }}</div>
                                            </div>
                                        </div>
                                    </div>

                                    <hr class="w-full my-1 border-gray-700">

                                    <div id="preview-body" class="preview-body w-[95%] grow mx-auto">
                                        <div class="preview-table w-full">
                                        <div class="table w-full rounded-md text-xs">
                                            <div class="flex gap-[6mm] items-start">
                                                @foreach (['left', 'right'] as $column)
                                                    <div class="w-1/2">
                                                        @if ($page[$column]->isNotEmpty())
                                                            <div class="preview-table w-full">
                                                            <div class="table w-full border border-gray-700 rounded-lg p-1 text-xs">
                                                                <div class="thead w-full">
                                                                    <div class="tr flex w-full px-2 py-1 bg-[var(--primary-color)] text-white text-center rounded-md">
                                                                        <div class="th font-medium overflow-hidden w-[8%] text-left">#</div>
                                                                        <div class="th font-medium overflow-hidden w-[30%] text-left">Article/pckt.</div>
                                                                        <div class="th font-medium overflow-hidden grow">Proc. By</div>
                                                                        <div class="th font-medium overflow-hidden w-[20%]">{{ $primaryLabel }}</div>
                                                                        <div class="th font-medium overflow-hidden w-[20%]">{{ $secondaryLabel }}</div>
                                                                    </div>
                                                                </div>
                                                                <div id="tbody" class="tbody w-full mt-0.5">
                                                                    @foreach ($page[$column] as $row)
                                                                        <div>
                                                                            @unless($loop->first)
                                                                                <hr class="w-full my-0.5 border-dotted">
                                                                            @endunless
                                                                            <div class="tr flex w-full px-2 py-[0.5px] text-center gap-0.5 leading-[0.72rem]">
                                                                                <div class="td font-medium overflow-hidden w-[8%] text-left capitalize truncate">{{ $serial++ }}.</div>
                                                                                <div class="td font-medium overflow-hidden w-[30%] text-left truncate">{{ $row['article_no'] }}</div>
                                                                                <div class="td font-medium overflow-hidden grow capitalize truncate">{{ $row['proceed_by'] }}</div>
                                                                                <div class="td font-medium overflow-hidden w-[20%]">{{ $row['primary_qty'] }}</div>
                                                                                <div class="td font-medium overflow-hidden w-[20%]">{{ $row['secondary_qty'] }}</div>
                                                                            </div>
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                            </div>
                                                        @else
                                                            <div class="w-full min-h-[30mm] border border-dashed border-gray-300 rounded-lg flex items-center justify-center text-gray-400">
                                                                No data
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                        </div>
                                    </div>

                                    <hr class="w-full my-1 border-gray-700">
                                    <div class="tfooter flex w-full text-sm px-4 justify-between text-gray-800 leading-none text-xs" style="font-size: 0.70rem">
                                        <p>Powered by SparkPair &copy; {{ now()->year }} SparkPair | +92 316 5825495</p>
                                        <p>{{ $loop->iteration }} of {{ $totalPages }} | {{ $today = now()->format('d-M-Y, g:i A') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="preview-page w-[210mm] h-[297mm] mx-auto bg-white rounded-md overflow-hidden relative p-[0.19in]">
                            <div class="px-[10mm] py-[12mm] text-center text-gray-500">
                                No matching records found for the selected report options.
                            </div>
                        </div>
                    @endforelse
                </div>
            @endif
        </div>
    </form>
@endsection


@push('page-scripts')
<script defer src="{{ asset('js/pages/reports-physical-quantity.js') }}"></script>
<script>
    window.__reportsPhysicalQuantity = {
        reportUrl: @json(route('reports.physical-quantity')),
        reportType: @json($reportType),
        setTypeUrl: @json(url('/set-physical-quantity-report-type')),
        csrfToken: @json(csrf_token()),
    };
</script>
@endpush
