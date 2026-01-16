@extends('app')
@section('title', 'Show Articles | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Invoice No" => [
                "id" => "invoice_no",
                "type" => "text",
                "placeholder" => "Enter invoice no.",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "invoice_no",
            ],
            "Reff. No." => [
                "id" => "reff_no",
                "type" => "text",
                "placeholder" => "Enter reff. no.",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "reff_no",
            ],
            "Customer Name" => [
                "id" => "customer_name",
                "type" => "text",
                "placeholder" => "Enter customer name",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "customer_name",
            ],
            "City" => [
                "id" => "city",
                "type" => "text",
                "placeholder" => "Enter city",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "city",
            ],
            "Date Range" => [
                "id" => "date_range_start",
                "type" => "date",
                "id2" => "date_range_end",
                "type2" => "date",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "date",
            ]
        ];
    @endphp
    <div class="w-[80%] mx-auto">
        <x-search-header heading="Invoices" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn title="Show Invoices" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn />

            <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                <x-section-navigation-button link="{{ route('invoices.create') }}" title="Add New Invoice" icon="fa-plus" />
            </div>

            <div class="details h-full z-40">
                <div class="container-parent h-full my-scrollbar-2">
                    <div class="card_container px-3 h-full flex flex-col">
                        <div id="table-head" class="grid grid-cols-5 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Invoice No.</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Reff. No.</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Customer</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Date</div>
                            <div class="text-center cursor-pointer" onclick="sortByThis(this)">Amount</div>
                        </div>
                        <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                        <div class="overflow-y-auto grow my-scrollbar-2">
                            <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        let companyData = @json($client_company);
        let authLayout = '{{ $authLayout }}';

        function createRow(data) {
            return `
                <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                    class="item row relative group grid grid-cols-5 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                    data-json='${JSON.stringify(data)}'>

                    <span class="text-center">${data.name}</span>
                    <span class="text-center">${data.details["Reff. No."]}</span>
                    <span class="text-center">${data.details["Customer"]}</span>
                    <span class="text-center">${data.details['Date']}</span>
                    <span class="text-center">${data.details['Amount']}</span>
                </div>`;
        }

        // const fetchedData = [];
        // let allDataArray = fetchedData.map(item => {
        //     return {
        //         id: item.id,
        //         name: item.invoice_no,
        //         details: {
        //             'Customer': item.customer.customer_name + ' | ' + item.customer.city.title,
        //             'Date': formatDate(item.date),
        //             'Amount': formatNumbersWithDigits(item.netAmount, 1, 1),
        //             'Reff. No.': item.order_no ?? item.shipment_no
        //         },
        //         reff_no: item.order_no ?? item.shipment_no,
        //         data: item,
        //         oncontextmenu: "generateContextMenu(event)",
        //         onclick: "generateModal(this)",
        //         visible: true,
        //     };
        // });

        function printInvoice(elem) {
            closeAllDropdowns();

            if (elem.parentElement.tagName.toLowerCase() === 'li') {
                elem.parentElement.parentElement.querySelector('#show-details').click();
                document.getElementById('modalForm').parentElement.classList.add('hidden');
            }

            const preview = document.getElementById('preview-container'); // preview content

            // Pehle se agar koi iframe hai to usko remove karein
            let oldIframe = document.getElementById('printIframe');
            if (oldIframe) {
                oldIframe.remove();
            }

            // Naya iframe banayein
            let printIframe = document.createElement('iframe');
            printIframe.id = "printIframe";
            printIframe.style.position = "absolute";
            printIframe.style.width = "0px";
            printIframe.style.height = "0px";
            printIframe.style.border = "none";
            printIframe.style.display = "none"; // ✅ Hide iframe

            // Iframe ko body me add karein
            document.body.appendChild(printIframe);

            let printDocument = printIframe.contentDocument || printIframe.contentWindow.document;
            printDocument.open();

            // ✅ Current page ke CSS styles bhi iframe me inject karenge
            const headContent = document.head.innerHTML;

            printDocument.write(`
                <html>
                    <head>
                        <title>Print Invoice</title>
                        ${headContent} <!-- Copy current styles -->
                        <style>
                            @media print {

                                body {
                                    margin: 0;
                                    padding: 0;
                                    width: 210mm; /* A4 width */
                                    height: 302.5mm; /* A4 height */

                                }

                                .preview-container, .preview-container * {
                                    page-break-inside: avoid;
                                }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="preview-container">${preview.innerHTML}</div> <!-- Add the preview content, only innerHTML -->
                        <div id="preview-container" class="preview-container">${preview.innerHTML}</div> <!-- Add the preview content, only innerHTML -->
                    </body>
                </html>
            `);

            printDocument.close();

            // Wait for iframe to load and print
            printIframe.onload = () => {
                printDocument
                    .querySelectorAll('.preview')
                    .forEach(p => p.classList.remove('py-6'));

                printDocument
                    .querySelectorAll('#banner')
                    .forEach(p => p.classList.remove('mt-8'));

                printDocument
                    .querySelectorAll('.footer')
                    .forEach(p => p.classList.remove('mb-4'));

                let invoiceCopys = printDocument.querySelectorAll('#preview-container .preview-copy');
                if (invoiceCopys) {
                    invoiceCopys.forEach(invoiceCopy => {
                        invoiceCopy.textContent = "Invoice Copy: Office";
                    });
                }

                // Listen for after print in the iframe's window
                printIframe.contentWindow.onafterprint = () => {
                    console.log("Print dialog closed");
                };

                setTimeout(() => {
                    printIframe.contentWindow.focus();
                    printIframe.contentWindow.print();
                }, 1000);

                document.getElementById('modalForm').parentElement.remove();
            };
        }

        function generateContextMenu(e) {
            e.preventDefault();
            let item = e.target.closest('.item');
            let data = JSON.parse(item.dataset.json);

            let contextMenuData = {
                item: item,
                data: data,
                x: e.pageX,
                y: e.pageY,
                actions: [
                    {id: 'print', text: 'Print Invoice', onclick: 'printInvoice(this)'}
                ]
            };

            createContextMenu(contextMenuData);
        }

        function generateModal(item) {
            let data = JSON.parse(item.dataset.json);

            let modalData = {
                id: 'modalForm',
                preview: {type: 'invoice', data: data.data, document: 'Sales Invoice'},
                bottomActions: [
                    {id: 'print', text: 'Print Invoice', onclick: 'printInvoice(this)'}
                ],
            }

            createModal(modalData);
        }
    </script>
@endsection
