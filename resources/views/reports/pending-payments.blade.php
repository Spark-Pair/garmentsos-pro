@extends('app')
@section('title', 'Pending Payments | ' . $client_company->name)
@section('content')
@php
    $companyData = $client_company;
@endphp
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-4xl mx-auto">
        <x-search-header heading="Pending Payments"/>
        <x-progress-bar :steps="['Select Date', 'Preview']" :currentStep="1" />
    </div>
    <!-- Form -->
    <form id="form" action="{{ route('orders.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto  relative overflow-hidden">
        <x-form-title-bar title="Pending Payments" />

        <!-- Step 1: Select Date -->
        <div class="step1 space-y-4 ">
            <div class="flex">
                <div class="grow">
                    <!-- date -->
                    <x-input
                        label="Date"
                        name="date"
                        id="date"
                        type="date"
                        value="{{ now()->toDateString() }}"
                        required
                    />
                </div>
            </div>
        </div>


        <!-- Step 2: view order -->
        <div class="step2 hidden space-y-4 text-black h-[35rem]">
            @if (isset($data))
                {{-- First Page (26 rows) --}}
                <div id="preview-container" class="h-full relative overflow-y-auto my-scrollbar-2">
                    <div id="preview-page" class="w-[210mm] mx-auto overflow-hidden relative bg-white rounded-md pt-6 pb-0">
                        <div id="preview" class="preview flex flex-col h-full">
                            <div id="preview-document" class="preview-document flex flex-col h-full px-2">
                                {{-- Table --}}
                                <div id="preview-body" class="preview-body w-[95%] grow mx-auto">
                                    {{-- Multiple Slips --}}
                                    @foreach ($data as $item)
                                        <div class="slip w-full border border-gray-700 rounded-lg p-1 overflow-hidden text-xs tracking-wide">
                                            {{-- Header --}}
                                            <div class="head w-full px-4 py-1.5 border border-gray-700 text-center rounded-md mb-1">
                                                <div class="font-medium">{{ $item['customer'] }}</div>
                                            </div>

                                            <div class="table w-full">
                                                {{-- Table Header --}}
                                                <div class="thead w-full">
                                                    <div class="tr flex items-center w-full px-4 py-1.5 bg-[var(--primary-color)] text-white text-center rounded-md">
                                                        <div class="th w-[10%] font-medium">S.No</div>
                                                        <div class="th w-1/6 font-medium">Date</div>
                                                        <div class="th w-1/6 font-medium">Method</div>
                                                        <div class="th w-1/6 font-medium">Reff. No.</div>
                                                        <div class="th w-1/6 font-medium">Amount</div>
                                                        <div class="th w-1/6 font-medium">Received</div>
                                                        <div class="th w-1/6 font-medium">Balance</div>
                                                    </div>
                                                </div>

                                                {{-- Table Body --}}
                                                <div id="tbody" class="tbody w-full">
                                                    @foreach ($item['payments'] as $payment)
                                                        <div class="w-full px-4 py-1.5 text-center border-b border-gray-700 last:border-0">
                                                            <div class="tr flex items-center">
                                                                <div class="td w-[10%]">{{ $loop->iteration }}</div>
                                                                <div class="td w-1/6">{{ \Carbon\Carbon::parse($payment['date'])->format('d-M-Y, D') }}</div>
                                                                <div class="td w-1/6">{{ $payment['method'] }}</div>
                                                                <div class="td w-1/6">{{ $payment['reff_no'] }}</div>
                                                                <div class="td w-1/6">{{ number_format($payment['amount']) }}</div>
                                                                <div class="td w-1/6">{{ number_format($payment['received_amount']) }}</div>
                                                                <div class="td w-1/6">{{ number_format($payment['balance']) }}</div>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>

                                            {{-- footer --}}
                                            <div class="footer grid grid-cols-3 gap-1 border-t border-gray-700 pt-1">
                                                <div class="px-4 py-1.5 border border-gray-700 text-center rounded-md">
                                                    <div class="font-medium">Total Amount : {{ number_format($item['totals']['amount']) }}</div>
                                                </div>
                                                <div class="px-4 py-1.5 border border-gray-700 text-center rounded-md">
                                                    <div class="font-medium">Total Recived : {{ number_format($item['totals']['received_amount']) }}</div>
                                                </div>
                                                <div class="px-4 py-1.5 border border-gray-700 text-center rounded-md">
                                                    <div class="font-medium">Balance : {{ number_format($item['totals']['balance']) }}</div>
                                                </div>
                                            </div>
                                        </div>

                                        <hr class="w-[85%] mx-auto my-3 border-gray-700/60">
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </form>

    <script>
        function getPendingPayments() {
            const date = document.getElementById('date').value;

            $.ajax({
                url: "{{ route('reports.pending-payments') }}",
                type: 'GET',
                data: {
                    _token: "{{ csrf_token() }}",
                    date: date,
                },
                success: function(response) {
                    renderPendingPayments(response);
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching statement:', error);
                }
            });
        }

        function renderPendingPayments(response) {
            // Parse the HTML string into a jQuery object
            const $responseHtml = $(response);

            // Find the .step2 element inside the response
            const $previewInResponse = $responseHtml.find('.step2');

            if ($previewInResponse.length) {
                // Replace the current page's .step2 innerHTML
                $('.step2').html($previewInResponse.html());
            } else {
                console.warn('.step2 not found in response HTML.');
            }
        }

        function onClickOnPrintBtn() {
            const preview = document.getElementById('preview-page'); // preview content

            // ✅ Clone so that original DOM safe rahe
            let clone = preview.cloneNode(true);

            // ✅ Sirf direct child <hr> (pages ke beech) remove karo
            clone.querySelectorAll(":scope > hr").forEach(hr => hr.remove());

            // Agar pehle se iframe hai to usko hatao
            let oldIframe = document.getElementById('printIframe');
            if (oldIframe) {
                oldIframe.remove();
            }

            // Naya iframe banao
            let printIframe = document.createElement('iframe');
            printIframe.id = "printIframe";
            printIframe.style.position = "absolute";
            printIframe.style.width = "0px";
            printIframe.style.height = "0px";
            printIframe.style.border = "none";
            printIframe.style.display = "none";

            document.body.appendChild(printIframe);

            let printDocument = printIframe.contentDocument || printIframe.contentWindow.document;
            printDocument.open();

            // ✅ Copy styles from current page
            const headContent = document.head.innerHTML;

            printDocument.write(`
                <html>
                <head>
                    <title>Print Pending Payments</title>
                    ${headContent}
                    <style>
                    @page {
                        size: A4;
                        margin: 0.19in;
                    }

                    body {
                        margin: 0;
                        padding: 0;
                        background: #fff;
                    }

                    /* ✅ Prevent half-slip breaking */
                    @media print {
                        .slip {
                            page-break-inside: avoid;
                            break-inside: avoid;
                        }

                        /* ✅ Allow multiple slips per page */
                        .slip + hr {
                            page-break-after: auto; /* only break if needed */
                        }

                        /* Remove weird overflow issues */
                        #preview-page {
                            overflow: visible !important;
                        }
                    }
                    </style>
                </head>
                <body>
                    ${clone.innerHTML}
                </body>
                </html>
            `);

            printDocument.close();

            // Print jab iframe load ho jaye
            printIframe.onload = () => {
                printIframe.contentWindow.focus();
                printIframe.contentWindow.print();
            };
        }

        function validateForNextStep() {
            getPendingPayments();
            return true;
        }
    </script>
@endsection
