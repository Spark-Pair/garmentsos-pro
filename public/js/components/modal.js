function createModal(data, animate = 'animate') {
    const statusColor = {
        active: ['[var(--bg-success)]', '[var(--h-bg-success)]', '[var(--border-success)]'],
        transparent: ['transparent', 'transparent', 'transparent'],
        no_Image: ['[var(--bg-warning)]', '[var(--h-bg-warning)]', '[var(--border-warning)]'],
        inactive: ['[var(--bg-error)]', '[var(--h-bg-error)]', '[var(--border-error)]'],
    };

    const contextMenu = document.getElementById('context-menu');
    if (contextMenu) {
        contextMenu.classList.add('fade-out');
        contextMenu.addEventListener('animationend', () => {
            contextMenu.remove()
        }, { once: true })
    };

    let modalWrapper = ''
    modalWrapper = document.createElement('div');
    modalWrapper.id = `${data.id}-wrapper`;
    modalWrapper.className = `fixed inset-0 z-[999] text-sm flex items-center justify-center bg-[var(--overlay-color)] ${animate == 'animate' ? 'fade-in' : ''} `;

    let clutter = `
        <form id="${data.id}" method="${data.method ?? 'POST'}" action="${data.action}" enctype="multipart/form-data" class="w-full h-full flex flex-col space-y-4 relative items-center justify-center ${animate == 'animate' ? 'scale-in' : ''} ${data.class}">
            <input type="hidden" name="_token" value="${document.querySelector('meta[name=\'csrf-token\']')?.content}">
            <div class="${data.class} ${data.preview ? `bg-white text-black ${data.preview.size == "A5" ? "w-[148mm]" : "max-w-4xl"} h-[35rem] py-0` : 'bg-[var(--secondary-bg-color)]'} ${data.cards ? 'h-[40rem] max-w-6xl' : 'max-w-2xl'} rounded-2xl shadow-lg w-full p-6 flex relative">
                <div id="modal-close" onclick="closeModal('${data.id}')"
                    class="absolute top-0 -right-4 translate-x-full bg-[var(--secondary-bg-color)] rounded-2xl shadow-lg w-auto p-3 text-sm transition-all duration-300 ease-in-out hover:scale-[0.95] cursor-pointer">
                    <button type="button"
                        class="z-10 text-gray-400 hover:text-gray-600 hover:scale-[0.95] transition-all duration-300 ease-in-out cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                            class="w-6 h-6" style="display: inline">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                ${data.info ?
                    `<div class="${data.id}Info absolute z-10 ${data.calcBottom?.length > 0 ? 'bottom-14' : 'bottom-4'}  left-4 border border-[var(--glass-border-color)]/10 group bg-[var(--glass-border-color)]/5 backdrop-blur-md rounded-xl cursor-pointer flex items-center justify-end p-1 overflow-hidden h-auto pr-3 transition-all duration-300 ease-in-out shadow-md pointer-events-auto" >
                        <div class="flex items-center justify-center bg-[var(--bg-color)] border border-[var(--glass-border-color)]/20 rounded-lg p-2" >
                            <div class="transition-all duration-300 ease-in-out size-2.5 relative" >
                                <i class="fas fa-info text-xs absolute top-1/2 left-1/2 -translate-1/2" ></i>
                            </div>
                        </div>
                        <span class="main-text inline-block overflow-hidden whitespace-nowrap transition-all duration-300 ease-in-out opacity-100 max-w-[300px] ml-2" >
                            ${data.info}
                        </span>
                    </div>` : ''
                }

                <div class="flex ${data.flex_col ? 'flex-col' : ''} w-full ${data.preview ? 'py-10' : ''}">
                    <div class="w-full h-full relative ${!data.table?.scrollable ? 'overflow-y-auto my-scrollbar-2' : ''}">
    `;

    if (data.user?.status || data.status) {
        const [bgColor, hoverBgColor, textColor] = statusColor[data.user?.status ?? data.status] || statusColor.inactive;
        if (data.image) {
            clutter += `
                <div id="active_inactive_dot_modal"
                    class="absolute top-3 left-3 w-[0.7rem] h-[0.7rem] bg-${textColor} rounded-full">
                </div>
            `;
        } else {
            clutter += `
                <div id="active_inactive_dot_modal"
                    class="absolute top-3 right-3 w-[0.7rem] h-[0.7rem] bg-${textColor} rounded-full">
                </div>
            `;
        }
    }

    clutter += `
        <div class="flex ${data.flex_col ? 'flex-col' : ''} items-start relative ${(data.class || '').includes('h-') ? 'h-full' : 'h-[15rem]'}">
    `;

    if (data.image) {
        clutter += `
                <div class="${!data.profile ? 'rounded-lg' : 'rounded-[41.5%]'} ${data.image && data.image == '/images/no_image_icon.png' ? 'scale-75' : ''} h-full aspect-square overflow-hidden">
                    <img id="imageInModal" src="/storage/uploads/images/${data.image}" alt=""
                        class="w-full h-full object-cover aspect-square">
                </div>
        `;
    }

    let detailsHTML = '';
    if (data.details && typeof data.details === 'object') {
        detailsHTML = Object.entries(data.details).map(([label, value]) => {
            // If it's an 'hr' entry (you can use any key like 'hr' or '--hr--')
            if (label === 'hr') {
                return `<hr class="w-full my-3 border-gray-600">`;
            }

            return `
                <p class="text-[var(--secondary-text)] mb-1 tracking-wide text-sm capitalize">
                    <strong>${label}:</strong> <span style="opacity: 0.9">${value}</span>
                </p>
            `;
        }).join('');
    }

    if (data.name) {
        clutter += `
            <div id="modelInner" class="flex-1 flex flex-col ${data.image ? 'ml-8' : ''} h-full w-full ${!data.table?.scrollable ? 'overflow-y-auto my-scrollbar-2' : ''}">
                <div class="flex justify-between">
                    <h5 id="name" class="text-2xl my-1 text-[var(--text-color)] capitalize font-semibold">${data.name}</h5>
                    ${data.searchFilter ? renderSearchFilter() : ''}
                </div>
                ${detailsHTML}
        `;
    }

    function renderSearchFilter() {
        return `
            <div id="search-form" class="search-box shrink-0">
                <!-- Search Input -->
                <div class="search-input">
                    <button id="filter-btn" type="button" onclick="openDropDown(event, this)"
                        class="dropdown-trigger bg-[var(--primary-color)] px-3 py-2.5 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out cursor-pointer flex gap-2 items-center font-semibold">
                        <i class="text-xs fa-solid fa-filter"></i> Search & Filter
                    </button>
                    <div class="dropdownMenu flex flex-col text-sm fixed top-2 bottom-2 right-2 border border-gray-600 w-sm bg-[var(--h-secondary-bg-color)] text-[var(--text-color)] shadow-xl rounded-2xl transition-all duration-300 ease-in-out z-[100] p-4 opacity-0 hidden">
                        <div class="header flex justify-between items-center p-1">
                            <h6 class="text-2xl text-[var(--text-color)] font-semibold leading-none ml-1">Search & Filter</h6>
                            <div onclick="closeAllDropdowns()" class="text-sm transition-all duration-300 ease-in-out hover:scale-[0.95] cursor-pointer">
                                <button type="button" class="z-10 text-gray-400 hover:text-gray-600 hover:scale-[0.95] transition-all duration-300 ease-in-out cursor-pointer">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6" style="display: inline">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <hr class="border-gray-600 my-4 w-full">
                        <div class="grow overflow-y-auto my-scrollbar-2 p-1">
                            <div id="searchFilterBody" class="grid grid-cols-1 gap-4">
                                ${data.searchFilter.fieldsHtml}
                            </div>
                        </div>
                        <hr class="border-gray-600 my-4 w-full">
                        <div class="flex gap-4 p-1">
                            <button type="button" onclick="closeAllDropdowns()"
                                class="flex-1 px-4 py-2 bg-[var(--secondary-bg-color)] border border-gray-600 text-[var(--secondary-text)] rounded-lg hover:bg-[var(--h-bg-color)] transition-all duration-300 ease-in-out cursor-pointer hover:scale-[0.95]">
                                Cancel
                            </button>
                            <button type="button" onclick="clearAllSearchFields()"
                                class="flex-1 px-4 py-2 bg-[var(--bg-error)] border border-[var(--bg-error)] text-[var(--text-error)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-error)] transition-all duration-300 ease-in-out cursor-pointer hover:scale-[0.95]">
                                Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    if (data.fields) {
        clutter += `
            <hr class="w-full my-3 border-gray-600">
            <div class="grid grid-cols-${data.fieldsGridCount} w-full gap-3 p-1">
        `;
        data.fields.forEach(field => {
            if (field.category == 'input') {
                if (field.type != 'hidden') {
                    let buttonHTML = '';

                    if (field.btnId) {
                        buttonHTML = `
                            <button onclick="${field.onclick ?? ''}" id="${field.btnId ?? ''}" type="button" class="bg-[var(--primary-color)] px-4 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out cursor-pointer text-lg font-bold disabled:opacity-50 disabled:cursor-not-allowed" disabled>+</button>
                        `;
                    }

                    clutter += `
                        <div class="${field.grow ? 'grow' : ''} ${field.full ? 'col-span-full' : ''}">
                            <div class="form-group relative ${field.hidden ? 'hidden' : ''}">
                                <label for="${field.name ?? ''}" class="block font-medium text-[var(--secondary-text)] mb-2 ${!field.label ? 'hidden' : ''}">${field.label}</label>

                                <div class="relative flex gap-3">
                                    <input onkeydown="${field.enterToSubmitListener ? 'enterToSubmit(event)' : ''}" id="${field.id ?? ''}" type="${field.type ?? 'text'}" name="${field.name ?? ''}" value="${field.value ?? ''}" min="${field.min}" max="${field.max}" placeholder="${field.placeholder ?? ''}" data-validate="${field.data_validate ?? ''}" ${field.required ? 'required' : ''} ${field.disabled ? 'disabled' : ''} ${field.readonly ? 'readonly' : ''}
                                    ${field.data_validate ? `oninput="validateInput(this); ${field.oninput ?? ''}"` : (field.oninput ? `oninput="${field.oninput}"` : '')}
                                    onchange="${field.onchange ?? ''}" class="w-full rounded-lg bg-[var(--h-bg-color)] border-gray-600 text-[var(--text-color)] px-3 ${field.type == 'date' ? 'py-[7px]' : 'py-2'} border focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300 ease-in-out disabled:bg-transparent placeholder:capitalize">
                                    ${buttonHTML}
                                </div>
                            </div>

                            <div id="${field.name}-error" class="text-[var(--border-error)] text-xs mt-1.5 hidden transition-all duration-300 ease-in-out leading-none"></div>
                        </div>
                    `;

                    if (field.focus) {
                        setTimeout(() => {
                            const input = document.getElementById(`${field.id}`);
                            if (input) input.focus();
                        }, 0);
                    }
                } else {
                    clutter += `
                        <input id="${field.id ?? ''}" type="hidden" name="${field.name ?? ''}" value="${field.value ?? ''}">
                    `;
                }
            } else if (field.category == 'select') {
                let buttonHTML = '';
                let optionsHTML = '<option value="">-- No options available --</option>';

                if (field.btnId) {
                    buttonHTML = `
                        <button onclick="${field.onclick ?? ''}" id="${field.btnId ?? ''}" type="button" class="bg-[var(--primary-color)] px-4 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out cursor-pointer text-lg font-bold disabled:opacity-50 disabled:cursor-not-allowed" disabled>+</button>
                    `;
                }

                if (field.options && field.options.length > 0) {
                    optionsHTML = `<option value="">-- Select ${field.label} --</option>`;

                    const rawOptions = field.options[0];
                    const optionsArray = Object.entries(rawOptions).map(([key, obj]) => {
                        return {
                            id: key,
                            text: obj.text,
                            data_option: obj.data_option || '{}'
                        };
                    });

                    optionsArray.forEach(option => {
                        optionsHTML += `
                            <option value="${option.id}" data-option='${JSON.stringify(option.data_option)}'>${option.text}</option>
                        `;
                    });
                }

                clutter += `
                    <div class="grow form-group">
                        <label for="${field.name ?? ''}" class="block font-medium text-[var(--secondary-text)] mb-2">${field.label} *</label>

                        <div class="selectParent relative flex gap-3">
                            <select id="${field.id ?? ''}" name="${field.name ?? ''}" onchange="${field.onchange}" value="${field.value || ''}" class="w-full rounded-lg bg-[var(--h-bg-color)] border-gray-600 text-[var(--text-color)] px-3 py-2 border appearance-none focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300 ease-in-out disabled:bg-transparent" ${field.required ? 'required' : ''} ${field.disabled ? 'disabled' : ''} ${field.readonly ? 'readonly' : ''}>
                                ${optionsHTML}
                            </select>
                            ${buttonHTML}
                        </div>
                    </div>
                `;
            } else if (field.category == 'hr') {
                clutter += `
                    <div class="col-span-full">
                        <hr class="w-full border-gray-600">
                    </div>
                `;
            } else if (field.category == 'explicitHtml') {
                clutter += `
                    <div class="${field.grow ? 'grow' : ''} ${field.full ? 'col-span-full' : ''}">
                        <div class="">
                            ${field.html}
                        </div>
                    </div>
                `;
            }
        });

        clutter += `
            </div>
        `;
    }

    if (data.imagePicker) {
        clutter += `
            <div>
                <hr class="w-full my-3 border-gray-600">

                <div class="grid grid-cols-1 md:grid-cols-1">
                    <label for="${data.imagePicker.name}"
                        class="border-dashed border-2 border-gray-300 rounded-lg p-6 flex flex-col items-center justify-center cursor-pointer hover:border-primary transition-all duration-300 ease-in-out relative">
                        <input id="${data.imagePicker.id}" type="file" name="${data.imagePicker.name}" accept="image/*"
                            class="image_upload opacity-0 absolute inset-0 cursor-pointer"
                            onchange="previewImage(event)" />
                        <div id="image_preview_${data.imagePicker.id}" class="flex flex-col items-center max-w-[50%]">
                            <img src="/storage/uploads/images/${data.imagePicker.placeholder}" alt="Upload Icon"
                                class="placeholder_icon w-auto h-full mb-2 rounded-md" id="placeholder_icon_${data.imagePicker.id}" />
                            <p id="upload_text_${data.imagePicker.id}" class="upload_text text-md text-gray-500">${data.imagePicker.uploadText}</p>
                        </div>
                    </label>
                </div>
            </div>
        `;
    }

    if (data.cards) {
        clutter += `
            <div class="flex-1 flex flex-col ${data.image ? 'ml-8' : ''} h-auto w-full overflow-y-auto my-scrollbar-2">
                <div class="flex justify-between">
                    <h5 id="name" class="text-2xl text-[var(--text-color)] capitalize font-semibold leading-[1.5]">${data.cards.name}</h5>
                    ${data.basicSearch ? `<div class="form-group relative" id="basicSearch">
                        <div class="relative flex gap-2 w-sm pt-0.5">
                            <input
                                type="text"
                                placeholder="🔍 Search..."
                                autocomplete="off"
                                class="w-full rounded-lg bg-[var(--h-bg-color)] border-gray-600 text-[var(--text-color)] px-3 py-2 border focus:ring-2 focus:ring-primary focus:border-transparent transition-all duration-300 ease-in-out disabled:bg-transparent disabled:opacity-70 placeholder:capitalize"
                                oninput="${data.onBasicSearch}"
                            />

                            <button
                                type="button"
                                class="bg-[var(--primary-color)] px-4 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out cursor-pointer text-nowrap disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <i class="text-xs fa-solid fa-magnifying-glass"></i>
                            </button>
                        </div>

                        <div
                            id="search_box-error"
                            class="text-[var(--border-error)] text-xs mt-1 hidden transition-all duration-300 ease-in-out"
                        ></div>
                    </div>` : ''}
                </div>
                <hr class="w-full my-3 border-gray-600">
                <div class="${data.id}CardsContainer grid grid-cols-${data.cards.count} w-full gap-3 text-sm">
                    ${returnCardsInModal(data)}
                </div>
            </div>
        `
    }

    if (data.table) {
        let headerHTML = '';

        data.table.headers.forEach(header => {
            headerHTML += `<div class="${header.class}">${header.label}</div>`;
        });

        let bodyHTML = '';

        clutter += `
            <hr class="w-full my-3 border-gray-600">

            <!-- TABLE WRAPPER -->
            <div class="w-full flex-1 flex flex-col text-left text-sm relative overflow-hidden">

                <!-- Header -->
                <div id="table-head"
                    class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-3">
                    ${headerHTML}
                </div>

                <!-- No Items Error -->
                <p id="noItemsError"
                    style="display: none"
                    class="text-sm text-[var(--border-error)] mt-2 mb-1">
                    No items found
                </p>

                <!-- BODY (auto height takes remaining space) -->
                <div id="table-body"
                    class="search_container flex-1 overflow-y-auto my-scrollbar-2">
                    ${bodyHTML}
                </div>

            </div>
        `;
    }

    if (data.calcBottom && data.calcBottom.length > 0) {
        let calcBottomClass = '';
        let fieldsHTML = '';
        const childCount = data.calcBottom.length;

        if (childCount === 1 || childCount === 3) {
            calcBottomClass = 'flex';
        } else if (childCount === 2 || childCount === 4) {
            calcBottomClass = 'grid grid-cols-2';
        } else if (childCount === 6) {
            calcBottomClass = 'grid grid-cols-3';
        }

        data.calcBottom.forEach(field => {
            fieldsHTML += `
                <div class="final flex justify-between items-center bg-[var(--h-bg-color)] border border-gray-600 rounded-lg py-2 px-4 w-full ${field.disabled ? 'cursor-not-allowed' : ''}">
                    <label for="${field.name}" class="text-nowrap grow">${field.label}</label>
                    <input type="text" required name="${field.name}" id="${field.name}" max="${field.max}" value="${field.value}" ${field.disabled ? 'disabled' : ''} class="text-right bg-transparent outline-none border-none w-[50%]" />
                </div>
            `;
        });

        clutter += `
            <div class="w-full">
                <hr class="w-full my-3 border-gray-600">
                <div id="calc-bottom" class="${calcBottomClass} w-full gap-3 text-sm">
                    ${fieldsHTML}
                </div>
            </div>
        `;
    }

    if (data.chips) {
        clutter += `
            <hr class="w-full my-3 border-gray-600">
            <div id="chipsContainer" class="w-full flex flex-wrap gap-2 overflow-y-auto my-scrollbar-2 text-[var(--text-color)]">
        `;

        let removeBtn = `
            <button class="delete cursor-pointer ${data.chips.length <= 1 ? 'hidden' : ''} transition-all 0.3s ease-in-out" type="button">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                class="size-3 stroke-gray-400">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        `;

        data.chips.forEach(chip => {
           clutter += `
                <div data-id="${chip.id}" class="chip border border-gray-600 text-xs rounded-xl py-2 px-4 inline-flex items-center gap-2 transition-all 0.3s ease-in-out">
                    <div class="text tracking-wide">${chip.title}</div>
                    ${data.editableChips ? removeBtn : ''}
                </div>
           `;
        });

        clutter += `
            </div>
        `;
    }

    if (data.name) {
        clutter += '</div>';
    }

    function chunkArray(array, size) {
        const chunks = [];
        for (let i = 0; i < array.length; i += size) {
            chunks.push(array.slice(i, i + size));
        }
        return chunks;
    }

    if (data.preview) {
        let previewData = data.preview.data;
        let cottonCount = previewData.cotton_count || 0;
        let totalAmount = 0;
        let totalQuantity = 0;
        let discount = previewData.discount || previewData.shipment?.discount || previewData.order?.discount;
        let previousBalance = previewData.previous_balance || 0;
        let netAmount = previewData.netAmount || previewData.shipment?.netAmount;
        let currentBalance = previewData.current_balance;

        let invoiceTableHeader = "";
        let invoiceTableBody = "";
        let invoiceBottom = "";

        const articlePages = (previewData.articles || previewData.order?.articles || previewData.shipment?.articles)
        ? chunkArray((previewData.articles || previewData.order?.articles || previewData.shipment?.articles), 21)
        : [];


        if (data.preview.type == "voucher") {
            invoiceTableHeader = `
                <div class="th text-sm font-medium w-[7%]">S.No</div>
                <div class="th text-sm font-medium w-[11%]">Method</div>
                ${previewData.supplier ? '<div class="th text-sm font-medium w-1/5">Customer</div>' : ''}
                <div class="th text-sm font-medium w-1/4">Account</div>
                <div class="th text-sm font-medium w-[14%]">Date</div>
                <div class="th text-sm font-medium w-[14%]">Reff. No.</div>
                <div class="th text-sm font-medium w-[10%]">Amount</div>
            `;

            invoiceTableBody = `
                ${previewData.payments.map((payment, index) => {
                    console.log(payment);

                    const hrClass = index === 0 ? "mb-2.5" : "my-2.5";
                    return `
                    <div>
                        <hr class="w-full ${hrClass} border-gray-600">
                        <div class="tr flex justify-between w-full px-4">
                            <div class="td text-sm font-semibold w-[7%]">${index + 1}.</div>
                            <div class="td text-sm font-semibold w-[11%] capitalize">${payment.method ?? '-'}</div>
                            ${previewData.supplier ? `<div class="td text-sm font-semibold w-1/5">${payment.program?.customer.customer_name ? payment.program?.customer.customer_name : payment.cheque?.customer.customer_name ? payment.cheque?.customer.customer_name : payment.slip?.customer.customer_name ? payment.slip?.customer.customer_name : '-'}</div>` : ''}
                            ${previewData.supplier ? `<div class="td text-sm font-semibold w-1/4">${(payment.bank_account?.account_title?.split('|')[0] ?? '-') + ' | ' + (payment.bank_account?.bank.short_title ?? '-')}</div>` :
                                `<div class="td text-sm font-semibold w-1/4">${(payment.self_account?.account_title?.split('|')[0] ?? '-') + ' | ' + (payment.self_account?.bank.short_title ?? '-')}</div>
                            `}
                            <div class="td text-sm font-semibold w-[14%]">${formatDate(payment.date, true) ?? '-'}</div>
                            <div class="td text-sm font-semibold w-[14%]">${payment.cheque?.cheque_no ?? payment.cheque_no ?? payment.reff_no ?? payment.slip?.slip_no ??
                                payment.transaction_id ?? payment.reff_no ?? '-'}</div>
                            <div class="td text-sm font-semibold w-[10%]">${formatNumbersWithDigits(payment.amount, 1, 1) ?? '-'}
                            </div>
                        </div>
                    </div>
                    `;
                }).join('')}
            `;

            invoiceBottom = '';

            if (previewData.supplier) {
                invoiceBottom += `
                    <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                        <div class="text-nowrap">Previous Balance - Rs</div>
                        <div class="w-1/4 text-right grow">${formatNumbersWithDigits(previewData.previous_balance, 1, 1)}</div>
                    </div>
                `;
            }

            invoiceBottom += `
                <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                    <div class="text-nowrap">Total Payment - Rs</div>
                    <div class="w-1/4 text-right grow">${formatNumbersWithDigits(previewData.total_payment, 1, 1)}</div>
                </div>
            `;

            if (previewData.supplier) {
                invoiceBottom += `
                    <div class="total flex justify-between items-center border border-gray-600 rounded-lg py-2 px-4 w-full">
                        <div class="text-nowrap">Current Balance - Rs</div>
                        <div class="w-1/4 text-right grow">${formatNumbersWithDigits(previewData.previous_balance - previewData.total_payment, 1, 1)}</div>
                    </div>
                `;
            }

        } else if (data.preview.type == "cargo_list") {
            invoiceTableHeader = `
                <div class="th text-sm font-medium w-[7%]">S.No</div>
                <div class="th text-sm font-medium w-1/5">Date</div>
                <div class="th text-sm font-medium w-1/6">Invoice No.</div>
                <div class="th text-sm font-medium w-1/6">Cotton</div>
                <div class="th text-sm font-medium grow">Customer</div>
                <div class="th text-sm font-medium w-[12%]">City</div>
            `;

            invoiceTableBody = `
                ${previewData.invoices.map((invoice, index) => {
                    const hrClass = index === 0 ? "mb-2.5" : "my-2.5";

                    return `
                        <div>
                            <hr class="w-full ${hrClass} border-black">
                            <div class="tr flex justify-between w-full px-4">
                                <div class="td text-sm font-semibold w-[7%]">${index + 1}.</div>
                                <div class="td text-sm font-semibold w-1/5">${formatDate(invoice.date)}</div>
                                <div class="td text-sm font-semibold w-1/6">${invoice.invoice_no}</div>
                                <div class="td text-sm font-semibold w-1/6">${invoice.cotton_count}</div>
                                <div class="td text-sm font-semibold grow capitalize">${invoice.customer.customer_name}</div>
                                <div class="td text-sm font-semibold w-[12%]">${invoice.customer.city.title}</div>
                            </div>
                        </div>
                    `;
                }).join('')}
            `;
        } else if (data.preview.type == "form") {

        } else {
            const articlePages = chunkArray((previewData.articles || previewData.order?.articles || previewData.shipment?.articles), 21);

            let totalAmount = 0;
            let totalPcs = 0;
            let totalPackets = 0;

            clutter += `
                    <div id="preview-container" class="h-auto mx-auto relative flex flex-col">`

            articlePages.forEach((articlesChunk, pageIndex) => {
                invoiceTableHeader = `
                    <div class="th text-sm font-medium ">S.No</div>
                    <div class="th text-sm font-medium ">Article</div>
                    <div class="th text-sm font-medium col-span-2">Description</div>
                    ${data.preview.type == 'invoice' ? '<div class="th text-sm font-medium ">Unit</div>' : ''}
                    <div class="th text-sm font-medium ">Packets</div>
                    <div class="th text-sm font-medium ">Pcs.</div>
                    <div class="th text-sm font-medium ">Rate/Pc.</div>
                    <div class="th text-sm font-medium ">Amount</div>
                    ${data.preview.type == 'order' ? '<div class="th text-sm font-medium text-center ">Dispatch</div>' : ''}
                `;

                invoiceTableBody = `
                    ${articlesChunk.map((orderedArticle, index) => {
                        const article = orderedArticle.article;
                        const salesRate = article.sales_rate;
                        const qty =
                            orderedArticle.ordered_pcs ||
                            orderedArticle.invoice_pcs ||
                            orderedArticle.shipment_pcs;

                        const total = parseInt(salesRate) * qty;
                        const hrClass = index === 0 ? "mb-2.5" : "my-2.5";

                        totalAmount += total;
                        totalPcs += qty;
                        totalPackets += article?.pcs_per_packet ? Math.floor(qty / article.pcs_per_packet) : 0;

                        return `
                            <div>
                                <hr class="w-full ${hrClass} border-black">
                                <div class="tr grid grid-cols-${data.preview.type == 'shipment' ? '8' : '9'} justify-between w-full px-4">
                                    <div class="td text-sm font-semibold ">
                                        ${pageIndex * 21 + index + 1}.
                                    </div>
                                    <div class="td text-sm font-semibold ">
                                        ${article.article_no}
                                    </div>
                                    <div class="td text-sm font-semibold col-span-2 text-nowrap overflow-hidden mr-3 capitalize">
                                        ${orderedArticle.description}
                                    </div>
                                    ${data.preview.type == 'invoice'
                                        ? `<div class="td text-sm font-semibold ">
                                            ${article?.pcs_per_packet}
                                        </div>`
                                        : ''}
                                    <div class="td text-sm font-semibold ">
                                        ${article?.pcs_per_packet
                                            ? Math.floor(qty / article.pcs_per_packet)
                                            : 0}
                                    </div>
                                    <div class="td text-sm font-semibold ">
                                        ${qty}
                                    </div>
                                    <div class="td text-sm font-semibold ">
                                        ${formatNumbersWithDigits(salesRate, 1, 1)}
                                    </div>
                                    <div class="td text-sm font-semibold ">
                                        ${formatNumbersWithDigits(total, 1, 1)}
                                    </div>
                                    ${data.preview.type == 'order'
                                        ? `<div class="td text-sm text-center font-semibold"> ${orderedArticle.dispatched_pcs > 0 ? orderedArticle.dispatched_pcs : ''}</div>`
                                        : ''}
                                </div>
                            </div>
                        `;
                    }).join('')}
                `;

                const discountAmount = discount
                    ? (totalAmount * discount) / 100
                    : 0;

                // ✅ TOTALS SIRF LAST PREVIEW PAR
                invoiceBottom = '';
                if (pageIndex === articlePages.length - 1) {
                    invoiceBottom = `
                        <div class="total flex justify-between items-center border border-black rounded-lg py-1.5 px-4 w-full">
                            <div class="text-nowrap">Total Quantity</div>
                            <div class="w-1/4 text-right grow">
                                ${formatNumbersDigitLess(totalPcs)} | ${formatNumbersDigitLess(totalPackets)}
                            </div>
                        </div>
                        <div class="total flex justify-between items-center border border-black rounded-lg py-1.5 px-4 w-full">
                            <div class="text-nowrap">Gross Amount</div>
                            <div class="w-1/4 text-right grow">
                                ${formatNumbersWithDigits(totalAmount, 1, 1)}
                            </div>
                        </div>
                        <div class="total flex justify-between items-center border border-black rounded-lg py-1.5 px-4 w-full">
                            <div class="text-nowrap">Discount - %${discount}</div>
                            <div class="w-1/4 text-right grow">${formatNumbersWithDigits(discountAmount, 1, 1)}</div>
                        </div>
                        <div class="total flex justify-between items-center border border-black rounded-lg py-1.5 px-4 w-full">
                            <div class="text-nowrap">Net Amount</div>
                            <div class="w-1/4 text-right grow">
                                ${formatNumbersWithDigits(netAmount, 1, 1)}
                            </div>
                        </div>
                    `;
                }

                // 🔥 SAME PREVIEW HTML — DUPLICATED
                clutter += `
                    ${pageIndex > 0 ? '<div class="page-break"></div>' : ''}
                        <div id="preview" class="preview w-[208mm] h-[302mm] overflow-hidden flex flex-col">
                            <div class="flex flex-col h-full">
                                <div id="banner" class="banner w-full flex justify-between items-center px-5">
                                    <div class="left">
                                        <div class="logo">
                                            <img src="images/${companyData.logo}" alt="garmentsos-pro"
                                                class="w-[12rem]" />
                                            ${data.preview.type != 'form' ? (`
                                                <div class='mt-1'>${ companyData.phone_number }</div>
                                            `) : ''}
                                        </div>
                                    </div>
                                    <div class="right">
                                        <div class="logo text-right">
                                            <h1 class="text-2xl font-medium text-[var(--h-primary-color)]">${data.preview.document}</h1>
                                            <div class="mt-1 text-right ${cottonCount == 0 ? 'hidden' : ''}">Cotton: ${cottonCount}</div>
                                            ${previewData.shipment_no ? '<div class="mt-1 text-right">Shipment No.: ' + previewData.shipment_no + ' </div>' : ''}
                                            ${previewData.order_no ? '<div class="mt-1 text-right">Order No.: ' + previewData.order_no + ' </div>' : ''}
                                            ${data.preview.type == 'form' ? (`
                                                <div class='mt-1 text-sm'>${ companyData.phone_number }</div>
                                            `) : ''}
                                        </div>
                                    </div>
                                </div>
                                <hr class="w-full my-3 border-black">
                                ${data.preview.type != 'form' ? (`
                                    <div id="header" class="header w-full flex justify-between px-5">
                                        <div class="left w-50 space-y-1">
                                            ${data.preview.type == "order" || data.preview.type == "invoice" ? `
                                                <div class="customer text-lg leading-none capitalize font-medium text-nowrap">M/s: ${previewData.customer.customer_name}</div>
                                                <div class="person text-md text-lg leading-none">${previewData.customer.urdu_title}</div>
                                                <div class="address text-md leading-none">${previewData.customer.address}, ${previewData.customer.city.title}</div>
                                                <div class="phone text-md leading-none">${previewData.customer.phone_number}</div>
                                            ` : `
                                                <div class="date leading-none">Date: ${formatDate(previewData.date)}</div>
                                                <div class="number leading-none capitalize">${data.preview.type.replace('_', ' ')} No.: ${data.preview.type == 'shipment' ? previewData.shipment_no : data.preview.type == 'voucher' ? previewData.voucher_no : data.preview.type == 'cargo_list' ? previewData.cargo_no : ''}</div>
                                            `}
                                        </div>
                                        ${(data.preview.type == 'voucher' && previewData.supplier) || (data.preview.type && previewData.cargo_name) == 'cargo_list' ? `
                                            <div class="center my-auto ">
                                                <div class="supplier-name capitalize font-semibold text-md">Supplier Name: ${previewData.supplier?.supplier_name || previewData.cargo_name}</div>
                                            </div>
                                        ` : ''}
                                        <div class="right w-50 my-auto text-right text-sm text-black space-y-1.5">
                                            ${data.preview.type == "order" || data.preview.type == "invoice" ? `
                                                <div class="date leading-none">Date: ${formatDate(previewData.date)}</div>
                                                <div class="number leading-none capitalize font-medium">${data.preview.type} No.: ${data.preview.type == 'order' ? previewData.order_no : data.preview.type == 'invoice' ? previewData.invoice_no : ''}</div>
                                            ` : '' }
                                            <div class="preview-copy leading-none capitalize">${data.preview.type.replace('_', ' ')} Copy: ${data.preview.type == 'shipment' || (data.preview.type == 'voucher' && !previewData.supplier) ? 'Staff' : (data.preview.type == 'voucher' && previewData.supplier) ? 'Supplier' : data.preview.type == 'cargo_list' ? 'Cargo' : 'Customer'}</div>
                                            <div class="copy leading-none">Document: ${data.preview.document}</div>
                                        </div>
                                    </div>
                                    <hr class="w-full my-3 border-black">
                                    <div class="body w-full px-5 grow mx-auto">
                                        <div class="table w-full">
                                            <div class="table w-full border border-black rounded-lg pb-2.5 overflow-hidden">
                                                <div class="thead w-full">
                                                    <div class="tr ${data.preview.type == 'voucher' || data.preview.type == 'cargo_list' ? 'flex justify-between' : 'grid'} ${data.preview.type == 'shipment' ? 'grid-cols-8' : 'grid-cols-9'} w-full px-4 py-1.5 bg-[var(--primary-color)] text-white">
                                                        ${invoiceTableHeader}
                                                    </div>
                                                </div>
                                                <div id="tbody" class="tbody w-full">
                                                    ${invoiceTableBody}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `) : (`
                                    <div class="grow flex flex-col px-5">
                                        <div class="fields grow flex flex-col gap-3 pt-1">
                                            ${data.preview.data.formFields.map(field =>(`
                                                <div class="flex gap-3">
                                                    <label>${field.label} :</label>
                                                    <div class="grow border-b border-black capitalize ps-1"> ${field.text}</div>
                                                </div>
                                            `)).join('')}
                                        </div>
                                        <div class="signatureFields flex gap-6 w-full">
                                            <div class="grow flex gap-3">
                                                <label>Admin Sig. :</label>
                                                <div class="grow border-b border-black"></div>
                                            </div>
                                            <div class="grow flex gap-3">
                                                <label>Emp. Sig. :</label>
                                                <div class="grow border-b border-black"></div>
                                            </div>
                                        </div>
                                    </div>
                                `)}
                                ${invoiceBottom != '' ? `<hr class="w-full my-3 border-black">` : ''}
                                <div class="grid grid-cols-2 gap-2 px-5">
                                    ${invoiceBottom}
                                </div>
                                <hr class="w-full my-3 border-black">
                                <div class="footer flex w-full text-sm px-5 justify-between text-black">
                                    <P class="leading-none">Powered by SparkPair</P>
                                    <p class="leading-none text-sm">&copy; 2025 SparkPair | +92 316 5825495</p>
                                </div>
                            </div>
                        </div>
                `;
            });
            clutter += `
                    </div>`
        }

        {articlePages.length == 0 ? (
            clutter += `
                <div id="preview-container" class="mx-auto ${data.preview.size == "A5" ? "w-[145mm] h-[210mm]" : "w-[208mm] h-[300mm]"} relative overflow-hidden my-scrollbar-2">
                    <div id="preview" class="preview ${data.preview.size == "A5" ? "w-[145mm] h-[210mm]" : "w-[208mm] h-[300mm]"} overflow-hidden flex flex-col">
                        <div class="flex flex-col h-full">
                            <div id="banner" class="banner w-full flex justify-between items-center px-5">
                                <div class="left">
                                    <div class="logo">
                                        <img src="images/${companyData.logo}" alt="garmentsos-pro"
                                            class="w-[12rem]" />
                                        ${data.preview.type != 'form' ? (`
                                            <div class='mt-1'>${ companyData.phone_number }</div>
                                        `) : ''}
                                    </div>
                                </div>
                                <div class="right">
                                    <div class="logo text-right">
                                        <h1 class="text-2xl font-medium text-[var(--h-primary-color)]">${data.preview.document}</h1>
                                        <div class="mt-1 text-right ${cottonCount == 0 ? 'hidden' : ''}">Cotton: ${cottonCount}</div>
                                        ${previewData.shipment_no ? '<div class="mt-1 text-right">Shipment No.: ' + previewData.shipment_no + ' </div>' : ''}
                                        ${previewData.order_no ? '<div class="mt-1 text-right">Order No.: ' + previewData.order_no + ' </div>' : ''}
                                        ${data.preview.type == 'form' ? (`
                                            <div class='mt-1 text-sm'>${ companyData.phone_number }</div>
                                        `) : ''}
                                    </div>
                                </div>
                            </div>
                            <hr class="w-full my-3 border-black">
                            ${data.preview.type != 'form' ? (`
                                <div id="header" class="header w-full flex justify-between px-5">
                                    <div class="left w-50 space-y-1">
                                        ${data.preview.type == "order" || data.preview.type == "invoice" ? `
                                            <div class="customer text-lg leading-none capitalize font-medium text-nowrap">M/s: ${previewData.customer.customer_name}</div>
                                            <div class="person text-md text-lg leading-none">${previewData.customer.urdu_title}</div>
                                            <div class="address text-md leading-none">${previewData.customer.address}, ${previewData.customer.city.title}</div>
                                            <div class="phone text-md leading-none">${previewData.customer.phone_number}</div>
                                        ` : `
                                            <div class="date leading-none">Date: ${formatDate(previewData.date)}</div>
                                            <div class="number leading-none capitalize">${data.preview.type.replace('_', ' ')} No.: ${data.preview.type == 'shipment' ? previewData.shipment_no : data.preview.type == 'voucher' ? previewData.voucher_no : data.preview.type == 'cargo_list' ? previewData.cargo_no : ''}</div>
                                        `}
                                    </div>
                                    ${(data.preview.type == 'voucher' && previewData.supplier) || (data.preview.type && previewData.cargo_name) == 'cargo_list' ? `
                                        <div class="center my-auto ">
                                            <div class="supplier-name capitalize font-semibold text-md">Supplier Name: ${previewData.supplier?.supplier_name || previewData.cargo_name}</div>
                                        </div>
                                    ` : ''}
                                    <div class="right w-50 my-auto text-right text-sm text-black space-y-1.5">
                                        ${data.preview.type == "order" || data.preview.type == "invoice" ? `
                                            <div class="date leading-none">Date: ${formatDate(previewData.date)}</div>
                                            <div class="number leading-none capitalize font-medium">${data.preview.type} No.: ${data.preview.type == 'order' ? previewData.order_no : data.preview.type == 'invoice' ? previewData.invoice_no : ''}</div>
                                        ` : '' }
                                        <div class="preview-copy leading-none capitalize">${data.preview.type.replace('_', ' ')} Copy: ${data.preview.type == 'shipment' || (data.preview.type == 'voucher' && !previewData.supplier) ? 'Staff' : (data.preview.type == 'voucher' && previewData.supplier) ? 'Supplier' : data.preview.type == 'cargo_list' ? 'Cargo' : 'Customer'}</div>
                                        <div class="copy leading-none">Document: ${data.preview.document}</div>
                                    </div>
                                </div>
                                <hr class="w-full my-3 border-black">
                                <div class="body w-full px-5 grow mx-auto">
                                    <div class="table w-full">
                                        <div class="table w-full border border-black rounded-lg pb-2.5 overflow-hidden">
                                            <div class="thead w-full">
                                                <div class="tr ${data.preview.type == 'voucher' || data.preview.type == 'cargo_list' ? 'flex justify-between' : 'grid'} ${data.preview.type == 'shipment' ? 'grid-cols-8' : 'grid-cols-9'} w-full px-4 py-1.5 bg-[var(--primary-color)] text-white">
                                                    ${invoiceTableHeader}
                                                </div>
                                            </div>
                                            <div id="tbody" class="tbody w-full">
                                                ${invoiceTableBody}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `) : (`
                                <div class="grow flex flex-col px-5">
                                    <div class="fields grow flex flex-col gap-3 pt-1">
                                        ${data.preview.data.formFields.map(field =>(`
                                            <div class="flex gap-3">
                                                <label>${field.label} :</label>
                                                <div class="grow border-b border-black capitalize ps-1"> ${field.text}</div>
                                            </div>
                                        `)).join('')}
                                    </div>
                                    <div class="signatureFields flex gap-6 w-full">
                                        <div class="grow flex gap-3">
                                            <label>Admin Sig. :</label>
                                            <div class="grow border-b border-black"></div>
                                        </div>
                                        <div class="grow flex gap-3">
                                            <label>Emp. Sig. :</label>
                                            <div class="grow border-b border-black"></div>
                                        </div>
                                    </div>
                                </div>
                            `)}
                            ${invoiceBottom != '' ? `<hr class="w-full my-3 border-black">` : ''}
                            <div class="grid ${data.preview.type == 'order' || (data.preview.type == 'voucher' && previewData.supplier) ? 'grid-cols-3' : data.preview.type == 'voucher' && !previewData.supplier ? 'flex' : 'grid-cols-2'} gap-2 px-5">
                                ${invoiceBottom}
                            </div>
                            <hr class="w-full my-3 border-black">
                            <div class="footer flex w-full text-sm px-5 justify-between text-black">
                                <P class="leading-none">Powered by SparkPair</P>
                                <p class="leading-none text-sm">&copy; 2025 SparkPair | +92 316 5825495</p>
                            </div>
                        </div>
                    </div>
                </div>
            `
        ) : ''}
    }

    clutter += `
                    </div>
                </div>
            </div>
        </div>

        <div id="modal-action"
            class="bg-[var(--secondary-bg-color)] rounded-2xl shadow-lg max-w-3xl w-auto p-3 relative text-sm">
            <div class="flex gap-3">
                <button onclick="closeModal('${data.id}')" type="button"
                    class="px-4 py-2 bg-[var(--secondary-bg-color)] border border-gray-600 text-[var(--secondary-text)] rounded-lg hover:bg-[var(--h-bg-color)] transition-all duration-300 ease-in-out cursor-pointer hover:scale-[0.95]">
                    Close
                </button>
    `;

    if (data.bottomActions) {
        data.bottomActions.forEach(action => {
            if (action.id.includes('edit')) {
                clutter += `
                    <a id="${action.id}-in-modal" href="${window.location.pathname}/${action.dataId}/edit"
                        class="px-4 py-2 bg-[var(--secondary-bg-color)] border border-gray-600 text-[var(--secondary-text)] rounded-lg hover:bg-[var(--h-bg-color)] transition-all duration-300 ease-in-out cursor-pointer hover:scale-[0.95]">
                        ${action.text}
                    </a>
                `;
            } else {
                clutter += `
                    <button id="${action.id}-in-modal" type="${action.type ?? 'button'}" onclick='${action.onclick}'
                        class="px-4 py-2 bg-${(action.id.includes('add') || action.id.includes('done'))? '[var(--bg-success)]' : '[var(--secondary-bg-color)]'} border hover:border-${(action.id.includes('add') || action.id.includes('done'))? '[var(--border-success)] border-[var(--bg-success)]' : 'gray-600 border-gray-600'} text-${(action.id.includes('add') || action.id.includes('done'))? '[var(--border-success)]' : '[var(--secondary-text)]'} rounded-lg hover:bg-${(action.id.includes('add') || action.id.includes('done'))? '[var(--h-bg-success)]' : '[var(--h-bg-color)]'} transition-all duration-300 ease-in-out cursor-pointer hover:scale-[0.95]">
                        ${action.text}
                    </button>
                `;
            }
        });
    }

    if ((data.details && data.details['Balance'] == 0.0) || data.forceStatusBtn) {
        if (data.user?.status || data.status) {
            let status = data.user?.status ?? data.status;
            const [bgColor, hoverBgColor, textColor] = statusColor[status == 'active' ? status = 'in_active' : status = 'active'] || statusColor.inactive;
            clutter += `
                <div id="ac_in_modal">
                    <input type="hidden" id="user_id" name="user_id" value="${data.user?.id ?? data.uId}">
                    <input type="hidden" id="user_status" name="status" value="${data.user?.status ?? data.status}">
                    <button id="ac_in_btn" type="submit"
                        class="px-4 py-2 bg-${bgColor} border border-${bgColor} text-${textColor} font-semibold rounded-lg hover:bg-${hoverBgColor} transition-all duration-300 ease-in-out cursor-pointer hover:scale-[0.95] capitalize">
                        ${status.replace('_', ' ')}
                    </button>
                </div>
            `;
        }
    }

    clutter += `
                </div>
            </div>
        </form>
    `;
    modalWrapper.innerHTML = clutter;

    closeOnClickOutside = (e) => {
        const clickedId = e.target.id;
        if (clickedId === `${data.id}-wrapper` || clickedId === `${data.id}`) {
            const modal = document.getElementById(`${data.id}`);
            const modalWrapper = document.getElementById(`${data.id}-wrapper`);

            modal.classList.add('scale-out');
            modal.addEventListener('animationend', () => {
                modalWrapper.classList.add('fade-out');
                modalWrapper.addEventListener('animationend', () => {
                    modalWrapper.remove();
                }, { once: true });
            }, { once: true });
            document.removeEventListener('mousedown', closeOnClickOutside);
            document.removeEventListener('keydown', escToClose);
            document.removeEventListener('keydown', enterToSubmit);
        }
    };
    document.addEventListener('mousedown', closeOnClickOutside);

    // ✅ Escape Key to Close
    escToClose = (e) => {
        if (e.key === 'Escape') {
            const form = modalWrapper.querySelector('form');
            form.classList.add('scale-out');
            form.addEventListener('animationend', () => {
                modalWrapper.classList.add('fade-out');
                modalWrapper.addEventListener('animationend', () => {
                    modalWrapper.remove();
                }, { once: true });
            }, { once: true });

            // Optionally: remove these listeners after first use
            document.removeEventListener('mousedown', closeOnClickOutside);
            document.removeEventListener('keydown', escToClose);
            document.removeEventListener('keydown', enterToSubmit);
        }
    };

    // ✅ enter Key to subbmit
    enterToSubmit = (e) => {
        if (e.key === 'Enter') {
            const form = modalWrapper.querySelector('form');
            const btn = form.querySelector('#modal-action button[id*="add"], #modal-action button[id*="update"]');
            if (btn) {
                btn.click();
            }
        }
    };

    document.addEventListener('keydown', escToClose);
    if (data.defaultListener !== false) {
        document.addEventListener('keydown', enterToSubmit);
    }
    document.body.appendChild(modalWrapper);

    data.table ? renderTableBody(data.table.body) : '';

    data.fields?.forEach(field => {
        if (field.category == 'explicitHtml' && field.focus) {
            document.querySelector(`#${field.focus}`).focus();
        }
    })

    data.basicSearch ? document.querySelector('#basicSearch input').focus() : '';

    formatAllAmountInputs();
}

