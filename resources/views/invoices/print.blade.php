@extends('app')
@section('title', 'Generate Invoice | ' . $client_company->name)
@section('content')
    <div id="invoice-container" class="hidden"></div>

@endsection

@push('page-styles')
<style>
    body.print-only {
        background: #fff;
    }
    body.print-only aside,
    body.print-only #logoutModal,
    body.print-only #page-loader,
    body.print-only #messageBox,
    body.print-only #notificationBox,
    body.print-only .left_actions {
        display: none !important;
    }
    body.print-only .wrapper {
        width: 100%;
        margin: 0;
    }
    body.print-only main {
        padding: 0 !important;
        background: #fff !important;
        border-radius: 0 !important;
        overflow: visible !important;
    }
    @media print {
        body {
            background: #fff !important;
        }
        aside,
        #logoutModal,
        #page-loader,
        #messageBox,
        #notificationBox,
        .left_actions {
            display: none !important;
        }
        main {
            padding: 0 !important;
            background: #fff !important;
            border-radius: 0 !important;
            overflow: visible !important;
        }
    }
</style>
@endpush

@push('page-scripts')
<script defer src="{{ asset('js/pages/invoices-print.js') }}?v={{ config('app.version', 'local') }}"></script>
<script>
        document.addEventListener('DOMContentLoaded', function () {
            document.body.classList.add('print-only');
        });
        window.__invoicesPrint = {
            invoices: @json($invoicePayloads ?? $invoices),
            companyData: @json($client_company),
            companyLogoBase: '{{ asset("images") }}',
        };
    </script>
@endpush
