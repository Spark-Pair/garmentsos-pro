(function () {
    let selectedInvoicesArray = [];

    let lastCargo;
    let companyData;
    let invoices = [];
    let isEdit = false;
    let cargo = null;
    let cardData = [];
    let visibleCardData = [];
    let invoicesLoading = false;

    const generateListBtn = document.getElementById('generateListBtn');
    const dateInput = document.getElementById('date');
    const cargoListDOM = document.getElementById('cargo-list');
    const finalTotalCartonsDOM = document.getElementById('finalTotalCartons');

    let totalCartonCount = 0;

    window.trackStateOfgenerateBtn = function trackStateOfgenerateBtn(elem) {
        if (!generateListBtn) return;
        if (elem.value != '') {
            generateListBtn.disabled = false;
            loadInvoicesForDate(elem.value);
        } else {
            generateListBtn.disabled = true;
            invoices = [];
            selectedInvoicesArray = [];
            renderList();
        }
    };

    if (generateListBtn) {
        generateListBtn.disabled = true;
        generateListBtn.addEventListener('click', () => {
            generateModal();
        });
    }

    function invoiceSearchText(item) {
        return [
            item.name,
            item.data?.invoice_no,
            item.data?.shipment_no,
            item.data?.customer?.customer_name,
            item.data?.customer?.city?.title,
        ].filter(Boolean).join(' ').toLowerCase();
    }

    function buildInvoiceCards() {
        const data = invoices || [];
        cardData = [];

        if (data.length > 0) {
            cardData.push(
                ...data.map(item => {
                    return {
                        id: item.id,
                        name: item.invoice_no,
                        details: {
                            'Shipment No.': item.shipment_no || '-',
                            Customer: item.customer?.customer_name || '-',
                            City: item.customer?.city?.title || '-',
                        },
                        data: item,
                        checkbox: true,
                        checked: selectedInvoicesArray.some(selected => selected.id === item.id),
                        onclick: 'selectThisInvoice(this)',
                    };
                })
            );
        }
    }

    async function loadInvoicesForDate(date) {
        if (!date || invoicesLoading) return;

        invoicesLoading = true;
        if (generateListBtn) {
            generateListBtn.disabled = true;
            generateListBtn.textContent = 'Loading...';
        }

        try {
            const url = new URL(window.__cargosGenerate.invoicesUrl || window.location.href, window.location.origin);
            url.searchParams.set('date', date);
            if (isEdit && cargo?.id) {
                url.searchParams.set('cargo_id', cargo.id);
            }

            const response = await fetch(url.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            invoices = Array.isArray(payload.invoices) ? payload.invoices : [];
            const allowedIds = new Set(invoices.map(invoice => invoice.id));
            selectedInvoicesArray = selectedInvoicesArray.filter(invoice => allowedIds.has(invoice.id));
            renderList();
        } catch (error) {
            console.error('Error loading cargo invoices:', error);
            invoices = [];
            selectedInvoicesArray = [];
            renderList();
            if (window.showToast) {
                window.showToast('Invoices could not be loaded for this date.', 'error');
            } else if (window.appAlert) {
                window.appAlert('Invoices could not be loaded for this date.');
            }
        } finally {
            invoicesLoading = false;
            if (generateListBtn) {
                generateListBtn.disabled = !date;
                generateListBtn.textContent = 'Select Invoices';
            }
        }
    }

    window.basicSearchCargoInvoices = function basicSearchCargoInvoices(searchValue) {
        const needle = String(searchValue || '').trim().toLowerCase();
        visibleCardData = needle
            ? cardData.filter(item => invoiceSearchText(item).includes(needle))
            : cardData;

        renderCardsInModal({
            id: 'modalForm',
            cards: {
                name: 'Invoices',
                count: 3,
                data: visibleCardData,
            },
        });
        updateSelectAllInvoicesButton();
    };

    function updateSelectAllInvoicesButton() {
        const button = document.getElementById('selectAllCargoInvoicesBtn');
        if (!button) return;

        const visibleIds = visibleCardData.map(item => item.id);
        const selectedVisibleCount = visibleIds.filter(id =>
            selectedInvoicesArray.some(selected => selected.id === id)
        ).length;

        button.disabled = visibleCardData.length === 0;
        button.innerHTML = selectedVisibleCount === visibleCardData.length && visibleCardData.length > 0
            ? '<i class="fas fa-square-minus text-xs"></i> Clear Visible'
            : '<i class="fas fa-check-double text-xs"></i> Select All';
    }

    function enhanceInvoiceModalControls() {
        const searchBox = document.querySelector('#modalForm-wrapper #basicSearch .relative');
        if (!searchBox || document.getElementById('selectAllCargoInvoicesBtn')) return;

        const button = document.createElement('button');
        button.id = 'selectAllCargoInvoicesBtn';
        button.type = 'button';
        button.className = 'bg-[var(--secondary-bg-color)] border border-gray-600 px-4 rounded-lg hover:bg-[var(--h-bg-color)] transition-all duration-300 ease-in-out cursor-pointer text-nowrap disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2';
        button.onclick = toggleAllVisibleInvoices;
        searchBox.appendChild(button);
        updateSelectAllInvoicesButton();
    }

    window.generateModal = function generateModal() {
        if (invoicesLoading) return;
        if (!dateInput?.value) {
            if (window.showToast) {
                window.showToast('Please select cargo date first.', 'error');
            } else if (window.appAlert) {
                window.appAlert('Please select cargo date first.');
            }
            return;
        }

        buildInvoiceCards();
        visibleCardData = cardData;

        const modalData = {
            id: 'modalForm',
            class: 'h-[80%] w-full',
            basicSearch: true,
            onBasicSearch: 'basicSearchCargoInvoices(this.value)',
            cards: { name: 'Invoices', count: 3, data: visibleCardData },
        };

        createModal(modalData);
        enhanceInvoiceModalControls();
    };

    function deselectInvoiceAtIndex(index) {
        if (index !== -1) {
            selectedInvoicesArray.splice(index, 1);
        }
    }

    window.deselectThisInvoice = function deselectThisInvoice(index) {
        deselectInvoiceAtIndex(index);
        renderList();
    };

    function renderList() {
        if (!cargoListDOM || !finalTotalCartonsDOM) return;
        totalCartonCount = selectedInvoicesArray.reduce((sum, invoice) => sum + Number(invoice.carton_count || 0), 0);
        if (selectedInvoicesArray.length > 0) {
            let clutter = '';
            selectedInvoicesArray.forEach((selectedInvoice, index) => {
                clutter += `
                        <div class="flex justify-between items-center border-t border-gray-600 py-3 px-4">
                            <div class="w-[10%]">${index + 1}</div>
                            <div class="w-1/6">${formatDate(selectedInvoice.date)}</div>
                            <div class="w-[14%]">${selectedInvoice.invoice_no || '-'}</div>
                            <div class="w-[14%]">${selectedInvoice.shipment_no || '-'}</div>
                            <div class="w-1/6">${selectedInvoice.carton_count ?? '-'}</div>
                            <div class="grow">${selectedInvoice.customer?.customer_name || '-'}</div>
                            <div class="w-[10%]">${selectedInvoice.customer?.city?.title || '-'}</div>
                            <div class="w-[10%] text-center">
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
                '<div class="text-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4">No Invoices Yet</div>';
        }
        finalTotalCartonsDOM.textContent = totalCartonCount;
        updateInputinvoicesArray();
    }

    function updateInputinvoicesArray() {
        const inputinvoices = document.getElementById('invoices');
        const finalArticlesArray = selectedInvoicesArray.map(invoice => {
            return {
                id: invoice.id,
                description: invoice.description,
                shipment_quantity: invoice.shipmentQuantity,
            };
        });
        if (inputinvoices) {
            inputinvoices.value = JSON.stringify(finalArticlesArray);
        }
    }

    const previewContainer = document.getElementById('preview-container');

    window.generateCargoListPreview = function generateCargoListPreview() {
        const cargoNo = isEdit && cargo?.cargo_no
            ? cargo.cargo_no
            : 'Assigned after save';
        const cargoNameInpDom = document.getElementById('cargo_name');
        const dateInpDom = document.getElementById('date');

        if (!previewContainer) return;
        if (selectedInvoicesArray.length > 0) {
            previewContainer.className = 'h-auto mx-auto relative flex flex-col';
            previewContainer.innerHTML = window.DocumentPreview.render({
                preview: {
                    type: 'cargo_list',
                    size: 'A4',
                    document: 'Cargo List',
                    data: {
                        cargo_no: cargoNo,
                        cargo_name: cargoNameInpDom?.value || '',
                        date: dateInpDom?.value || '',
                        invoices: selectedInvoicesArray,
                        branch_branding: companyData,
                    },
                },
            }, {
                companyData,
                companyLogoBase: window.__cargosGenerate?.companyLogoBase,
            });
        } else {
            previewContainer.className = 'w-[210mm] h-[297mm] mx-auto overflow-hidden relative';
            previewContainer.innerHTML =
                '<div id="preview" class="preview cargo-list-preview w-[210mm] h-[297mm] gos-a4-document cargo-list-a4-document overflow-hidden flex flex-col"><h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview avalaible.</h1></div>';
        }
    };

    window.selectThisInvoice = function selectThisInvoice(invoiceElem) {
        const checkbox = invoiceElem.querySelector("input[type='checkbox']");
        checkbox.checked = !checkbox.checked;
        toggleInvoice(invoiceElem, checkbox);
    };

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
        }
        renderList();
        buildInvoiceCards();
        visibleCardData = visibleCardData.map(item => ({
            ...item,
            checked: selectedInvoicesArray.some(selected => selected.id === item.id),
        }));
        updateSelectAllInvoicesButton();
    }

    function deselectInvoice(invoiceElem) {
        const invoiceData = JSON.parse(invoiceElem.dataset.json).data;

        const index = selectedInvoicesArray.findIndex(invoice => invoice.id === invoiceData.id);
        if (index > -1) {
            selectedInvoicesArray.splice(index, 1);
        }
        renderList();
        buildInvoiceCards();
        visibleCardData = visibleCardData.map(item => ({
            ...item,
            checked: selectedInvoicesArray.some(selected => selected.id === item.id),
        }));
        updateSelectAllInvoicesButton();
    }

    window.toggleAllVisibleInvoices = function toggleAllVisibleInvoices() {
        const allVisibleSelected = visibleCardData.length > 0 && visibleCardData.every(item =>
            selectedInvoicesArray.some(selected => selected.id === item.id)
        );

        if (allVisibleSelected) {
            const visibleIds = new Set(visibleCardData.map(item => item.id));
            selectedInvoicesArray = selectedInvoicesArray.filter(invoice => !visibleIds.has(invoice.id));
        } else {
            visibleCardData.forEach(item => {
                if (!selectedInvoicesArray.some(selected => selected.id === item.id)) {
                    selectedInvoicesArray.push(item.data);
                }
            });
        }

        buildInvoiceCards();
        visibleCardData = visibleCardData.map(item => ({
            ...item,
            checked: selectedInvoicesArray.some(selected => selected.id === item.id),
        }));
        renderCardsInModal({
            id: 'modalForm',
            cards: {
                name: 'Invoices',
                count: 3,
                data: visibleCardData,
            },
        });
        renderList();
        updateSelectAllInvoicesButton();
    };

    window.validateForNextStep = function validateForNextStep() {
        generateCargoListPreview();
        return true;
    };

    function addListenerToPrintAndSaveBtn() {
        const printAndSaveBtn = document.getElementById('printAndSaveBtn');
        if (!printAndSaveBtn) return;

        printAndSaveBtn.addEventListener('click', e => {
            e.preventDefault();
            closeAllDropdowns();

            if (typeof validateForNextStep === 'function' && validateForNextStep() === false) {
                return;
            }

            const form = document.getElementById('form');
            if (!form) return;
            let printAfterSave = form.querySelector('input[name="printAfterSave"]');
            if (!printAfterSave) {
                printAfterSave = document.createElement('input');
                printAfterSave.type = 'hidden';
                printAfterSave.name = 'printAfterSave';
                form.appendChild(printAfterSave);
            }
            printAfterSave.value = '1';
            form.requestSubmit();
        });
    }

    function initCargosGenerate(data) {
        isEdit = Boolean(data?.isEdit);
        cargo = data?.cargo || null;
        lastCargo = data?.lastCargo || null;
        companyData = data?.companyData || null;
        invoices = data?.invoices || [];
        selectedInvoicesArray = Array.isArray(data?.selectedInvoices) ? [...data.selectedInvoices] : [];
        if (generateListBtn) {
            generateListBtn.disabled = !dateInput?.value;
        }
        renderList();
        addListenerToPrintAndSaveBtn();
    }

    window.initCargosGenerate = initCargosGenerate;

    function boot() {
        if (window.__cargosGenerate) {
            initCargosGenerate(window.__cargosGenerate);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