function renderTableBody(tableBody) {
    let bodyHTML = '';

    if (tableBody.length > 0) {
        tableBody.forEach(data => {
            const rowHTML = data.map(item => {
                let checkboxHTML = '';
                let inputHTML = '';

                if (item.input) {
                    inputHTML = `
                        <input class="${item.input.class || ''} w-[70%] border border-gray-600 bg-[var(--h-bg-color)] py-0.5 px-2 rounded-md text-xs focus:outline-none opacity-0 pointer-events-none" type="${item.input.type || 'text'}" name="${item.input.name || ''}" value="${item.input.value || ''}" min="${item.input.min || ''}" oninput="${item.input.oninput || ''}" onclick="${item.input.onclick || ''}" />
                    `;
                }

                if (item.checkbox) {
                    checkboxHTML = `
                        <input ${item.checked ? 'checked' : ''} type="checkbox" name="selected_customers[]"
                            class="row-checkbox mr-2 shrink-0 w-3.5 h-3.5 appearance-none border border-gray-400 rounded-sm checked:bg-[var(--primary-color)] checked:border-transparent focus:outline-none transition duration-150 cursor-pointer" />
                    `;
                }

                if (item.rawHTML) {
                    return item.rawHTML;
                } else {
                    if (item.checkbox || item.input) {
                        return `
                            <div class="${item.class}">
                                ${checkboxHTML}
                                ${inputHTML}
                            </div>
                        `;
                    } else {
                        return `<div class="${item.class}">${item.data}</div>`;
                    }
                }
            }).join('');
            bodyHTML += `
                <div id='${data[0].jsonData?.id}' ${data[0].jsonData ? `data-json='${JSON.stringify(data[0].jsonData)}'` : ''} data class="flex justify-between items-center border-t border-gray-600 py-2 px-4 ${data[0].checkbox ? 'cursor-pointer row-toggle select-none customer-row hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out' : ''}" ${data[0].checkbox ? 'onclick="console.log(this)"' : ''}>
                    ${rowHTML}
                </div>
            `;
        });
    } else {
        bodyHTML += `
            <div class="flex justify-between items-center border-t border-gray-600 py-2 px-4">
                <div class="grow text-center text-[var(--border-error)]">No available yet.</div>
            </div>
        `;
    }

    document.getElementById('table-body').innerHTML = bodyHTML;
}

