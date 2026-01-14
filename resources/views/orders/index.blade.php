@extends('app')
@section('title', 'Show Orders | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Order No" => [
                "id" => "order_no",
                "type" => "text",
                "placeholder" => "Enter order no",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "name",
            ],
            "Customer Name" => [
                "id" => "customer_name",
                "type" => "text",
                "placeholder" => "Enter customer name",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "details.Customer",
            ],
            'Status' => [
                'id' => 'status',
                'type' => 'select',
                'options' => [
                    'pending' => ['text' => 'Pending'],
                    'partially_invoiced' => ['text' => 'Partially Invoiced'],
                    'invoiced' => ['text' => 'Invoiced'],
                ],
                'dataFilterPath' => 'status',
            ],
            "Date Range" => [
                "id" => "date_range_start",
                "type" => "date",
                "id2" => "date_range_end",
                "type2" => "date",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "data.date",
            ]
        ];
    @endphp

    <div class="w-[80%] mx-auto">
        <x-search-header heading="Orders" :search_fields=$searchFields/>
    </div>

    <!-- Main Content -->
    <section class="text-center mx-auto ">
        <div
            class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] border border-[var(--glass-border-color)]/20 rounded-xl shadow pt-8.5 relative">
            <x-form-title-bar printBtn title="Show Orders" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn />

            @if (count($orders) > 0)
                <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                    <x-section-navigation-button link="{{ route('orders.create') }}" title="Add New Order" icon="fa-plus" />
                </div>

                <div class="details h-full z-40">
                    <div class="container-parent h-full">
                        <div class="card_container px-3 h-full flex flex-col">
                            <div id="table-head" class="grid grid-cols-4 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 hidden mt-4">
                                <div class="text-center cursor-pointer" onclick="sortByThis(this)">Order No.</div>
                                <div class="text-center cursor-pointer" onclick="sortByThis(this)">Customer</div>
                                <div class="text-center cursor-pointer" onclick="sortByThis(this)">Date</div>
                                <div class="text-center cursor-pointer" onclick="sortByThis(this)">Status</div>
                            </div>
                            <p id="noItemsError" style="display: none" class="text-sm text-[var(--border-error)] mt-3">No items found</p>
                            <div class="overflow-y-auto grow my-scrollbar-2">
                                <div class="search_container grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 grow">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="no-records-message w-full h-full flex flex-col items-center justify-center gap-2">
                    <h1 class="text-sm text-[var(--secondary-text)] capitalize">No Order Found</h1>
                    <a href="{{ route('orders.create') }}"
                        class="text-sm bg-[var(--primary-color)] text-[var(--text-color)] px-4 py-2 rounded-md hover:bg-[var(--h-primary-color)] hover:scale-105 hover:mb-2 transition-all duration-300 ease-in-out font-semibold">Add
                        New</a>
                </div>
            @endif
        </div>
    </section>

    <script>
        let companyData = @json($client_company);
        let authLayout = '{{ $authLayout }}';

        function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group grid grid-cols-4 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="text-center">${data.name}</span>
                <span class="text-center">${data.details["Customer"]}</span>
                <span class="text-center">${data.details['Date']}</span>
                <span class="text-center capitalize">${data.status.replace('_', ' ')}</span>
            </div>`;
        }

        const fetchedData = @json($orders);
        let allDataArray = fetchedData.map(item => {
            return {
                id: item.id,
                name: item.order_no,
                details: {
                    'Customer': item.customer.customer_name + ' | ' + item.customer.city.title,
                    'Date': formatDate(item.date),
                },
                status: item.status,
                data: item,
                oncontextmenu: "generateContextMenu(event)",
                onclick: "generateModal(this)",
                visible: true,
            };
        });

        function printOrder(elem) {
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
                        <title>Print Order</title>
                        ${headContent} <!-- Copy current styles -->
                        <style>
                            @media print {

                                body {
                                    margin: 0;
                                    padding: 0;
                                    width: 210mm; /* A4 width */
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

                let orderCopys = printDocument.querySelectorAll('#preview-container .preview-copy');
                if (orderCopys) {
                    orderCopys.forEach(orderCopy => {
                        orderCopy.textContent = "Order Copy: Office";
                    })
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
                    {id: 'print', text: 'Print Order', onclick: 'printOrder(this)'}
                ]
            };

            createContextMenu(contextMenuData);
        }

        function generateModal(item) {
            let data = JSON.parse(item.dataset.json);

            let modalData = {
                id: 'modalForm',
                preview: {type: 'order', data: data.data, document: 'Sales Order'},
                bottomActions: [
                    {id: 'print', text: 'Print Order', onclick: 'printOrder(this)'}
                ],
            }

            createModal(modalData);
        }
    </script>
@endsection
