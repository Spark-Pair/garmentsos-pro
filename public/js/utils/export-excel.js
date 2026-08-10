(function() {
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
            script.onload = () => typeof XLSX !== 'undefined'
                ? resolve()
                : reject(new Error('Excel export library unavailable.'));
            script.onerror = () => reject(new Error('Excel export library could not be loaded.'));
            document.head.appendChild(script);
        });

        return window.__xlsxLoading;
    }

    function columnWidth(header, rows, index, max = 60) {
        let width = String(header ?? '').length + 2;
        rows.forEach(row => {
            width = Math.max(width, String(row[index] ?? '').length + 2);
        });
        return Math.min(Math.max(width, 10), max);
    }

    window.exportPageToExcel = async function exportPageToExcel() {
        try {
            await loadXlsx();
        } catch (error) {
            appAlert(error.message || 'Excel export library failed to load.');
            return;
        }

        const tools = window.TableExportTools;
        const columns = tools?.columns?.() || [];
        const formattedRows = tools?.formattedRows?.() || [];
        const rawRows = tools?.rawRows?.() || [];
        const layoutData = tools?.reportLayoutData?.() || null;

        if (!columns.length) {
            appAlert('Table data not found for export.');
            return;
        }

        const headers = layoutData?.processedColumns?.length
            ? layoutData.processedColumns.map(column => column.text).filter(Boolean)
            : columns.map(column => column.text).filter(Boolean);

        if (!headers.length || (!formattedRows.length && !rawRows.length)) {
            appAlert('No table data available for export.');
            return;
        }

        const workbook = XLSX.utils.book_new();
        const pageTitle = document.getElementById('page-title')?.textContent?.trim() || 'Export';
        const fileName = `${tools.sanitizeFileName(pageTitle)}.xlsx`;
        const formattedSheetName = 'Formatted Data';

        if (formattedRows.length || layoutData?.rowRecords?.length) {
            const bodyRows = layoutData?.rowRecords?.length
                ? layoutData.rowRecords.map(row => row.cells)
                : formattedRows.map(row => headers.map((_, index) => row[index] ?? ''));
            const formattedData = [headers, ...bodyRows];

            if (layoutData?.hasTotals) {
                formattedData.push(layoutData.totalValues.map((value, index) => value === '' ? (index === 0 ? 'Total' : '') : value));
            }

            const formattedSheet = XLSX.utils.aoa_to_sheet(formattedData);
            formattedSheet['!cols'] = headers.map((header, index) => ({
                wch: columnWidth(header, formattedData.slice(1), index),
            }));
            XLSX.utils.book_append_sheet(workbook, formattedSheet, formattedSheetName);
        }

        if (rawRows.length) {
            const rawHeaders = Array.from(rawRows.reduce((set, row) => {
                Object.keys(row).forEach(key => set.add(key));
                return set;
            }, new Set()));

            const rawData = [
                rawHeaders,
                ...rawRows.map(row => rawHeaders.map(header => row[header] ?? '')),
            ];

            const rawSheet = XLSX.utils.aoa_to_sheet(rawData);
            rawSheet['!cols'] = rawHeaders.map((header, index) => ({
                wch: columnWidth(header, rawData.slice(1), index, 45),
            }));
            XLSX.utils.book_append_sheet(workbook, rawSheet, 'Raw Data');
        }

        XLSX.writeFile(workbook, fileName);
    };
})();
