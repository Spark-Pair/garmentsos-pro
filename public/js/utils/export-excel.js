(function () {

    function loadXlsx() {
        if (typeof XLSX !== 'undefined') {
            return Promise.resolve();
        }

        if (window.__xlsxLoading) {
            return window.__xlsxLoading;
        }

        window.__xlsxLoading = new Promise((resolve, reject) => {
            const script = document.createElement('script');

            script.src = '/vendor/xlsx/xlsx.full.min.js';
            script.async = true;

            script.onload = () => {
                if (typeof XLSX !== 'undefined') {
                    resolve();
                } else {
                    reject(
                        new Error(
                            'Excel export library unavailable.'
                        )
                    );
                }
            };

            script.onerror = () => {
                reject(
                    new Error(
                        'Excel export library could not be loaded.'
                    )
                );
            };

            document.head.appendChild(script);
        });

        return window.__xlsxLoading;
    }


    /*
     * ------------------------------------------------------------
     * Column Width
     * ------------------------------------------------------------
     */

    function columnWidth(header, rows, index, max = 60) {
        let width =
            String(header ?? '').length + 2;

        rows.forEach(row => {
            width = Math.max(
                width,
                String(row[index] ?? '').length + 2
            );
        });

        return Math.min(
            Math.max(width, 10),
            max
        );
    }


    /*
     * ------------------------------------------------------------
     * Normalize
     * ------------------------------------------------------------
     */

    function normalize(value) {
        return String(value ?? '')
            .trim()
            .toLowerCase();
    }


    /*
     * ------------------------------------------------------------
     * Detect Date Column
     * ------------------------------------------------------------
     */

    function isDateColumn(column) {
        const key = normalize(
            column?.key ??
            column?.field ??
            column?.name ??
            column?.dataKey ??
            ''
        );

        const text = normalize(
            column?.text ??
            column?.label ??
            column?.title ??
            ''
        );

        const value = `${key} ${text}`;

        return (
            value.includes('date') ||
            value.includes('created at') ||
            value.includes('updated at') ||
            value.includes('invoice date') ||
            value.includes('payment date') ||
            value.includes('due date') ||
            value.includes('cheque date') ||
            value.includes('transaction date') ||
            value.includes('entry date') ||
            value.includes('voucher date')
        );
    }


    /*
     * ------------------------------------------------------------
     * Detect Number Column
     * ------------------------------------------------------------
     */

    function isNumberColumn(column) {
        const key = normalize(
            column?.key ??
            column?.field ??
            column?.name ??
            column?.dataKey ??
            ''
        );

        const text = normalize(
            column?.text ??
            column?.label ??
            column?.title ??
            ''
        );

        const value = `${key} ${text}`;

        return (
            value.includes('amount') ||
            value.includes('total') ||
            value.includes('balance') ||
            value.includes('debit') ||
            value.includes('credit') ||
            value.includes('price') ||
            value.includes('rate') ||
            value.includes('quantity') ||
            value.includes('qty') ||
            value.includes('discount') ||
            value.includes('tax') ||
            value.includes('paid') ||
            value.includes('payment') ||
            value.includes('received') ||
            value.includes('expense') ||
            value.includes('profit') ||
            value.includes('loss') ||
            value.includes('cost') ||
            value.includes('pending') ||
            value.includes('advance') ||
            value.includes('amount')
        );
    }


    /*
     * ------------------------------------------------------------
     * Parse Number
     *
     * Supports:
     *
     * 1,250,000.00
     * 50,000
     * -50,000
     * +50,000
     * -1,250.50
     * (5,000.00)
     * Rs 50,000
     * PKR 50,000
     * $50,000
     * ------------------------------------------------------------
     */

    function parseNumber(value) {

        if (
            value === null ||
            value === undefined ||
            value === ''
        ) {
            return null;
        }

        if (
            typeof value === 'number' &&
            Number.isFinite(value)
        ) {
            return value;
        }

        let text = String(value).trim();

        if (!text) {
            return null;
        }


        /*
         * Remove currency symbols / currency names
         */

        text = text
            .replace(/PKR/gi, '')
            .replace(/Rs\.?/gi, '')
            .replace(/\$/g, '')
            .replace(/€/g, '')
            .replace(/£/g, '')
            .trim();


        /*
         * Accounting negative:
         *
         * (1,250.00) => -1250
         */

        let negative = false;

        if (
            text.startsWith('(') &&
            text.endsWith(')')
        ) {
            negative = true;

            text = text.slice(1, -1).trim();
        }


        /*
         * Remove commas and spaces
         */

        text = text
            .replace(/,/g, '')
            .replace(/\s/g, '');


        /*
         * Supports:
         *
         * 1250
         * -1250
         * +1250
         * 1250.50
         * -1250.50
         * .50
         * -.50
         */

        if (
            !/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)$/.test(text)
        ) {
            return null;
        }


        const number = Number(text);

        if (!Number.isFinite(number)) {
            return null;
        }

        return negative
            ? -number
            : number;
    }


    /*
     * ------------------------------------------------------------
     * Parse Date
     *
     * Supports:
     *
     * 02-Sep-2026
     * 02-Sep-2026, Wed
     * 02-Sep-2026, Wednesday
     * 02 September 2026
     * 02 September 2026, Wednesday
     * 02/09/2026
     * 02-09-2026
     * 2026-09-02
     * 2026/09/02
     * 2026-09-02T00:00:00
     * ------------------------------------------------------------
     */

    function parseDate(value) {

        if (
            value === null ||
            value === undefined ||
            value === ''
        ) {
            return null;
        }


        /*
         * Already Date object
         */

        if (value instanceof Date) {
            return isNaN(value.getTime())
                ? null
                : value;
        }


        const text = String(value).trim();

        if (!text) {
            return null;
        }


        /*
         * --------------------------------------------------------
         * YYYY-MM-DD
         * YYYY/MM/DD
         * --------------------------------------------------------
         */

        let match = text.match(
            /^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/
        );

        if (match) {

            const year = Number(match[1]);
            const month = Number(match[2]);
            const day = Number(match[3]);

            const date = new Date(
                year,
                month - 1,
                day
            );

            if (
                date.getFullYear() === year &&
                date.getMonth() === month - 1 &&
                date.getDate() === day
            ) {
                return date;
            }
        }


        /*
         * --------------------------------------------------------
         * DD-MM-YYYY
         * DD/MM/YYYY
         * --------------------------------------------------------
         */

        match = text.match(
            /^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/
        );

        if (match) {

            const day = Number(match[1]);
            const month = Number(match[2]);
            const year = Number(match[3]);

            const date = new Date(
                year,
                month - 1,
                day
            );

            if (
                date.getFullYear() === year &&
                date.getMonth() === month - 1 &&
                date.getDate() === day
            ) {
                return date;
            }
        }


        /*
         * --------------------------------------------------------
         * DD-MMM-YYYY
         *
         * 02-Sep-2026
         * 02-Sep-2026, Wed
         * 02-Sep-2026, Wednesday
         * --------------------------------------------------------
         */

        match = text.match(
            /^(\d{1,2})[-\/\s]([A-Za-z]{3,9})[-\/\s](\d{4})(?:,\s*[A-Za-z]+)?$/
        );

        if (match) {

            const day = Number(match[1]);

            const monthText =
                match[2].toLowerCase();

            const year = Number(match[3]);

            const months = [
                'jan',
                'feb',
                'mar',
                'apr',
                'may',
                'jun',
                'jul',
                'aug',
                'sep',
                'oct',
                'nov',
                'dec'
            ];

            const month =
                months.findIndex(
                    month =>
                        monthText === month ||
                        monthText.startsWith(month)
                );

            if (month !== -1) {

                const date = new Date(
                    year,
                    month,
                    day
                );

                if (
                    date.getFullYear() === year &&
                    date.getMonth() === month &&
                    date.getDate() === day
                ) {
                    return date;
                }
            }
        }


        /*
         * --------------------------------------------------------
         * DD Month YYYY
         *
         * 02 September 2026
         * 02 September 2026, Wednesday
         * --------------------------------------------------------
         */

        match = text.match(
            /^(\d{1,2})\s+([A-Za-z]{3,9})\s+(\d{4})(?:,\s*[A-Za-z]+)?$/
        );

        if (match) {

            const day = Number(match[1]);

            const monthText =
                match[2].toLowerCase();

            const year = Number(match[3]);

            const months = [
                'jan',
                'feb',
                'mar',
                'apr',
                'may',
                'jun',
                'jul',
                'aug',
                'sep',
                'oct',
                'nov',
                'dec'
            ];

            const month =
                months.findIndex(
                    month =>
                        monthText === month ||
                        monthText.startsWith(month)
                );

            if (month !== -1) {

                const date = new Date(
                    year,
                    month,
                    day
                );

                if (
                    date.getFullYear() === year &&
                    date.getMonth() === month &&
                    date.getDate() === day
                ) {
                    return date;
                }
            }
        }


        /*
         * --------------------------------------------------------
         * ISO DateTime
         *
         * 2026-09-02T00:00:00
         * 2026-09-02T00:00:00.000Z
         * --------------------------------------------------------
         */

        if (
            /^\d{4}-\d{2}-\d{2}T/.test(text)
        ) {

            const date = new Date(text);

            if (!isNaN(date.getTime())) {
                return date;
            }
        }


        /*
         * --------------------------------------------------------
         * Fallback
         * --------------------------------------------------------
         */

        const fallbackDate =
            new Date(text);

        if (!isNaN(fallbackDate.getTime())) {
            return fallbackDate;
        }

        return null;
    }


    /*
     * ------------------------------------------------------------
     * Convert Value
     * ------------------------------------------------------------
     */

    function convertValue(value, column) {

        if (
            value === null ||
            value === undefined ||
            value === ''
        ) {
            return '';
        }


        /*
         * Date
         */

        if (isDateColumn(column)) {

            const date =
                parseDate(value);

            if (date) {
                return date;
            }

            return value;
        }


        /*
         * Number
         */

        if (isNumberColumn(column)) {

            const number =
                parseNumber(value);

            if (number !== null) {
                return number;
            }

            return value;
        }


        /*
         * Already numeric
         */

        if (
            typeof value === 'number' &&
            Number.isFinite(value)
        ) {
            return value;
        }

        return value;
    }


    /*
     * ------------------------------------------------------------
     * Raw Column Type Detection
     *
     * Raw data does not always have the same column object
     * structure as formatted data.
     *
     * So we create a temporary column from the raw header.
     * ------------------------------------------------------------
     */

    function getRawColumnType(header) {

        const fakeColumn = {
            key: header,
            field: header,
            name: header,
            dataKey: header,
            text: header,
            label: header,
            title: header
        };

        return {
            isDate: isDateColumn(fakeColumn),
            isNumber: isNumberColumn(fakeColumn)
        };
    }


    /*
     * ------------------------------------------------------------
     * Convert Raw Value
     *
     * IMPORTANT:
     *
     * Raw data also gets date + number conversion.
     * ------------------------------------------------------------
     */

    function convertRawValue(value, header) {

        if (
            value === null ||
            value === undefined ||
            value === ''
        ) {
            return '';
        }


        const type =
            getRawColumnType(header);


        /*
         * Date
         */

        if (type.isDate) {

            const date =
                parseDate(value);

            if (date) {
                return date;
            }
        }


        /*
         * Number
         *
         * Includes negative numbers.
         */

        if (type.isNumber) {

            const number =
                parseNumber(value);

            if (number !== null) {
                return number;
            }
        }


        /*
         * Preserve actual numeric values
         */

        if (
            typeof value === 'number' &&
            Number.isFinite(value)
        ) {
            return value;
        }

        return value;
    }


    /*
     * ------------------------------------------------------------
     * Format Excel Cells
     * ------------------------------------------------------------
     */

    function formatExcelCells(
        sheet,
        columns
    ) {

        if (!sheet['!ref']) {
            return;
        }


        const range =
            XLSX.utils.decode_range(
                sheet['!ref']
            );


        columns.forEach(
            (column, columnIndex) => {

                const dateColumn =
                    isDateColumn(column);

                const numberColumn =
                    isNumberColumn(column);


                if (
                    !dateColumn &&
                    !numberColumn
                ) {
                    return;
                }


                for (
                    let rowIndex =
                        range.s.r + 1;

                    rowIndex <= range.e.r;

                    rowIndex++
                ) {

                    const address =
                        XLSX.utils.encode_cell({
                            r: rowIndex,
                            c: columnIndex
                        });


                    const cell =
                        sheet[address];


                    if (!cell) {
                        continue;
                    }


                    /*
                     * Date
                     */

                    if (
                        dateColumn &&
                        cell.v instanceof Date
                    ) {

                        cell.t = 'd';

                        cell.z =
                            'dd-mmm-yyyy';

                        continue;
                    }


                    /*
                     * Number
                     */

                    if (
                        numberColumn &&
                        typeof cell.v === 'number' &&
                        Number.isFinite(cell.v)
                    ) {

                        cell.t = 'n';

                        cell.z =
                            '#,##0.00';
                    }
                }
            }
        );
    }


    /*
     * ------------------------------------------------------------
     * Format Raw Excel Cells
     * ------------------------------------------------------------
     */

    function formatRawExcelCells(
        sheet,
        headers
    ) {

        if (!sheet['!ref']) {
            return;
        }


        const range =
            XLSX.utils.decode_range(
                sheet['!ref']
            );


        headers.forEach(
            (header, columnIndex) => {

                const type =
                    getRawColumnType(header);


                if (
                    !type.isDate &&
                    !type.isNumber
                ) {
                    return;
                }


                for (
                    let rowIndex =
                        range.s.r + 1;

                    rowIndex <= range.e.r;

                    rowIndex++
                ) {

                    const address =
                        XLSX.utils.encode_cell({
                            r: rowIndex,
                            c: columnIndex
                        });


                    const cell =
                        sheet[address];


                    if (!cell) {
                        continue;
                    }


                    /*
                     * Date
                     */

                    if (
                        type.isDate &&
                        cell.v instanceof Date
                    ) {

                        cell.t = 'd';

                        cell.z =
                            'dd-mmm-yyyy';

                        continue;
                    }


                    /*
                     * Number
                     */

                    if (
                        type.isNumber &&
                        typeof cell.v === 'number' &&
                        Number.isFinite(cell.v)
                    ) {

                        cell.t = 'n';

                        cell.z =
                            '#,##0.00';
                    }
                }
            }
        );
    }


    /*
     * ------------------------------------------------------------
     * Build Formatted Rows
     * ------------------------------------------------------------
     */

    function buildFormattedRows(
        formattedRows,
        columns
    ) {

        return formattedRows.map(row => {

            return columns.map(
                (column, index) => {

                    const value =
                        Array.isArray(row)
                            ? row[index]
                            : '';

                    return convertValue(
                        value,
                        column
                    );
                }
            );
        });
    }


    /*
     * ------------------------------------------------------------
     * Build Raw Rows
     *
     * IMPORTANT:
     *
     * Raw data is ALSO converted.
     * ------------------------------------------------------------
     */

    function buildRawRows(rawRows) {

        if (
            !Array.isArray(rawRows) ||
            !rawRows.length
        ) {
            return null;
        }


        /*
         * Get all raw headers
         */

        const rawHeaders =
            Array.from(
                rawRows.reduce(
                    (set, row) => {

                        if (
                            row &&
                            typeof row === 'object' &&
                            !Array.isArray(row)
                        ) {

                            Object.keys(row)
                                .forEach(key => {
                                    set.add(key);
                                });
                        }

                        return set;

                    },
                    new Set()
                )
            );


        if (!rawHeaders.length) {
            return null;
        }


        /*
         * Build converted raw data
         */

        const rawData = [
            rawHeaders,

            ...rawRows.map(row => {

                return rawHeaders.map(
                    header => {

                        const value =
                            row?.[header] ?? '';

                        return convertRawValue(
                            value,
                            header
                        );
                    }
                );
            })
        ];


        return {
            headers: rawHeaders,
            data: rawData
        };
    }


    /*
     * ------------------------------------------------------------
     * Main Export Function
     * ------------------------------------------------------------
     */

    window.exportPageToExcel =
        async function exportPageToExcel() {

            /*
             * Load XLSX
             */

            try {

                await loadXlsx();

            } catch (error) {

                appAlert(
                    error.message ||
                    'Excel export library failed to load.'
                );

                return;
            }


            /*
             * Existing TableExportTools
             */

            const tools =
                window.TableExportTools;


            if (!tools) {

                appAlert(
                    'Table export tools are unavailable.'
                );

                return;
            }


            /*
             * Get data
             */

            const columns =
                tools.columns?.() || [];

            const formattedRows =
                tools.formattedRows?.() || [];

            const rawRows =
                tools.rawRows?.() || [];

            const layoutData =
                tools.reportLayoutData?.() || null;


            /*
             * Validate columns
             */

            if (!columns.length) {

                appAlert(
                    'Table data not found for export.'
                );

                return;
            }


            /*
             * Keep exact column order
             */

            const headers =
                columns.map(
                    column =>
                        column?.text ??
                        column?.label ??
                        column?.title ??
                        ''
                );


            /*
             * Validate data
             */

            if (
                !formattedRows.length &&
                !rawRows.length &&
                !layoutData?.rowRecords?.length
            ) {

                appAlert(
                    'No table data available for export.'
                );

                return;
            }


            /*
             * Create workbook
             */

            const workbook =
                XLSX.utils.book_new();


            /*
             * File name
             */

            const pageTitle =
                document
                    .getElementById('page-title')
                    ?.textContent
                    ?.trim() ||
                'Export';


            const safeFileName =
                typeof tools.sanitizeFileName ===
                'function'

                    ? tools.sanitizeFileName(
                        pageTitle
                    )

                    : pageTitle.replace(
                        /[<>:"\/\\|?*]+/g,
                        '_'
                    );


            const fileName =
                `${safeFileName || 'Export'}.xlsx`;


            /*
             * ====================================================
             * FORMATTED DATA
             * ====================================================
             */

            let bodyRows = [];


            /*
             * PRIMARY:
             *
             * formattedRows
             */

            if (
                formattedRows.length
            ) {

                bodyRows =
                    buildFormattedRows(
                        formattedRows,
                        columns
                    );
            }


            /*
             * FALLBACK:
             *
             * layoutData
             */

            else if (
                layoutData?.rowRecords?.length
            ) {

                bodyRows =
                    layoutData.rowRecords.map(
                        row => {

                            return columns.map(
                                (column, index) => {

                                    return convertValue(
                                        row?.cells?.[
                                            index
                                        ] ?? '',
                                        column
                                    );
                                }
                            );
                        }
                    );
            }


            /*
             * Create formatted sheet
             */

            if (bodyRows.length) {

                const formattedData = [
                    headers,
                    ...bodyRows
                ];


                /*
                 * Add totals
                 */

                if (
                    layoutData?.hasTotals &&
                    Array.isArray(
                        layoutData.totalValues
                    )
                ) {

                    const totalRow =
                        columns.map(
                            (column, index) => {

                                const value =
                                    layoutData
                                        .totalValues?.[
                                            index
                                        ] ?? '';


                                if (
                                    value === '' ||
                                    value === null ||
                                    value === undefined
                                ) {

                                    return index === 0
                                        ? 'Total'
                                        : '';
                                }


                                return convertValue(
                                    value,
                                    column
                                );
                            }
                        );


                    formattedData.push(
                        totalRow
                    );
                }


                /*
                 * Create worksheet
                 */

                const formattedSheet =
                    XLSX.utils.aoa_to_sheet(
                        formattedData
                    );


                /*
                 * Apply date / number formatting
                 */

                formatExcelCells(
                    formattedSheet,
                    columns
                );


                /*
                 * Column widths
                 */

                formattedSheet['!cols'] =
                    headers.map(
                        (header, index) => ({

                            wch: columnWidth(
                                header,
                                formattedData.slice(
                                    1
                                ),
                                index
                            )
                        })
                    );


                /*
                 * Freeze header
                 */

                formattedSheet['!freeze'] = {
                    xSplit: 0,
                    ySplit: 1
                };


                XLSX.utils.book_append_sheet(
                    workbook,
                    formattedSheet,
                    'Formatted Data'
                );
            }


            /*
             * ====================================================
             * RAW DATA
             * ====================================================
             */

            const rawDataResult =
                buildRawRows(rawRows);


            if (rawDataResult) {

                /*
                 * Create raw sheet
                 */

                const rawSheet =
                    XLSX.utils.aoa_to_sheet(
                        rawDataResult.data
                    );


                /*
                 * IMPORTANT:
                 *
                 * Apply Date + Number formatting
                 * to RAW DATA too.
                 */

                formatRawExcelCells(
                    rawSheet,
                    rawDataResult.headers
                );


                /*
                 * Column widths
                 */

                rawSheet['!cols'] =
                    rawDataResult.headers.map(
                        (header, index) => ({

                            wch: columnWidth(
                                header,
                                rawDataResult.data.slice(
                                    1
                                ),
                                index,
                                45
                            )
                        })
                    );


                /*
                 * Freeze header
                 */

                rawSheet['!freeze'] = {
                    xSplit: 0,
                    ySplit: 1
                };


                XLSX.utils.book_append_sheet(
                    workbook,
                    rawSheet,
                    'Raw Data'
                );
            }


            /*
             * ====================================================
             * SAFETY CHECK
             * ====================================================
             */

            if (
                !workbook.SheetNames.length
            ) {

                appAlert(
                    'No data available for export.'
                );

                return;
            }


            /*
             * ====================================================
             * WRITE FILE
             * ====================================================
             */

            XLSX.writeFile(
                workbook,
                fileName
            );
        };

})();