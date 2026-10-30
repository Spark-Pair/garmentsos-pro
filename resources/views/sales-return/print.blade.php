@extends('app')

@section('title', 'Sales Return ' . $returnNo)

@section('content')
    <div id="sales-return-print-preview" class="flex justify-center p-4"></div>
@endsection

@push('page-styles')
<style>
    body.sales-return-print-only,
    body.sales-return-print-only main {
        background: #fff !important;
    }

    #sales-return-print-preview {
        color: #111;
    }

    @media print {
        body { background: #fff !important; }
        aside, #logoutModal, #page-loader, #messageBox, #notificationBox, .left_actions { display: none !important; }
        main { padding: 0 !important; background: #fff !important; border-radius: 0 !important; overflow: visible !important; }
    }
</style>
@endpush

@push('page-scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.body.classList.add('sales-return-print-only');

        const preview = document.getElementById('sales-return-print-preview');
        if (!preview || !window.DocumentPreview || !window.DocumentPrint) return;

        window.DocumentPreview.renderInto(preview, {
            type: 'sales_return',
            size: 'A5',
            document: 'Sales Return',
            data: @json($previewData),
        });

        window.DocumentPrint.printPreview({
            title: 'Print Sales Return',
            preview,
            delay: 500,
            afterPrint: function () {
                const indexUrl = @json(route('sales-returns.index'));
                const referrerIsSalesReturns = document.referrer
                    && new URL(document.referrer).origin === window.location.origin
                    && new URL(document.referrer).pathname.replace(/\/$/, '') === indexUrl.replace(window.location.origin, '').replace(/\/$/, '');

                if (referrerIsSalesReturns && window.history.length > 1) {
                    window.history.back();
                    return;
                }

                window.location.assign(indexUrl);
            },
        });
    });
</script>
@endpush
