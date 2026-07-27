(function() {
    const originalPrintPage = window.printPage;
    let printColumns = [];
    let draggedRow = null;

    window.openPrintColumnModal = function() {
        const tableHead = document.querySelector('#table-head');
        if (!tableHead) {
            appAlert('Table header not found');
            return;
        }

        const columns = Array.from(tableHead.children).map((col, index) => ({
            index,
            originalIndex: index,
            text: col.textContent.trim(),
            width: col.className.match(/w-\[(\d+)%\]/)?.[1] || '10',
            selected: true,
            mergeWith: null
        }));

        printColumns = columns;

        let tableBody = printColumns.map((col, displayIndex) => [
            {
                rawHTML: `
                    <div class="w-[5%] flex items-center justify-center cursor-move drag-handle" data-display-index="${displayIndex}">
                        <i class="fas fa-grip-vertical text-gray-400"></i>
                    </div>
                `
            },
            {
                checkbox: true,
                checked: col.selected,
                class: 'w-[8%] flex items-center'
            },
            {
                data: col.text,
                class: 'grow font-medium truncate'
            },
            {
                rawHTML: `
                    <div class="w-[15%] flex items-center gap-2">
                        <select class="merge-select text-xs px-2 py-1 rounded bg-[var(--h-bg-color)] border border-gray-600 w-full" data-display-index="${displayIndex}">
                            <option value="">No Merge</option>
                            ${printColumns.map((c, i) => i !== displayIndex ? `<option value="${i}" ${col.mergeWith === i ? 'selected' : ''}>→ ${c.text}</option>` : '').join('')}
                        </select>
                    </div>
                `
            }
        ]);

        let modalData = {
            id: 'printColumnModal',
            name: 'Select & Arrange Columns',
            class: 'p-5 max-w-4xl h-[35rem]',
            table: {
                headers: [
                    { label: "", class: "w-[5%]" },
                    { label: "Select", class: "w-[8%]" },
                    { label: "Column Name", class: "grow" },
                    { label: "Merge With", class: "w-[15%]" }
                ],
                body: tableBody,
            },
            bottomActions: [
                {
                    id: 'select-all',
                    text: 'Select All',
                    type: 'button',
                    onclick: 'selectAllPrintColumns(true)'
                },
                {
                    id: 'deselect-all',
                    text: 'Deselect All',
                    type: 'button',
                    onclick: 'selectAllPrintColumns(false)'
                },
                {
                    id: 'reset-order',
                    text: 'Reset Order',
                    type: 'button',
                    onclick: 'resetColumnOrder()'
                },
                {
                    id: 'print-selected',
                    text: 'Print',
                    type: 'button',
                    onclick: 'printWithSelectedColumns()'
                }
            ]
        };

        createModal(modalData);

        setTimeout(() => {
            setupModalInteractions();
        }, 150);
    };

    function updateTableBodyOnly() {
        const tableBody = document.querySelector('#printColumnModal #table-body');
        if (!tableBody) return;

        let bodyHTML = '';

        printColumns.forEach((col, displayIndex) => {
            const mergeOptions = printColumns
                .map((c, i) => i !== displayIndex ? `<option value="${i}" ${col.mergeWith === i ? 'selected' : ''}>→ ${c.text}</option>` : '')
                .join('');

            bodyHTML += `
                <div class="flex justify-between items-center border-t border-gray-600 py-2 px-4 cursor-pointer hover:bg-[var(--h-secondary-bg-color)] transition-all">
                    <div class="w-[5%] flex items-center justify-center cursor-move drag-handle" data-display-index="${displayIndex}">
                        <i class="fas fa-grip-vertical text-gray-400"></i>
                    </div>
                    <div class="w-[8%] flex items-center">
                        <input ${col.selected ? 'checked' : ''} type="checkbox" class="row-checkbox mr-2 shrink-0 w-3.5 h-3.5 appearance-none border border-gray-400 rounded-sm checked:bg-[var(--primary-color)] checked:border-transparent focus:outline-none transition duration-150 cursor-pointer" />
                    </div>
                    <div class="grow font-medium truncate">${col.text}</div>
                    <div class="w-[15%] flex items-center gap-2">
                        <select class="merge-select text-xs px-2 py-1 rounded bg-[var(--h-bg-color)] border border-gray-600 w-full" data-display-index="${displayIndex}">
                            <option value="">No Merge</option>
                            ${mergeOptions}
                        </select>
                    </div>
                </div>
            `;
        });

        tableBody.innerHTML = bodyHTML;

        setTimeout(() => {
            setupModalInteractions();
        }, 50);
    }

    function setupModalInteractions() {
        const tableBody = document.querySelector('#printColumnModal #table-body');
        if (!tableBody) {
            console.error('Table body not found');
            return;
        }

        const rows = Array.from(tableBody.children);

        rows.forEach((row, displayIndex) => {
            const checkbox = row.querySelector('.row-checkbox');
            if (checkbox) {
                row.addEventListener('click', function(e) {
                    if (e.target === checkbox ||
                        e.target.classList.contains('drag-handle') ||
                        e.target.closest('.drag-handle') ||
                        e.target.classList.contains('merge-select') ||
                        e.target.tagName === 'SELECT' ||
                        e.target.tagName === 'I') {
                        return;
                    }

                    checkbox.checked = !checkbox.checked;
                    printColumns[displayIndex].selected = checkbox.checked;
                });

                checkbox.addEventListener('change', function() {
                    printColumns[displayIndex].selected = this.checked;
                });
            }
        });

        setupDragAndDrop();
        setupMergeSelects();
    }

    function setupDragAndDrop() {
        const tableBody = document.querySelector('#printColumnModal #table-body');
        if (!tableBody) return;

        const rows = Array.from(tableBody.children);

        rows.forEach((row) => {
            const dragHandle = row.querySelector('.drag-handle');
            if (!dragHandle) return;

            dragHandle.setAttribute('draggable', 'true');

            dragHandle.addEventListener('dragstart', function(e) {
                draggedRow = row;
                row.style.opacity = '0.5';
                e.dataTransfer.effectAllowed = 'move';
            });

            dragHandle.addEventListener('dragend', function() {
                row.style.opacity = '1';
                draggedRow = null;
            });

            row.addEventListener('dragover', function(e) {
                if (draggedRow && draggedRow !== row) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    this.style.borderTop = '2px solid var(--primary-color)';
                }
            });

            row.addEventListener('dragleave', function() {
                this.style.borderTop = '';
            });

            row.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.borderTop = '';

                if (!draggedRow || draggedRow === row) return;

                const fromIndex = Array.from(tableBody.children).indexOf(draggedRow);
                const toIndex = Array.from(tableBody.children).indexOf(row);

                const [movedColumn] = printColumns.splice(fromIndex, 1);
                printColumns.splice(toIndex, 0, movedColumn);

                updateTableBodyOnly();
            });
        });
    }

    function setupMergeSelects() {
        const mergeSelects = document.querySelectorAll('.merge-select');

        mergeSelects.forEach(select => {
            const displayIndex = parseInt(select.dataset.displayIndex);

            select.addEventListener('change', function() {
                const mergeWithIndex = this.value ? parseInt(this.value) : null;
                printColumns[displayIndex].mergeWith = mergeWithIndex;
            });
        });
    }

    window.resetColumnOrder = function() {
        printColumns.sort((a, b) => a.originalIndex - b.originalIndex);

        printColumns.forEach(col => {
            col.mergeWith = null;
            col.selected = true;
        });

        updateTableBodyOnly();
    };

    window.selectAllPrintColumns = function(select) {
        printColumns.forEach(col => {
            col.selected = select;
        });

        const checkboxes = document.querySelectorAll('#printColumnModal #table-body .row-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = select;
        });
    };

    window.printWithSelectedColumns = function() {
        const selectedColumns = printColumns.filter(col => col.selected);

        if (selectedColumns.length === 0) {
            appAlert('Please select at least one column');
            return;
        }

        closeModal('printColumnModal');
        executePrintWithColumns(selectedColumns);
    };

    function executePrintWithColumns(selectedColumns) {
        const preview = document.querySelector('.container-parent');
        let clone = preview.cloneNode(true);

        let oldIframe = document.getElementById('printIframe');
        if (oldIframe) oldIframe.remove();

        let printIframe = document.createElement('iframe');
        printIframe.id = 'printIframe';
        printIframe.style.position = 'absolute';
        printIframe.style.width = '0px';
        printIframe.style.height = '0px';
        printIframe.style.border = 'none';
        printIframe.style.display = 'none';

        document.body.appendChild(printIframe);

        let printDocument = printIframe.contentDocument || printIframe.contentWindow.document;
        printDocument.open();

        const headContent = document.head.innerHTML;
        clone.querySelector('#calc-bottom')?.remove();

        function generatePrintBody(clone) {
            const header = clone.querySelector('#table-head');
            const body = clone.querySelector('.search_container');
            if (!header || !body) return clone.innerHTML;

            const processedColumns = [];
            const mergedIndices = new Set();

            selectedColumns.forEach((col, idx) => {
                if (mergedIndices.has(idx)) return;

                if (col.mergeWith !== null) {
                    const mergeTargetCol = selectedColumns[col.mergeWith];
                    if (mergeTargetCol && !mergedIndices.has(col.mergeWith)) {
                        processedColumns.push({
                            originalIndices: [col.originalIndex, mergeTargetCol.originalIndex],
                            text: `${col.text} / ${mergeTargetCol.text}`,
                            isMerged: true
                        });
                        mergedIndices.add(idx);
                        mergedIndices.add(col.mergeWith);
                    } else {
                        processedColumns.push({
                            originalIndices: [col.originalIndex],
                            text: col.text,
                            isMerged: false
                        });
                        mergedIndices.add(idx);
                    }
                } else {
                    processedColumns.push({
                        originalIndices: [col.originalIndex],
                        text: col.text,
                        isMerged: false
                    });
                    mergedIndices.add(idx);
                }
            });

            const totalColumns = processedColumns.length;
            const columnWidth = `${(100 / totalColumns).toFixed(2)}%`;

            const headerCols = Array.from(header.children);
            const filteredHeaderCols = processedColumns.map(col => {
                const headerDiv = document.createElement('div');
                headerDiv.className = 'truncate';
                headerDiv.style.width = columnWidth;
                headerDiv.style.minWidth = columnWidth;
                headerDiv.style.maxWidth = columnWidth;
                headerDiv.style.flex = `0 0 ${columnWidth}`;
                headerDiv.textContent = col.text;
                return headerDiv.outerHTML;
            });

            const headerHTML = `<div id="table-head" class="flex items-center bg-[var(--h-bg-color)] rounded-lg font-medium py-2 text-center px-4">
                ${filteredHeaderCols.join('')}
            </div>`;

            body.innerHTML = body.innerHTML
                .replaceAll('fade-in', '')
                .replaceAll('my-scrollbar-2', 'scrollbar-hidden');

            const rows = Array.from(body.children).map(r => {
                const rowClone = r.cloneNode(true);
                rowClone.removeAttribute('data-json');
                rowClone.removeAttribute('onclick');
                rowClone.removeAttribute('oncontextmenu');

                const spans = Array.from(rowClone.querySelectorAll('span'));

                const filteredSpans = processedColumns.map(col => {
                    const spanDiv = document.createElement('span');
                    spanDiv.style.width = columnWidth;
                    spanDiv.style.minWidth = columnWidth;
                    spanDiv.style.maxWidth = columnWidth;
                    spanDiv.style.flex = `0 0 ${columnWidth}`;

                    if (col.isMerged) {
                        const texts = col.originalIndices.map(idx => spans[idx]?.textContent || '').filter(Boolean);
                        spanDiv.textContent = texts.join(' / ');
                    } else {
                        const span = spans[col.originalIndices[0]];
                        if (span) {
                            spanDiv.textContent = span.textContent;
                        }
                    }

                    return spanDiv;
                });

                rowClone.innerHTML = '';
                filteredSpans.forEach(span => rowClone.appendChild(span));

                return rowClone;
            });

            let html = '';
            let currentRows = [];
            let height = 0;
            const maxHeight = 840;

            rows.forEach((r, i) => {
                currentRows.push(r.outerHTML);
                height += r.scrollHeight || 40;

                if (height >= maxHeight || i === rows.length - 1) {
                    html += `
                        <div class="print-page flex flex-col min-h-[750px]">
                            <div class="px-4 w-full flex justify-between text-[12px] font-medium tracking-wide leading-none mb-2">
                                <div class="capitalize">${document.getElementById('page-name')?.textContent || ''} | ${window.__clientCompanyName || ''}</div>
                                <div>Printed on: ${formatDate(new Date())}</div>
                            </div>
                            ${headerHTML}
                            <div class="rows px-4 text-center">
                                ${currentRows.join('')}
                            </div>
                            <div class="grow"></div>
                            <div class="px-4 w-full grid grid-cols-3 text-[12px] tracking-wide leading-none mt-3">
                                <div class="text-left">Showing ${i + 1} of ${rows.length} Records</div>
                                <div class="text-center">Powered by: <strong>SparkPair</strong></div>
                                <div class="text-right">Page ${Math.ceil((i + 1) / (maxHeight / 40))} of ${Math.ceil(rows.length / (maxHeight / 40))}</div>
                            </div>
                        </div>
                    `;
                    if (i !== rows.length - 1)
                        html += `<div style="page-break-after:always"></div>`;

                    currentRows = [];
                    height = 0;
                }
            });

            return html;
        }

        printDocument.write(`
            <html>
                <head>
                    <title>Print Statement</title>
                    ${headContent}
                    <style>
                        @page {
                            size: A4 landscape;
                            margin: 16px;
                        }
                        body {
                            margin: 0;
                            padding: 0;
                            background: #fff;
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                        }
                        .container-parent, .card_container {
                            display: block !important;
                            overflow: visible !important;
                            height: auto !important;
                        }
                        * {
                            page-break-inside: auto;
                            box-sizing: border-box;
                        }
                        .row, .record, tr, .card {
                            page-break-inside: avoid;
                            break-inside: avoid;
                        }
                        thead { display: table-header-group; }
                        .scrollbar-hidden { overflow: visible !important; }

                        body #table-head {
                            color: white !important;
                            background: var(--primary-color) !important;
                            font-size: 10px !important;
                            display: flex !important;
                        }
                        body #table-head > div {
                            flex-shrink: 0;
                            flex-grow: 0;
                            text-align: center;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                        }
                        body .row {
                            display: flex !important;
                            border-bottom: 1px solid #e5e7eb;
                            padding: 8px 0;
                        }
                        body .row span {
                            color: black !important;
                            font-size: 10px !important;
                            flex-shrink: 0;
                            flex-grow: 0;
                            text-align: center;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            white-space: nowrap;
                        }
                    </style>
                </head>
                <body>
                    ${generatePrintBody(clone)}
                </body>
            </html>
        `);

        printDocument.close();

        printIframe.onload = () => {
            printIframe.contentWindow.focus();
            printIframe.contentWindow.print();
        };
    }

    window.printPage = function() {
        window.openPrintColumnModal();
    };

    if (typeof originalPrintPage === 'function') {
        window.__originalPrintPage = originalPrintPage;
    }
})();
