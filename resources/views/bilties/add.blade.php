@extends('app')
@section('title', 'Add Bilty | ' . $client_company->name)
@section('content')
    <!-- Main Content -->
    <!-- Progress Bar -->
    <div class="mb-5 max-w-6xl mx-auto">
        <x-search-header heading="Add Bilty" link linkText="Show Bilties" linkHref="{{ route('bilties.index') }}"/>
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('bilties.store') }}" method="post" enctype="multipart/form-data"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-6xl mx-auto relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Add Bilty" />

        <div class="space-y-4 ">
            <div class="flex items-end gap-4">
                {{-- cargo date --}}
                <div class="grow">
                    <x-input label="Date" name="date" id="date" type="date" onchange="trackStateOfgenerateBtn(this)"
                        validateMax max='{{ now()->toDateString() }}' validateMin
                        min="2024-01-01" required />
                </div>

                <button id="generateListBtn" type="button"
                    class="bg-[var(--primary-color)] px-4 py-2 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out text-nowrap cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">Select Invoices</button>
            </div>
            {{-- cargo-list-table --}}
            <div id="cargo-list-table" class="w-full text-left text-sm">
                <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                    <div class="w-[7%]">S.No.</div>
                    <div class="w-1/6">Date</div>
                    <div class="w-[11%]">Bill No.</div>
                    <div class="w-[13%]">Cottons</div>
                    <div class="w-[17%]">Customer</div>
                    <div class="w-[10%]">City</div>
                    <div class="w-1/6">Bilty No.</div>
                    <div class="w-1/6">Cargo</div>
                    <div class="w-[8%] text-center">Action</div>
                </div>
                <div id="cargo-list" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    <div class="text-center bg-[var(--h-bg-color)] rounded-lg py-3 px-4">No Rates Added</div>
                </div>
            </div>

            <input type="hidden" name="invoices_array" id="invoices" value="">
            <div class="w-full grid grid-cols-1 text-sm mt-5 text-nowrap">
                <div class="total-qty flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Cottons</div>
                    <div id="finalTotalCottons">0</div>
                </div>
            </div>
        </div>

        <div class="w-full flex justify-end mt-4">
            <button type="submit"
                class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all 0.3s ease-in-out cursor-pointer">
                <i class='fas fa-save mr-1'></i> Save
            </button>
        </div>
    </form>

    <script>
        let selectedInvoicesArray = [];

        const modalDom = document.getElementById("modal");
        const selectAllCheckbox = document.getElementById("select-all-checkbox");
        const generateListBtn = document.getElementById("generateListBtn");
        const cargoListDOM = document.getElementById('cargo-list');
        const finalTotalCottonsDOM = document.getElementById('finalTotalCottons');
        generateListBtn.disabled = true;
        let totalCottonCount = 0;

        function trackStateOfgenerateBtn(elem) {
            if (elem.value != "") {
                generateListBtn.disabled = false;
            } else {
                generateListBtn.disabled = true;
            }
        }

        let isModalOpened = false;

        generateListBtn.addEventListener('click', () => {
            generateModal();
        })

        function generateModal() {
            let data = @json($invoices);
            let cardData = [];

            if (data.length > 0) {
                cardData.push(...data.map(item => {
                    return {
                        id: item.id,
                        name: item.invoice_no,
                        details: {
                            '': `${item.customer.customer_name} | ${item.customer.city.title}`,
                        },
                        data: item,
                        checkbox: true,
                        checked: selectedInvoicesArray.some(selected => selected.id === item.id) || false,
                        onclick: 'selectThisInvoice(this)',
                    };
                }));
            }

            let modalData = {
                id: 'modalForm',
                class: 'h-[80%] w-full',
                cards: {name: 'Invoices', count: 3, data: cardData},
            }

            createModal(modalData);
        }

        function deselectInvoiceAtIndex(index) {
            if (index !== -1) {
                selectedInvoicesArray.splice(index, 1);
            }
        }

        function deselectThisInvoice(index) {
            totalCottonCount -= selectedInvoicesArray[index].cotton_count;

            deselectInvoiceAtIndex(index);

            renderList();

            finalTotalCottonsDOM.textContent = totalCottonCount;
        }

        function renderList() {
            if (selectedInvoicesArray.length > 0) {
                let clutter = "";
                selectedInvoicesArray.forEach((selectedInvoice, index) => {
                    let cottonCount = selectedInvoice.cotton_count ?? `<input oninput="setCottonCount(${selectedInvoice.id}, this.value)" class="cotton_count_inp w-[80%] border border-gray-600 bg-[var(--h-bg-color)] py-0.5 px-2 rounded-md text-xs focus:outline-none" type="number"/>`;
                    let cargoName = selectedInvoice.cargo_name ?? `<input oninput="setCargoName(${selectedInvoice.id}, this.value)" class="cotton_count_inp w-[80%] border border-gray-600 bg-[var(--h-bg-color)] py-0.5 px-2 rounded-md text-xs focus:outline-none" type="text" />`;
                    clutter += `
                        <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                            <div class="w-[7%]">${index+1}</div>
                            <div class="w-1/6">${formatDate(selectedInvoice.date)}</div>
                            <div class="w-[11%]">${selectedInvoice.invoice_no}</div>
                            <div class="w-[13%]">${cottonCount}</div>
                            <div class="w-[17%] capitalize">${selectedInvoice.customer.customer_name}</div>
                            <div class="w-[10%]">${selectedInvoice.customer.city.title}</div>
                            <div class="w-1/6">
                                <input oninput="setBiltyNo(${selectedInvoice.id}, this.value)" class="bilty_no w-[80%] border border-gray-600 bg-[var(--h-bg-color)] py-0.5 px-2 rounded-md text-xs focus:outline-none" type="number"/>
                            </div>
                            <div class="w-1/6">${cargoName}</div>
                            <div class="w-[8%] text-center">
                                <button onclick="deselectThisInvoice(${index})" type="button" class="text-[var(--danger-color)] cursor-pointer text-xs px-2 py-1 rounded-lg hover:text-[var(--h-danger-color)] transition-all duration-300 ease-in-out">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });

                cargoListDOM.innerHTML = clutter;
            } else {
                cargoListDOM.innerHTML =
                    `<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Invoices Yet</div>`;
            }
            updateInputInvoicesArray();
        }
        renderList();

        function updateInputInvoicesArray() {
            let inputinvoices = document.getElementById('invoices');
            let finalInovicesArray = selectedInvoicesArray.map(invoice => {
                return {
                    id: invoice.id,
                    cottonCount: invoice.cottonCount,
                    biltyNo: invoice.biltyNo,
                    cargoName: invoice.cargoName,
                }
            });
            inputinvoices.value = JSON.stringify(finalInovicesArray);
        }

        let companyData = @json($client_company);
        const previewDom = document.getElementById('preview');

        function generateCargoListPreview() {
            const cargoNameInpDom = document.getElementById("cargo_name");
            const dateInpDom = document.getElementById("date");

            if (selectedInvoicesArray.length > 0) {
                previewDom.innerHTML = `
                    <div id="preview-document" class="preview-document flex flex-col h-full">
                        <div id="preview-banner" class="preview-banner w-full flex justify-between items-center mt-8 pl-5 pr-8">
                            <div class="left">
                                <div class="company-logo">
                                    <img src="{{ asset('images/${companyData.logo}') }}" alt="garmentsos-pro"
                                        class="w-[12rem]" />
                                </div>
                            </div>
                            <div class="right">
                                <div>
                                    <h1 class="text-2xl font-medium text-[var(--primary-color)] pr-2">Cargo List</h1>
                                    <div class='mt-1'>${ companyData.phone_number }</div>
                                </div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div id="preview-header" class="preview-header w-full flex justify-between px-5">
                            <div class="left my-auto pr-3 text-sm text-gray-600 space-y-1.5">
                                <div class="cargo-date leading-none">Date: ${dateInpDom.value}</div>
                                <div class="cargo-number leading-none">Cargo No.: ${cargoNo}</div>
                                <input type="hidden" name="cargo_no" value="${cargoNo}" />
                            </div>
                            <div class="center my-auto">
                                <div class="cargo-name capitalize font-semibold text-md">Cargo Name: ${cargoNameInpDom.value}</div>
                            </div>
                            <div class="right my-auto pr-3 text-sm text-gray-600 space-y-1.5">
                                <div class="preview-copy leading-none">Cargo List Copy: Cargo</div>
                                <div class="preview-doc leading-none">Document: Cargo List</div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div id="preview-body" class="preview-body w-[95%] grow mx-auto">
                            <div class="preview-table w-full">
                                <div class="table w-full border border-gray-600 rounded-lg pb-2.5 overflow-hidden">
                                    <div class="thead w-full">
                                        <div class="tr flex justify-between w-full px-4 py-1.5 bg-[var(--primary-color)] text-white">
                                            <div class="th text-sm font-medium w-[7%]">S.No</div>
                                            <div class="th text-sm font-medium w-1/6">Date</div>
                                            <div class="th text-sm font-medium w-1/6">Invoice No.</div>
                                            <div class="th text-sm font-medium w-1/6">Cotton</div>
                                            <div class="th text-sm font-medium grow">Customer</div>
                                            <div class="th text-sm font-medium w-1/6">City</div>
                                        </div>
                                    </div>
                                    <div id="tbody" class="tbody w-full">
                                        ${selectedInvoicesArray.map((invoice, index) => {
                                            const hrClass = index === 0 ? "mb-2.5" : "my-2.5";
                                            return `
                                                <div>
                                                    <hr class="w-full ${hrClass} border-gray-600">
                                                    <div class="tr flex justify-between w-full px-4">
                                                        <div class="td text-sm font-semibold w-[7%]">${index + 1}.</div>
                                                        <div class="td text-sm font-semibold w-1/6">${invoice.date}</div>
                                                        <div class="td text-sm font-semibold w-1/6">${invoice.invoice_no}</div>
                                                        <div class="td text-sm font-semibold w-1/6">${invoice.cotton_count}</div>
                                                        <div class="td text-sm font-semibold grow">${invoice.customer.customer_name}</div>
                                                        <div class="td text-sm font-semibold w-1/6">${invoice.customer.city.title}</div>
                                                    </div>
                                                </div>
                                            `;
                                        }).join('')}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="w-full my-3 border-gray-600">
                        <div class="tfooter flex w-full text-sm px-4 justify-between mb-4 text-gray-600">
                            <P class="leading-none">Powered by SparkPair</P>
                            <p class="leading-none text-sm">&copy; 2025 SparkPair | +92 316 5825495</p>
                        </div>
                    </div>
                `;
            } else {
                previewDom.innerHTML = `
                    <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1>
                `;
            }
        }

        function selectThisInvoice(invoiceElem) {
            let checkbox = invoiceElem.querySelector("input[type='checkbox']")
            checkbox.checked = !checkbox.checked;

            toggleInvoice(invoiceElem, checkbox);
        }

        function toggleInvoice(invoiceElem, checkbox) {
            if (checkbox.checked) {
                selectInvoice(invoiceElem);
            } else {
                deselectInvoice(invoiceElem);
            }
        }

        function selectInvoice(invoiceElem) {
            const invoiceData = JSON.parse(invoiceElem.dataset.json).data;
            const index = selectedInvoicesArray.findIndex(invoice => invoice.id === invoiceData.id);
            if (index == -1) {
                selectedInvoicesArray.push(invoiceData);
                totalCottonCount += invoiceData.cotton_count;
            }
            renderList()
        }

        function deselectInvoice(invoiceElem) {
            const invoiceData = JSON.parse(invoiceElem.dataset.json).data;

            const index = selectedInvoicesArray.findIndex(invoice => invoice.id === invoiceData.id);
            if (index > -1) {
                selectedInvoicesArray.splice(index, 1);
                totalCottonCount -= invoiceData.cotton_count;

                // selectAllCheckbox.checked = false;
            }
            renderList()
        }

        function deselectAllInvoices() {
            document.querySelectorAll(".invoice-card input[type='checkbox']").forEach(checkbox => {
                checkbox.checked = false;
            });

            selectedInvoicesArray = [];
            totalCottonCount = 0;
            selectAllCheckbox.checked = false;
        }

        function validateForNextStep() {
            generateCargoListPreview()
            return true;
        }

        function setCottonCount(invoiceId, cottonCount) {
            const invoice = selectedInvoicesArray.find(invoice => invoice.id === invoiceId);
            if (invoice) {
                invoice.cottonCount = cottonCount;
            }

            updateInputInvoicesArray();
        }

        function setBiltyNo(invoiceId, biltyNo) {
            const invoice = selectedInvoicesArray.find(invoice => invoice.id === invoiceId);
            if (invoice) {
                invoice.biltyNo = biltyNo;
            }

            updateInputInvoicesArray();
        }

        function setCargoName(invoiceId, cargoName) {
            const invoice = selectedInvoicesArray.find(invoice => invoice.id === invoiceId);
            if (invoice) {
                invoice.cargoName = cargoName;
            }

            updateInputInvoicesArray();
        }
    </script>
@endsection