function returnCardsInModal(data) {
    let cardsHTML = '';
    if (data.cards.data.length > 0) {
        data.cards.data.forEach(item => {
            cardsHTML += createCard(item);
        });
    } else {
        cardsHTML= `
            <div class="col-span-full text-center text-[var(--border-error)] text-md mt-4">No ${data.cards.name} yet</div>
        `;
    }
    return cardsHTML;
}

function renderCardsInModal(data) {
    document.querySelector(`.${data.id}CardsContainer`).innerHTML = returnCardsInModal(data);
}

function openSubMenu(event, card) {
    closeOpenedSubMenu();

    if(event.target.closest('.switchBtn')) return false;

    const subMenuDom = card.querySelector('.subMenu');

    subMenuDom.style.top = event.y + 'px';
    subMenuDom.style.left = event.x + 'px';

    subMenuDom.classList.remove('hidden');
}

function closeOpenedSubMenu() {
    document.querySelector('.subMenu:not(.hidden)')?.classList.add('hidden');
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.card')) {
        closeOpenedSubMenu();
    }
})

// function basicSearch(searchValue) {
//     if (searchValue == '') return;

//     console.log(searchValue, data.cards);
// }

function reRenderInfoInModal(specifier, value) {
    document.querySelector(specifier + ' .main-text').innerHTML = value;
}
