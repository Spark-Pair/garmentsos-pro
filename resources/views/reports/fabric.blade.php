@extends('app')
@section('title', 'Fabric Report | ' . $client_company->name)
@section('content')
@php
    $companyData = (object) app(\App\Services\Branches\ModuleBranchService::class)->documentBranding('reports_fabric');
    $selectedBranchLabels = $selectedBranchLabels ?? ['All Branches'];
    $fabricReportType = $fabricReportType ?? (Auth::user()->fabric_report_type ?? 'worker');
    if (!in_array($fabricReportType, ['worker', 'tag', 'article'], true)) {
        $fabricReportType = 'worker';
    }
@endphp
    <div class="switch-btn-container flex absolute top-3 md:top-17 left-3 md:left-5 z-4">
        <div class="switch-btn relative flex border-3 border-[var(--secondary-bg-color)] bg-[var(--secondary-bg-color)] rounded-2xl overflow-hidden">
            <div id="fabric-report-highlight" class="absolute h-full rounded-xl bg-[var(--bg-color)] transition-all duration-300 ease-in-out z-0"></div>

            <button id="fabricWorkerBtn" type="button" class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300" onclick="setFabricReportType(this, 'worker')">
                <div class="hidden md:block">Worker Wise</div>
                <div class="block md:hidden"><i class="fas fa-user-gear text-xs"></i></div>
            </button>
            <button id="fabricTagBtn" type="button" class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300" onclick="setFabricReportType(this, 'tag')">
                <div class="hidden md:block">Tag Wise</div>
                <div class="block md:hidden"><i class="fas fa-tags text-xs"></i></div>
            </button>
            <button id="fabricArticleBtn" type="button" class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-pointer rounded-xl transition-colors duration-300" onclick="setFabricReportType(this, 'article')">
                <div class="hidden md:block">Article Wise</div>
                <div class="block md:hidden"><i class="fas fa-shirt text-xs"></i></div>
            </button>
        </div>
    </div>

    <div class="mb-5 max-w-4xl mx-auto">
        <x-search-header heading="Fabric Report"/>
        <x-progress-bar :steps="['Generate Fabric Report', 'Preview']" :currentStep="1" />
    </div>

    <form id="form" action="{{ route('reports.fabric') }}" method="get"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto relative overflow-hidden">
        <x-form-title-bar title="Generate Fabric Report" />

        <div class="step1 space-y-4">
            <input type="hidden" name="mode" id="mode" value="{{ $fabricReportType }}">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="fabric-filter" data-modes="worker tag article">
                    <x-select
                        label="Range"
                        name="range"
                        id="range"
                        :options="[
                            'current_month' => ['text' => 'Current Month'],
                            'last_month' => ['text' => 'Last Month'],
                            'last_three_months' => ['text' => 'Last Three Months'],
                            'last_six_months' => ['text' => 'Last Six Months'],
                            'custom' => ['text' => 'Custom'],
                        ]"
                        showDefault
                        onchange="applyFabricRange(this.value)"
                    />
                </div>

                <div class="fabric-filter" data-modes="worker tag article">
                    <x-input label="Date From" name="date_range_start" id="date_range_start" type="date" validateMax max="{{ now()->toDateString() }}" />
                </div>
                <div class="fabric-filter" data-modes="worker tag article">
                    <x-input label="Date To" name="date_range_end" id="date_range_end" type="date" validateMax max="{{ now()->toDateString() }}" />
                </div>
                <div class="fabric-filter" data-modes="worker tag article">
                    <x-input label="Fabric" name="fabric" id="fabric" placeholder="Filter by fabric" />
                </div>
                <div class="fabric-filter" data-modes="worker tag article">
                    <x-input label="Tag" name="tag" id="tag" placeholder="Filter by tag" />
                </div>
                <div class="fabric-filter" data-modes="tag">
                    <x-input label="Supplier" name="supplier_name" id="supplier_name" placeholder="Filter by supplier" />
                </div>
                <div class="fabric-filter" data-modes="worker article">
                    <x-input label="Worker" name="worker_name" id="worker_name" placeholder="Filter by worker" />
                </div>
                <div class="fabric-filter" data-modes="article">
                    <x-input label="Article No" name="article_no" id="article_no" placeholder="Filter by article no" />
                </div>
                <div class="fabric-filter" data-modes="worker tag article">
                    <x-input label="Type" name="source" id="source" placeholder="Received, issued, returned, production" />
                </div>
            </div>
        </div>

        <div class="step2 hidden space-y-4 text-black h-[35rem] overflow-y-auto my-scrollbar-2">
            <div id="fabric-preview-empty" class="hidden text-center text-sm text-[var(--secondary-text)] py-16">
                No fabric report data found.
            </div>
            <div id="fabric-preview-container" class="h-full relative"></div>
        </div>
    </form>

@endsection

@push('page-scripts')
<script defer src="{{ asset('js/pages/reports-fabric.js') }}"></script>
<script>
    window.__reportsFabric = {
        authLayout: @json($authLayout),
        fabricUrl: @json(route('reports.fabric')),
        setTypeUrl: @json(route('set-fabric-report-type')),
        csrfToken: @json(csrf_token()),
        fabricReportType: @json($fabricReportType),
        companyData: @json($companyData),
        selectedBranchLabels: @json($selectedBranchLabels),
    };
</script>
@endpush
