@extends('app')
@section('title', 'Show Employees | ' . $client_company->name)
@section('content')
    @php
        $searchFields = [
            "Employee Name" => [
                "id" => "employee_name",
                "type" => "text",
                "placeholder" => "Enter employee name",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "employee_name",
            ],
            "Phone" => [
                "id" => "phone_number",
                "type" => "text",
                "placeholder" => "Enter phone number",
                "oninput" => "runDynamicFilter()",
                "dataFilterPath" => "phone_number",
            ],
            "Category" => [
                "id" => "category",
                "type" => "select",
                "options" => [
                            'staff' => ['text' => 'Staff'],
                            'worker' => ['text' => 'Worker'],
                        ],
                "onchange" => "runDynamicFilter()",
                "dataFilterPath" => "category",
            ],
            "Type" => [
                "id" => "type",
                "type" => "select",
                "options" => $all_types,
                "onchange" => "runDynamicFilter()",
                "dataFilterPath" => "type",
            ]
        ];
    @endphp
    <div>
        <div class="w-[80%] mx-auto">
            <x-search-header heading="Employees" :search_fields=$searchFields/>
        </div>

        <!-- Main Content -->
        <section class="text-center mx-auto">
            <div class="show-box mx-auto w-[80%] h-[70vh] bg-[var(--secondary-bg-color)] rounded-xl shadow pt-8.5 pr-2 relative">
                <x-form-title-bar printBtn title="Show Employees" changeLayoutBtn layout="{{ $authLayout }}" resetSortBtn />

                <div class="absolute bottom-3 right-3 flex items-center gap-2 w-fll z-50">
                    <x-section-navigation-button link="{{ route('employees.create') }}" title="Add New Employee" icon="fa-plus" />
                </div>

                <div class="details h-full z-40">
                    <div class="container-parent h-full">
                        <div class="card_container px-3 h-full flex flex-col">
                            <div id="table-head"class="grid grid-cols-6 bg-[var(--h-bg-color)] rounded-lg font-medium py-2 mt-4">
                                <div onclick="sortByThis(this)" class="cursor-pointer text-left pl-5">Employee Name</div>
                                <div onclick="sortByThis(this)" class="cursor-pointer text-left pl-5">Urdu Title</div>
                                <div onclick="sortByThis(this)" class="cursor-pointer text-left pl-5">Category</div>
                                <div onclick="sortByThis(this)" class="cursor-pointer text-center">Type</div>
                                <div onclick="sortByThis(this)" class="cursor-pointer text-center">Balance</div>
                                <div onclick="sortByThis(this)" class="cursor-pointer text-right pr-5">Status</div>
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
    </div>

    <script>
        let currentUserRole = '{{ Auth::user()->role }}';
        let authLayout = '{{ $authLayout }}';

        function createRow(data) {
            return `
            <div id="${data.id}" oncontextmenu='${data.oncontextmenu || ""}' onclick='${data.onclick || ""}'
                class="item row relative group grid text- grid-cols-6 border-b border-[var(--h-bg-color)] items-center py-2 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out"
                data-json='${JSON.stringify(data)}'>

                <span class="text-left pl-5 capitalize">${data.name}</span>
                <span class="text-left pl-5">${data.urdu_title}</span>
                <span class="text-left pl-5 capitalize">${data.details["Category"]}</span>
                <span class="text-center capitalize">${data.details["Type"]}</span>
                <span class="text-right">${data.details["Balance"]}</span>
                <span class="text-right pr-5 capitalize ${data.status === 'active' ? 'text-[var(--border-success)]' : 'text-[var(--border-error)]'}">
                    ${data.status}
                </span>
            </div>`;
        }

        // const fetchedData = [];
        // let allDataArray = fetchedData.map(item => {
        //     return {
        //         id: item.id,
        //         uId: item.id,
        //         status: item.status,
        //         image: item.profile_picture == 'default_avatar.png' ? '/images/default_avatar.png' : `/storage/uploads/images/${item.profile_picture}`,
        //         name: item.employee_name,
        //         urdu_title: item.urdu_title,
        //         phone_number: item.phone_number,
        //         details: {
        //             'Category': item.category,
        //             'Type': item.type.title.split('|')[0].trim(),
        //             'Balance': formatNumbersWithDigits(item.balance ?? 0, 1, 1),
        //         },
        //         type: item.type.title,
        //         joining_date: item.joining_date,
        //         cnic_no: item.cnic_no,
        //         salary: item.salary,
        //         oncontextmenu: "generateContextMenu(event)",
        //         onclick: "generateModal(this)",
        //         profile: true,
        //         visible: true,
        //     };
        // });

        function generateContextMenu(e) {
            e.preventDefault();
            let item = e.target.closest('.item');
            let data = JSON.parse(item.dataset.json);

            let contextMenuData = {
                item: item,
                data: data,
                x: e.pageX,
                y: e.pageY,
                action: "{{ route('update-employee-status') }}",
                actions: [
                    {id: 'edit', text: 'Edit Employee', dataId: data.id},
                    {id: 'emp-form-in-modal', text: 'Show Form', onclick: `showEmployeeForm(${JSON.stringify(data)})`}
                ],
            };

            createContextMenu(contextMenuData);
        }

        function generateModal(item) {
            let modalDom = document.getElementById('modal')
            let data = JSON.parse(item.dataset.json);

            let modalData = {
                id: 'modalForm',
                uId: data.id,
                status: data.status,
                method: "POST",
                action: "{{ route('update-employee-status') }}",
                class: '',
                closeAction: 'closeModal()',
                image: data.image,
                name: data.name,
                details: {
                    'Category': data.details.Category,
                    'Type': data.details.Type,
                    'Phone Number': data.phone_number,
                    'Joining Date': data.joining_date,
                    'C.N.I.C No.': data.cnic_no,
                    'Balance': data.details.Balance,
                    ...(data.salary > 0 && { 'Salary': formatNumbersWithDigits(data.salary, 1, 1) }),
                },
                profile: true,
                bottomActions: [
                    {id: 'edit-in-modal', text: 'Edit Employee', dataId: data.id},
                    {id: 'emp-form-in-modal', text: 'Show Form', onclick: `showEmployeeForm(${JSON.stringify(data)})`}
                ],
            }

            createModal(modalData);
        }

        let companyData = @json($client_company);

        function showEmployeeForm(data) {
            let formFieldsData = [
                {"label": "Name", "text": data.name},
                {"label": "Category", "text": data.details.Category},
                {"label": "Type", "text": data.details.Type},
                {"label": "Joining Date", "text": data.joining_date,},
                {"label": "Phone Number", "text": data.phone_number},
                {"label": "C.N.I.C No.", "text": data.cnic_no},
            ]
            let modalData = {
                id: 'modalForm',
                preview: {type: 'form', data: { formFields: formFieldsData }, document: 'Employee Form', size: "A5"},
                bottomActions: [
                    {id: 'print', text: 'Print Form', onclick: 'printForm(this)'}
                ],
            }

            createModal(modalData);
        }

        function printForm(elem) {
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
                        <title>Print Employee Form</title>
                        ${headContent} <!-- Copy current styles -->
                        <style>
                            @page {
                                size: A5 portrait; /* ✅ Default A5 */
                                margin: 0;
                            }

                            body {
                                padding: 0.08in 0.25in 0.08in 0.25in;
                                margin: 0;
                                width: 148mm; /* A5 width */
                                height: 210mm; /* A5 height */
                            }

                            .preview-container .banner {
                                margin-top: 0;
                            }

                            .preview-container .footer {
                                margin-top: 0;
                            }
                        </style>
                    </head>
                    <body>
                        <div class="preview-container">${preview.innerHTML}</div> <!-- Add the preview content, only innerHTML -->
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
    </script>
@endsection
