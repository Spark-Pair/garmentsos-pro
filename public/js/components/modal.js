function createModal(data, animate = 'animate') {
    const appInputBaseClass = 'w-full rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 text-[var(--text-color)] transition-all duration-200 ease-out placeholder:capitalize disabled:bg-transparent disabled:opacity-70 focus:outline-none focus:ring-2 focus:ring-[var(--primary-color)] focus:border-transparent';
    const appInputClassFor = (type = 'text') => `${appInputBaseClass} ${type === 'date' ? 'py-[7px]' : 'py-2'}`;
    const appButtonClass = 'bg-[var(--primary-color)] px-4 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out cursor-pointer text-nowrap disabled:opacity-50 disabled:cursor-not-allowed';
    const statusColor = {
        active: ['[var(--bg-success)]', '[var(--h-bg-success)]', '[var(--border-success)]'],
        transparent: ['transparent', 'transparent', 'transparent'],
        no_Image: ['[var(--bg-warning)]', '[var(--h-bg-warning)]', '[var(--border-warning)]'],
        inactive: ['[var(--bg-error)]', '[var(--h-bg-error)]', '[var(--border-error)]'],
    };
    const companyData = data.companyData || window.companyData || {};
    const companyLogoBase = (data.companyLogoBase || window.companyLogoBase || '/').replace(/\/+$/, '/') ;
    const explicitMaxWidth = (data.class || '').includes('max-w-');
    const isA5Preview = data.preview && (
        data.preview.size == "A5" || ['invoice', 'order', 'shipment', 'cargo_list', 'voucher'].includes(data.preview.type)
    );
    const isMenuModal = data.menuModal || data.id === 'menuModal';

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
            <div class="${data.class} ${data.preview ? `bg-white text-black ${isA5Preview ? "w-[calc(148mm+5rem)] max-w-[calc(100vw-2rem)]" : "max-w-4xl"} h-[35rem] py-0` : (isMenuModal ? 'bg-transparent' : 'bg-[var(--secondary-bg-color)]')} ${data.cards ? (isMenuModal ? 'h-[42rem] max-w-7xl' : 'h-[40rem] max-w-6xl') : (explicitMaxWidth || isA5Preview ? '' : 'max-w-2xl')} rounded-2xl ${isMenuModal ? 'shadow-none' : 'shadow-lg'} ${isA5Preview ? '' : 'w-full'} ${isMenuModal ? 'p-0' : 'p-6'} flex relative">
                <div id="modal-close" onclick="closeModal('${data.id}')" tabindex="-1"
                    class="absolute top-0 -right-4 translate-x-full bg-[var(--secondary-bg-color)] rounded-2xl shadow-lg w-auto p-3 text-sm transition-all duration-300 ease-in-out hover:scale-[0.95] cursor-pointer">
                    <button type="button" tabindex="-1" data-modal-close-button="true"
                        class="z-10 text-gray-400 hover:text-gray-600 hover:scale-[0.95] transition-all duration-300 ease-in-out cursor-pointer">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                            class="w-6 h-6" style="display: inline">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                ${data.info ?
                    `<div class="${data.id}Info absolute z-10 ${data.calcBottom?.length > 0 ? 'bottom-14' : isMenuModal ? 'bottom-4.5 left-4.5' : 'bottom-4 left-4 '} border border-[var(--glass-border-color)]/10 group bg-[var(--glass-border-color)]/5 backdrop-blur-md rounded-lg cursor-pointer flex items-center justify-end p-1 overflow-hidden h-auto pr-3 transition-all duration-300 ease-in-out shadow-md pointer-events-auto" >
                        <div class="flex items-center justify-center bg-[var(--bg-color)] border border-[var(--glass-border-color)]/20 rounded-md p-2" >
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
                    <img id="imageInModal" src="${data.image}" alt="" onerror="this.onerror=null;this.src='/images/no_image_icon.png';"
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
                    ${data.basicSearch ? renderBasicSearch() : ''}
                </div>
                ${detailsHTML}
        `;
    }

    function renderBasicSearch() {
        return `
            <div class="form-group relative" id="basicSearch">
                <div class="relative flex gap-2 w-sm pt-0.5">
                    <input
                        type="text"
                        placeholder="Search..."
                        autocomplete="off"
                        class="${appInputClassFor('text')}"
                        oninput="${data.onBasicSearch}"
                    />

                    <button
                        type="button"
                        class="${appButtonClass}"
                    >
                        <i class="text-xs fa-solid fa-magnifying-glass"></i>
                    </button>
                </div>

                <div
                    id="search_box-error"
                    class="text-[var(--border-error)] text-xs mt-1 hidden transition-all duration-300 ease-in-out"
                ></div>
            </div>
        `;
    }

    function renderSearchFilter() {
        return `
            <div id="search-form" class="search-box shrink-0">
                <!-- Search Input -->
                <div class="search-input">
                    <button id="filter-btn" type="button" onclick="openDropDown(event, this)"
                        title="Open search filters (Alt+F or \`)"
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
                            <button type="button" onclick="clearAllSearchFields()"
                                title="Clear filters (Alt+C)"
                                class="flex-1 px-4 py-2 bg-[var(--bg-error)] border border-[var(--bg-error)] text-[var(--text-error)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-error)] transition-all duration-300 ease-in-out cursor-pointer hover:scale-[0.95]">
                                Clear
                            </button>
                            <button type="button" onclick="applyFilters()"
                                title="Apply filters (Alt+S)"
                                class="flex-1 px-4 py-2 bg-[var(--secondary-bg-color)] border border-gray-600 text-[var(--secondary-text)] rounded-lg hover:bg-[var(--h-bg-color)] transition-all duration-300 ease-in-out cursor-pointer hover:scale-[0.95]">
                                Apply Filters
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
                    const fieldName = field.name ?? field.id ?? '';
                    const isOptional = !field.required && !field.readonly && !field.disabled;
                    const errorId = `${fieldName}-error`;
                    const errorIconHTML = fieldName ? `
                                        <div class="errorIconWrap absolute right-3 top-1/2 z-20 -translate-y-1/2">
                                            <button type="button" tabindex="-1" aria-label="Validation error"
                                                class="errorIcon peer flex size-[20px] items-center justify-center rounded-full border border-[var(--border-error)] bg-[color-mix(in_srgb,var(--border-error)_10%,var(--secondary-bg-color))] text-[13px] font-bold leading-none text-[var(--border-error)] opacity-0 pointer-events-none transition-all duration-200">
                                                !
                                            </button>

                                            <div id="${errorId}" role="alert"
                                                class="field-error-msg hidden absolute bottom-[calc(100%+8px)] right-0 z-50 w-max min-w-[9rem] max-w-[230px] rounded-md border border-[color-mix(in_srgb,var(--border-error)_35%,transparent)] bg-[var(--secondary-bg-color)] px-3 py-2 text-xs font-medium leading-4 text-[var(--text-color)] shadow-[0_10px_30px_rgba(15,23,42,0.16)] opacity-0 pointer-events-none translate-y-1 transition-all duration-150 peer-hover:translate-y-0 peer-hover:opacity-100 peer-focus:translate-y-0 peer-focus:opacity-100"></div>
                                        </div>
                                    ` : '';

                    if (field.btnId) {
                        buttonHTML = `
                            <button onclick="${field.onclick ?? ''}" id="${field.btnId ?? ''}" type="button" class="bg-[var(--primary-color)] px-4 rounded-lg hover:bg-[var(--h-primary-color)] transition-all duration-300 ease-in-out cursor-pointer text-lg font-bold disabled:opacity-50 disabled:cursor-not-allowed" disabled>+</button>
                        `;
                    }

                    clutter += `
                        <div class="${field.grow ? 'grow' : ''} ${field.full ? 'col-span-full' : ''}">
                            <div class="form-group relative ${field.hidden ? 'hidden' : ''}">
                                ${field.label ? `
                                    <span class="mb-2 flex items-center justify-between">
                                        <label for="${field.id || field.name || ''}" class="block font-medium text-[var(--secondary-text)]">
                                            ${field.label}${isOptional ? ' (optional)' : ''}
                                        </label>
                                    </span>
                                ` : ''}

                                <div class="field-control relative flex gap-4">
                                    <input onkeydown="${field.enterToSubmitListener ? 'enterToSubmit(event)' : ''}" id="${field.id ?? ''}" type="${field.type ?? 'text'}" name="${field.name ?? ''}" value="${field.value ?? ''}" min="${field.min}" max="${field.max}" placeholder="${field.placeholder ?? ''}" data-validate="${field.data_validate ?? ''}" ${field.required ? 'required' : ''} ${field.disabled ? 'disabled' : ''} ${field.readonly ? 'readonly' : ''}
                                    ${field.data_validate ? `oninput="validateInput(this); ${field.oninput ?? ''}"` : (field.oninput ? `oninput="${field.oninput}"` : '')}
                                    onchange="${field.onchange ?? ''}" class="${appInputClassFor(field.type ?? 'text')}" ${fieldName ? `aria-describedby="${errorId}"` : ''}>
                                    ${buttonHTML}
                                    ${errorIconHTML}
                                </div>
                            </div>
                        </div>
                    `;

                    if (field.focus) {
                        setTimeout(() => {
                            const input = document.getElementById(`${field.id}`);
                            if (input) input.focus();
                        }, 120);
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
                            <option value="${option.id}" data-option='${jsonAttr(option.data_option)}'>${option.text}</option>
                        `;
                    });
                }

                clutter += `
                    <div class="grow form-group">
                        <label for="${field.name ?? ''}" class="block font-medium text-[var(--secondary-text)] mb-2">${field.label} *</label>

                        <div class="selectParent relative flex gap-3">
                            <select id="${field.id ?? ''}" name="${field.name ?? ''}" onchange="${field.onchange}" value="${field.value || ''}" class="${appInputClassFor('text')} appearance-none" ${field.required ? 'required' : ''} ${field.disabled ? 'disabled' : ''} ${field.readonly ? 'readonly' : ''}>
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

    if (data.cards && isMenuModal) {
        clutter += `
            <div class="flex-1 flex flex-col ${data.image ? 'ml-8' : ''} h-full w-full overflow-hidden">
                <div class="rounded-3xl border border-[var(--glass-border-color)]/25 bg-[var(--h-secondary-bg-color)] shadow-sm overflow-hidden flex h-full flex-col">
                    <div class="flex flex-col gap-4 border-b border-[var(--glass-border-color)]/20 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex items-center gap-3">
                            <span class="h-10 w-1.5 shrink-0 rounded-full bg-[var(--primary-color)]/80"></span>
                            <div>
                                <h5 id="name" class="text-lg font-semibold uppercase tracking-wide text-[var(--text-color)]">${data.cards.name}</h5>
                                <p class="text-xs text-[var(--secondary-text)]">Choose the modules shown as quick shortcuts in the left menu.</p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            ${!isMenuModal && data.info ? `<div class="${data.id}Info rounded-lg border border-[var(--glass-border-color)]/20 bg-[var(--h-bg-color)] px-3 py-2 text-xs font-semibold text-[var(--secondary-text)] shadow-sm">
                                ${data.info || ''}
                            </div>` : ''}
                            ${data.basicSearch ? `<div class="form-group relative" id="basicSearch">
                                <div class="relative flex gap-2 min-w-[17rem] pt-0.5">
                                    <input
                                        type="text"
                                        placeholder="Search menu..."
                                        autocomplete="off"
                                        class="${appInputClassFor('text')}"
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
                    </div>
                    <div class="flex-1 overflow-y-auto my-scrollbar-2 p-4">
                        <div class="${data.id}CardsContainer grid grid-cols-1 md:grid-cols-2 xl:grid-cols-${data.cards.count} w-full gap-3 text-sm">
                            ${returnCardsInModal(data)}
                        </div>
                    </div>
                </div>
            </div>
        `;
    } else if (data.cards) {
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
                                class="${appInputClassFor('text')}"
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
                    class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 ${data.table.headerPaddingClass || 'px-4'} mb-3">
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

        calcBottomClass = data.calcBottomClass || calcBottomClass;

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

    if (data.preview) {
        if (window.DocumentPreview?.render) {
            clutter += window.DocumentPreview.render(data, { companyData, companyLogoBase });
        } else {
            clutter += `
                <div id="preview-container" class="h-auto mx-auto relative flex flex-col">
                    <div class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex items-center justify-center bg-white text-black">
                        Preview renderer not loaded.
                    </div>
                </div>
            `;
        }
    }

    clutter += `
                    </div>
                </div>
            </div>
        </div>
    `;

    if (!isMenuModal) {
        clutter += `
        <div id="modal-action"
            class="bg-[var(--secondary-bg-color)] rounded-2xl shadow-lg max-w-3xl w-auto p-3 relative text-sm">
            <div class="flex gap-3">
    `;

    const validRecordId = (value) => value !== null
        && typeof value !== 'undefined'
        && String(value).trim() !== ''
        && String(value) !== 'undefined';
    const usableLink = (link) => typeof link === 'string'
        && link.trim() !== ''
        && !/\/(?:undefined|null|NaN)(?:\/|$)/.test(link);

    const editActionHref = (action) => {
        const recordId = action.dataId ?? data.id;
        if (!validRecordId(recordId)) return null;

        const basePath = window.location.pathname.replace(/\/+$/, '');
        return `${basePath}/${encodeURIComponent(recordId)}/edit`;
    };

    if (data.bottomActions) {
        data.bottomActions.forEach(action => {
            if (action.link) {
                if (!usableLink(action.link)) return;

                clutter += `
                    <a id="${action.id}-in-modal" href="${action.link}"
                        class="px-4 py-2 bg-[var(--secondary-bg-color)] border border-gray-600 text-[var(--secondary-text)] rounded-lg hover:bg-[var(--h-bg-color)] transition-all duration-300 ease-in-out cursor-pointer hover:scale-[0.95]">
                        ${action.text}
                    </a>
                `;
            } else if (action.onclick) {
                clutter += `
                    <button id="${action.id}-in-modal" type="${action.type ?? 'button'}" onclick='${htmlAttr(action.onclick)}'
                        class="px-4 py-2 bg-${(action.id.includes('add') || action.id.includes('done'))? '[var(--bg-success)]' : '[var(--secondary-bg-color)]'} border hover:border-${(action.id.includes('add') || action.id.includes('done'))? '[var(--border-success)] border-[var(--bg-success)]' : 'gray-600 border-gray-600'} text-${(action.id.includes('add') || action.id.includes('done'))? '[var(--border-success)]' : '[var(--secondary-text)]'} rounded-lg hover:bg-${(action.id.includes('add') || action.id.includes('done'))? '[var(--h-bg-success)]' : '[var(--h-bg-color)]'} transition-all duration-300 ease-in-out cursor-pointer hover:scale-[0.95]">
                        ${action.text}
                    </button>
                `;
            } else if (action.id.includes('edit')) {
                const href = editActionHref(action);
                if (!href) return;

                clutter += `
                    <a id="${action.id}-in-modal" href="${href}"
                        class="px-4 py-2 bg-[var(--secondary-bg-color)] border border-gray-600 text-[var(--secondary-text)] rounded-lg hover:bg-[var(--h-bg-color)] transition-all duration-300 ease-in-out cursor-pointer hover:scale-[0.95]">
                        ${action.text}
                    </a>
                `;
            } else {
                clutter += `
                    <button id="${action.id}-in-modal" type="${action.type ?? 'button'}" onclick='${htmlAttr(action.onclick)}'
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
                    <button onclick="closeModal('${data.id}')" type="button"
                        class="px-4 py-2 bg-[var(--secondary-bg-color)] border border-gray-600 text-[var(--secondary-text)] rounded-lg hover:bg-[var(--h-bg-color)] transition-all duration-300 ease-in-out cursor-pointer hover:scale-[0.95]">
                        Close
                    </button>
                </div>
            </div>
        `;
    }

    clutter += `
        </form>
    `;
    modalWrapper.innerHTML = clutter;

    const deferModalFocus = (callback) => {
        window.requestAnimationFrame(() => {
            window.requestAnimationFrame(() => {
                window.setTimeout(callback, 40);
            });
        });
    };

    const focusModalSearchInput = () => {
        const wrappers = Array.from(document.querySelectorAll('div[id$="-wrapper"]'));
        const lastWrapper = wrappers[wrappers.length - 1];
        if (!lastWrapper || lastWrapper !== modalWrapper) return false;

        const input = modalWrapper.querySelector('#basicSearch input');
        if (!input) return false;

        input.focus();
        input.select?.();
        return true;
    };

    const focusFirstModalControl = () => {
        const selectors = [
            'input:not([type="hidden"]):not([disabled])',
            'select:not([disabled])',
            'textarea:not([disabled])',
            'button:not([disabled]):not([data-modal-close-button="true"])',
            'a[href]',
            '[role="button"]:not([data-modal-close-button="true"])',
        ];
        const target = modalWrapper.querySelector(selectors.join(', '));
        if (!target) return false;

        target.focus();
        if (target.matches('input, textarea')) {
            target.select?.();
        }
        return true;
    };

    const focusSearchShortcut = (e) => {
        if (!e.altKey || e.ctrlKey || e.metaKey || e.key.toLowerCase() !== 'f') return;
        if (!focusModalSearchInput()) return;

        e.preventDefault();
        e.stopPropagation();
    };

    const removeModalListeners = () => {
        document.removeEventListener('mousedown', closeOnClickOutside);
        document.removeEventListener('keydown', escToClose);
        document.removeEventListener('keydown', enterToSubmit);
        document.removeEventListener('keydown', focusSearchShortcut);
    };

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
            removeModalListeners();
        }
    };
    document.addEventListener('mousedown', closeOnClickOutside);

    // ✅ Escape Key to Close
    escToClose = (e) => {
        if (e.key === 'Escape') {
            const wrappers = Array.from(document.querySelectorAll('div[id$="-wrapper"]'));
            const lastWrapper = wrappers[wrappers.length - 1];
            if (!lastWrapper || lastWrapper !== modalWrapper) return;

            const form = modalWrapper.querySelector('form');
            form.classList.add('scale-out');
            form.addEventListener('animationend', () => {
                modalWrapper.classList.add('fade-out');
                modalWrapper.addEventListener('animationend', () => {
                    modalWrapper.remove();
                }, { once: true });
            }, { once: true });

            removeModalListeners();
        }
    };

    // ✅ enter Key to subbmit
    enterToSubmit = (e) => {
        if (e.defaultPrevented) return;
        if (e.target?.matches?.('input, select, textarea')) return;

        if (e.key === 'Enter') {
            const form = modalWrapper.querySelector('form');
            const btn = form.querySelector('#modal-action button[id*="add"], #modal-action button[id*="update"]');
            if (btn) {
                btn.click();
            }
        }
    };

    document.addEventListener('keydown', escToClose);
    document.addEventListener('keydown', focusSearchShortcut);
    if (data.defaultListener !== false) {
        document.addEventListener('keydown', enterToSubmit);
    }
    document.body.appendChild(modalWrapper);

    if (data.cards) {
        setupCardKeyboardNavigation(modalWrapper, data);
    }

    data.table ? renderTableBody(data.table.body, data.table.rowPaddingClass) : '';

    deferModalFocus(() => {
        data.fields?.forEach(field => {
            if (field.category == 'explicitHtml' && field.focus) {
                document.querySelector(`#${field.focus}`)?.focus();
            }
        });

        if (data.basicSearch && focusModalSearchInput()) {
            return;
        }

        focusFirstModalControl();
    });

    formatAllAmountInputs();
}

function renderTableBody(tableBody, rowPaddingClass = 'px-4') {
    let bodyHTML = '';

    if (tableBody.length > 0) {
        tableBody.forEach(data => {
            const rowHTML = data.map(item => {
                let checkboxHTML = '';
                let inputHTML = '';

                if (item.input) {
                    inputHTML = `
                        <input class="${item.input.class || ''} w-[70%] rounded-lg border border-gray-600 bg-[var(--h-bg-color)] px-3 py-2 text-xs text-[var(--text-color)] transition-all duration-200 ease-out placeholder:capitalize disabled:bg-transparent disabled:opacity-70 focus:outline-none focus:ring-2 focus:ring-[var(--primary-color)] focus:border-transparent opacity-0 pointer-events-none" type="${item.input.type || 'text'}" name="${item.input.name || ''}" value="${item.input.value || ''}" min="${item.input.min || ''}" oninput="${item.input.oninput || ''}" onclick="${item.input.onclick || ''}" />
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
                <div id='${data[0].jsonData?.id}' ${data[0].jsonData ? `data-json='${jsonAttr(data[0].jsonData)}'` : ''} data class="flex justify-between items-center border-t border-gray-600 py-2 ${rowPaddingClass} ${data[0].checkbox ? 'cursor-pointer row-toggle select-none customer-row hover:bg-[var(--h-secondary-bg-color)] transition-all fade-in ease-in-out' : ''}">
                    ${rowHTML}
                </div>
            `;
        });
    } else {
        bodyHTML += `
            <div class="flex justify-between items-center border-t border-gray-600 py-2 ${rowPaddingClass}">
                <div class="grow text-center text-[var(--border-error)]">No available yet.</div>
            </div>
        `;
    }

    document.getElementById('table-body').innerHTML = bodyHTML;
}

function modalText(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function menuCardDescription(data) {
    if (!data.details || typeof data.details !== 'object') {
        return '';
    }

    const first = Object.values(data.details).find(value => String(value ?? '').trim() !== '');
    return first ? modalText(first) : '';
}

function createMenuModalCard(data) {
    const isEnabled = Boolean(data.switchBtn?.active);
    const actionCount = Array.isArray(data.subMenu) ? data.subMenu.length : 0;
    const description = menuCardDescription(data);
    const actionText = actionCount > 0 ? 'View Actions' : 'Go to Page';
    const actionMetaText = actionCount > 0
        ? `${actionCount} ${actionCount === 1 ? 'action' : 'actions'}`
        : 'Direct page';
    const shortcutStatusText = isEnabled ? 'Pinned' : 'Not pinned';

    let submenuHtml = '';
    if (Array.isArray(data.subMenu) && data.subMenu.length) {
        submenuHtml = `
            <div class="subMenu text-sm fixed border border-[var(--glass-border-color)]/30 w-56 bg-[var(--h-secondary-bg-color)] text-[var(--text-color)] shadow-xl rounded-2xl transform scale-95 transition-all duration-300 ease-in-out z-50 opacity-100 scale-in hidden" style="top: 0; left: 0;">
                <ul class="p-1.5">
                    ${data.subMenu.map(subMenuAction => `
                        <li>
                            <a href="${subMenuAction.href}" class="block px-4 py-2 hover:bg-[var(--h-bg-color)] rounded-xl transition-all duration-200 ease-in-out text-nowrap">
                                ${modalText(subMenuAction.name)}
                            </a>
                        </li>
                    `).join('')}
                </ul>
            </div>
        `;
    }

    return `
        <div id="${data.id}" data-json='${jsonAttr(data)}' oncontextmenu='${htmlAttr(data.oncontextmenu || "")}' role="button" tabindex="0" data-keyboard-action="true"
            class="item card menu-modal-card no-translate relative flex min-h-[8.65rem] flex-col justify-between overflow-hidden rounded-2xl border border-[var(--glass-border-color)]/25 bg-[var(--secondary-bg-color)] p-4 shadow-sm transition-all duration-200 ease-in-out hover:border-[var(--primary-color)]/40 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-[var(--primary-color)]/40"
            onclick='${htmlAttr(data.onclick || "")}'>
            <div class="flex items-start justify-between gap-3">
                <div class="flex min-w-0 items-start gap-3">
                    <div class="flex size-11 shrink-0 items-center justify-center rounded-xl border border-[var(--glass-border-color)]/25 bg-[var(--h-bg-color)] text-[var(--text-color)]">
                        ${data.svgIcon || `<span class="text-sm font-bold">${modalText(String(data.name || 'M').slice(0, 2).toUpperCase())}</span>`}
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h6 class="truncate text-base font-semibold leading-tight text-[var(--text-color)]">${modalText(data.name || 'Menu')}</h6>
                            <span data-menu-shortcut-status="${modalText(data.id || '')}" class="inline-flex h-5 items-center gap-1.5 rounded-lg border ${isEnabled ? 'border-[var(--primary-color)]/25 bg-[var(--primary-color)]/10 text-[var(--primary-color)] shadow-[inset_0_1px_0_rgb(255_255_255_/_0.22)]' : 'border-[var(--glass-border-color)]/35 bg-[var(--h-bg-color)]/70 text-[var(--secondary-text)] shadow-[inset_0_1px_0_rgb(255_255_255_/_0.14)]'} px-2 text-[10px] font-semibold leading-none">
                                <i class="size-1.5 rounded-full ring-2 ${isEnabled ? 'bg-[var(--primary-color)] ring-[var(--primary-color)]/15' : 'bg-[var(--secondary-text)]/55 ring-[var(--secondary-text)]/10'}"></i>
                                <span>${shortcutStatusText}</span>
                            </span>
                        </div>
                        ${description ? `<p class="mt-1 text-xs leading-5 text-[var(--secondary-text)]">${description}</p>` : ''}
                    </div>
                </div>
                <div data-for='${data.id}' onclick='switchBtnTogggle(this, event)' title="${isEnabled ? 'Remove from menu' : 'Add to menu'}"
                    class="switchBtn shrink-0 ${isEnabled ? 'active' : ''}">
                    <div class="circle"></div>
                </div>
            </div>
            <div class="mt-4 flex items-center justify-between border-t border-[var(--glass-border-color)]/15 pt-3">
                <span class="inline-flex h-7 items-center rounded-lg bg-[var(--h-bg-color)] px-2.5 text-[11px] font-semibold text-[var(--secondary-text)]">
                    ${actionMetaText}
                </span>
                <button type="button" class="inline-flex h-7 items-center gap-2 rounded-lg border border-[var(--glass-border-color)]/30 bg-[var(--h-bg-color)] px-3 text-xs font-semibold text-[var(--secondary-text)] transition-all duration-200 hover:border-[var(--primary-color)]/40 hover:bg-[var(--secondary-bg-color)] hover:text-[var(--text-color)]">
                    ${actionText}
                    <i class="fa-solid ${actionCount > 0 ? 'fa-chevron-down' : 'fa-arrow-right'} text-[10px]"></i>
                </button>
            </div>
            ${submenuHtml}
        </div>
    `;
}

function returnCardsInModal(data) {
    let cardsHTML = '';
    const cardRenderer = data.cards.useMenuCard
        ? createMenuModalCard
        : data.cards.useBaseCard && typeof window.baseCreateCard === 'function'
        ? window.baseCreateCard
        : createCard;

    if (data.cards.data.length > 0) {
        data.cards.data.forEach(item => {
            cardsHTML += cardRenderer(item);
        });
    } else {
        cardsHTML= `
            <div class="col-span-full text-center text-[var(--border-error)] text-md mt-4">No ${data.cards.name} yet</div>
        `;
    }
    return cardsHTML;
}

function openSubMenuAtCard(card) {
    const subMenuDom = card.querySelector('.subMenu');
    if (!subMenuDom) return false;

    const rect = card.getBoundingClientRect();
    subMenuDom.style.top = (rect.top + rect.height / 2) + 'px';
    subMenuDom.style.left = (rect.right + 8) + 'px';

    subMenuDom.classList.remove('hidden');
    if (card.dataset?.kbIndex) {
        subMenuDom.dataset.kbParent = card.dataset.kbIndex;
    }
    subMenuDom.dataset.kbSkipClick = '1';
    const links = Array.from(subMenuDom.querySelectorAll('a'));
    links.forEach(a => {
        a.setAttribute('tabindex', '0');
        a.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                a.click();
            }
        }, { once: true });
    });
    links[0]?.focus();
    return true;
}

document.addEventListener('click', (e) => {
    const openMenu = document.querySelector('.subMenu:not(.hidden)');
    if (openMenu?.dataset?.kbSkipClick === '1') {
        if (openMenu.contains(e.target)) {
            openMenu.dataset.kbSkipClick = '';
            return;
        }
        e.stopPropagation();
        e.preventDefault();
        openMenu.dataset.kbSkipClick = '';
    }
}, true);

function setupCardKeyboardNavigation(modalWrapper, data) {
    const container = modalWrapper.querySelector(`.${data.id}CardsContainer`);
    if (!container) return;

    modalWrapper.dataset.kbColumns = String(Number(data.cards?.count) || 1);

    const getCards = () => Array.from(container.querySelectorAll('.item.card'));
    const getVisibleCards = () => getCards().filter(c => !c.classList.contains('hidden'));
    const cards = getCards();
    if (!cards.length) return;

    cards.forEach((card, i) => {
        if (card.dataset.kbCardBound === '1') return;
        card.dataset.kbCardBound = '1';
        card.setAttribute('tabindex', '0');
        card.dataset.kbIndex = String(i);
        card.addEventListener('focus', () => {
            modalWrapper.dataset.kbIndex = String(i);
        });
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                e.stopPropagation();
                if (openSubMenuAtCard(card)) {
                    modalWrapper.dataset.kbJustOpened = '1';
                    return;
                }
                card.click();
            }
        });
    });

    const getColumns = () => Number(modalWrapper.dataset.kbColumns) || 1;
    const clamp = (i, list) => Math.max(0, Math.min(list.length - 1, i));
    const focusCard = (i) => {
        const list = getVisibleCards();
        if (!list.length) return;
        const idx = clamp(i, list);
        list[idx].focus();
        list[idx].scrollIntoView({ block: 'nearest', inline: 'nearest' });
        modalWrapper.dataset.kbIndex = String(idx);
    };
    const focusSearchInput = () => {
        const input = modalWrapper.querySelector('#basicSearch input');
        if (!input) return false;
        input.focus();
        return true;
    };

    const closeSubMenuInWrapper = () => {
        const openMenu = modalWrapper.querySelector('.subMenu:not(.hidden)');
        if (openMenu) {
            openMenu.classList.add('hidden');
            openMenu.dataset.kbSkipClick = '';
            const parentIndex = openMenu.dataset?.kbParent;
            if (parentIndex) {
                focusCard(Number(parentIndex));
            }
            return true;
        }
        return false;
    };

    if (modalWrapper.dataset.kbWrapperBound === '1') {
        if (!modalWrapper.contains(document.activeElement) || document.activeElement === modalWrapper) {
            focusCard(0);
        }
        return;
    }
    modalWrapper.dataset.kbWrapperBound = '1';

    modalWrapper.addEventListener('keydown', (e) => {
        const wrappers = Array.from(document.querySelectorAll('div[id$=\"-wrapper\"]'));
        const lastWrapper = wrappers[wrappers.length - 1];
        if (lastWrapper && lastWrapper !== modalWrapper) return;

        if (data.basicSearch && e.altKey && !e.ctrlKey && !e.metaKey && e.key.toLowerCase() === 'f') {
            e.preventDefault();
            e.stopPropagation();
            focusSearchInput();
            return;
        }

        const openMenu = modalWrapper.querySelector('.subMenu:not(.hidden)');
        if (openMenu) {
            const links = Array.from(openMenu.querySelectorAll('a'));
            const currentIndex = Math.max(0, links.indexOf(document.activeElement));
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                const next = links[Math.min(links.length - 1, currentIndex + 1)] || links[0];
                next?.focus();
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                const prev = links[Math.max(0, currentIndex - 1)] || links[0];
                prev?.focus();
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                openMenu.dataset.kbSkipClick = '';
                document.activeElement?.click();
                return;
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                openMenu.classList.add('hidden');
                openMenu.dataset.kbSkipClick = '';
                return;
            }
        }

        const tag = e.target?.tagName?.toUpperCase();
        if (tag === 'INPUT' || tag === 'TEXTAREA') return;

        const cols = getColumns();
        const current = Number(modalWrapper.dataset.kbIndex || 0);

        if (e.key === 'ArrowRight') {
            e.preventDefault();
            focusCard(current + 1);
        } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            focusCard(current - 1);
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            focusCard(current + cols);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            focusCard(current - cols);
        } else if (e.key === 'Home') {
            e.preventDefault();
            focusCard(0);
        } else if (e.key === 'End') {
            e.preventDefault();
            const list = getVisibleCards();
            focusCard(list.length - 1);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const list = getVisibleCards();
            const card = list[current] || list[0];
            if (!card) return;
            if (openSubMenuAtCard(card)) {
                modalWrapper.dataset.kbJustOpened = '1';
                return;
            }
            card.click();
        } else if (e.key === ' ') {
            e.preventDefault();
            const list = getVisibleCards();
            const card = list[current] || list[0];
            if (!card) return;
            if (openSubMenuAtCard(card)) {
                modalWrapper.dataset.kbJustOpened = '1';
                return;
            }
            card.click();
        } else if (e.key === 'Tab') {
            e.preventDefault();
            const list = getVisibleCards();
            if (!list.length) return;
            const next = e.shiftKey ? current - 1 : current + 1;
            focusCard(next);
        } else if (e.key === 'Escape') {
            if (closeSubMenuInWrapper()) return;
        }
    });

    const searchInput = modalWrapper.querySelector('#basicSearch input');
    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                focusCard(0);
            }
        });
    }

    window.requestAnimationFrame(() => {
        window.requestAnimationFrame(() => {
            window.setTimeout(() => {
                if (searchInput) {
                    focusSearchInput();
                } else {
                    focusCard(0);
                }
            }, 40);
        });
    });

    modalWrapper.addEventListener('keyup', (e) => {
        if (modalWrapper.dataset.kbJustOpened === '1' && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            modalWrapper.dataset.kbJustOpened = '';
        }
    });
}

function renderCardsInModal(data) {
    document.querySelector(`.${data.id}CardsContainer`).innerHTML = returnCardsInModal(data);
    const wrapper = document.getElementById(`${data.id}-wrapper`);
    if (wrapper) setupCardKeyboardNavigation(wrapper, data);
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

function reRenderInfoInModal(specifier, value) {
    const containers = document.querySelectorAll(specifier);
    if (!containers.length) return;

    containers.forEach(container => {
        const mainText = container.querySelector('.main-text');
        if (mainText) {
            mainText.innerHTML = value;
            return;
        }

        container.innerHTML = value;
    });
}
