(function() {
    function sanitizeFileName(value) {
        return String(value || 'export')
            .trim()
            .replace(/[\\/:*?"<>|]+/g, '-')
            .replace(/\s+/g, ' ')
            .slice(0, 120);
    }

    function getVisibleRows(container) {
        return Array.from(container.children).filter((row) => {
            if (!(row instanceof HTMLElement)) return false;

            const style = window.getComputedStyle(row);
            return style.display !== 'none' && style.visibility !== 'hidden';
        });
    }

    window.exportPageToExcel = function exportPageToExcel() {
        if (typeof XLSX === 'undefined') {
            appAlert('Excel export library failed to load.');
            return;
        }

        const tableHead = document.querySelector('#table-head');
        const searchContainer = document.querySelector('.search_container');

        if (!tableHead || !searchContainer) {
            appAlert('Table data not found for export.');
            return;
        }

        const headers = Array.from(tableHead.children)
            .map((cell) => cell.textContent.trim())
            .filter(Boolean);

        const rows = getVisibleRows(searchContainer)
            .map((row) => Array.from(row.querySelectorAll('span')).map((cell) => cell.textContent.trim()))
            .filter((row) => row.some(Boolean));

        if (!headers.length || !rows.length) {
            appAlert('No table data available for export.');
            return;
        }

        const worksheetData = [headers, ...rows];
        const worksheet = XLSX.utils.aoa_to_sheet(worksheetData);
        const workbook = XLSX.utils.book_new();
        const pageTitle = document.getElementById('page-title')?.textContent?.trim() || 'Export';
        const fileName = `${sanitizeFileName(pageTitle)}.xlsx`;
        const sheetName = sanitizeFileName(pageTitle).slice(0, 31) || 'Sheet1';

        XLSX.utils.book_append_sheet(workbook, worksheet, sheetName);
        XLSX.writeFile(workbook, fileName);
    };
})();
