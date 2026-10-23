@extends('app')
@section('title', 'Business Dashboard | ' . $client_company->name)

@section('content')
    <div class="mx-auto flex h-[calc(100vh-9.5rem)] w-[94%] min-h-[35rem] flex-col gap-4 pt-10 sm:pt-8">
        <header class="flex flex-wrap items-start justify-between gap-3 px-1">
            <div>
                <p class="mb-1 text-[10px] font-semibold uppercase tracking-[0.22em] text-[var(--primary-color)]">Owner view</p>
                <h1 class="text-xl font-bold tracking-tight text-[var(--text-color)] sm:text-2xl">Business Dashboard</h1>
                <p class="mt-1 max-w-2xl text-xs text-[var(--secondary-text)] sm:text-sm">A quick view of your business position, activity and attention items.</p>
            </div>
            <div class="flex w-full flex-wrap items-end justify-end gap-3 sm:w-auto">
                <span data-dashboard-range-label class="mb-2 text-xs font-medium text-[var(--secondary-text)] sm:mb-1">
                    {{ $summary['meta']['period_label'] }}
                </span>
                <div class="dashboard-range w-full sm:w-52">
                    <x-select
                        label="Range"
                        name="dashboard_range"
                        id="dashboard_range"
                        value="current_month"
                        :options="[
                            'current_month' => ['text' => 'This Month'],
                            'last_month' => ['text' => 'Last Month'],
                            'last_three_months' => ['text' => 'Last 3 Months'],
                            'last_six_months' => ['text' => 'Last 6 Months'],
                            'custom' => ['text' => 'Custom Range'],
                        ]"
                        required
                        onchange="applyDashboardRange(this.value)"
                    />
                </div>
                <button type="button" data-dashboard-refresh aria-label="Refresh dashboard"
                    class="mb-1 shrink-0 rounded-lg bg-[var(--primary-color)] px-4 py-2 text-sm font-medium text-white transition-all duration-300 hover:bg-[var(--h-primary-color)]">
                    Refresh
                </button>
            </div>
        </header>

        <div id="dashboard-custom-range" class="hidden flex-wrap items-end justify-between gap-3 border-b border-[var(--glass-border-color)]/20 px-1 pb-3">
            <div class="flex w-full flex-wrap items-end gap-2 sm:w-auto sm:gap-3">
                <div class="dashboard-date-field min-w-0 flex-1 sm:w-36 sm:flex-none">
                    <x-input label="From" name="dashboard_from" id="dashboard_from" type="date" value="{{ $summary['meta']['from'] }}" class="dashboard-date-input" required />
                </div>
                <div class="dashboard-date-field min-w-0 flex-1 sm:w-36 sm:flex-none">
                    <x-input label="To" name="dashboard_to" id="dashboard_to" type="date" value="{{ $summary['meta']['to'] }}" class="dashboard-date-input" required />
                </div>
                <button type="button" data-dashboard-apply
                    class="rounded-lg bg-[var(--primary-color)] px-4 py-2 text-sm font-medium transition-all duration-300 hover:scale-[0.98] hover:bg-[var(--h-primary-color)]">
                    Apply
                </button>
            </div>
        </div>

        <div id="dashboard-summary" class="my-scrollbar-2 min-h-0 grow overflow-y-auto px-1 pb-5">
            @include('dashboard.partials.summary', ['summary' => $summary])
        </div>
    </div>
@endsection

@push('page-styles')
    <style>
        .dashboard-card,
        .dashboard-panel {
            border-color: color-mix(in srgb, var(--glass-border-color) 24%, transparent);
            background: var(--secondary-bg-color);
            box-shadow: 0 5px 18px rgb(15 23 42 / 0.045);
        }

        .dashboard-card {
            min-height: 7rem;
        }

        .dashboard-panel {
            border-radius: 1rem;
        }

        .dashboard-date-field .form-group > span {
            margin-bottom: 0.25rem;
        }

        .dashboard-date-input {
            border-color: color-mix(in srgb, var(--glass-border-color) 30%, transparent) !important;
            background: var(--secondary-bg-color) !important;
            padding-top: 0.4rem !important;
            padding-bottom: 0.4rem !important;
        }

        @media (max-width: 640px) {
            .dashboard-card {
                min-height: 6.25rem;
            }
        }
    </style>
@endpush

@push('page-scripts')
    <script>
        window.__ownerDashboard = {
            url: @json(route('home.dashboard-data')),
            summary: @json($summary),
        };
    </script>
    <script defer src="{{ asset('js/pages/owner-dashboard.js') }}?v={{ config('app.version', 'local') }}.1"></script>
@endpush
