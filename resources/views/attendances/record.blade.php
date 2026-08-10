@extends('app')
@section('title', 'Add Physical Quantities | ' . $client_company->name)
@section('content')
    <!-- Main Content -->
    <div class="max-w-2xl mx-auto">
        <x-search-header heading="Record Attendance" link linkText="Generate Slip"
            linkHref="{{ route('attendances.generate-slip') }}" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('attendances.store') }}" method="post"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-2xl mx-auto relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Record Attendance" />

        <div>
            <x-file-upload id="inputFile" placeholder="{{ asset('images/xls_icon.png') }}"
                uploadText="Upload XLS file" class="h-50" imageSize="12" accept=".xlsx, .xls" />
        </div>

        <!-- hidden input for formatted data -->
        <input type="hidden" name="attendances" id="attendancesInput">

        <div class="w-full flex justify-end mt-4">
            <button type="submit"
                class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all 0.3s ease-in-out cursor-pointer">
                <i class='fas fa-save mr-1'></i> Save
            </button>
        </div>
    </form>

@endsection

@push('page-scripts')
<script defer src="{{ asset('vendor/xlsx/xlsx.full.min.js') }}"></script>
<script defer src="{{ asset('js/pages/attendances-record.js') }}"></script>
<script>
        window.__attendancesRecord = {
            invalidEmployees: @json(session('invalid_employees') ?? []),
        };
    </script>
@endpush
