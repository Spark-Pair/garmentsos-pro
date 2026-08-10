<style>
    /*
     * Central document preview/print styling.
     * Order, invoice, shipment, voucher, cargo and report previews should use these
     * shared preview classes instead of page-specific visual tweaks.
     */

    #preview-container .td,
    #preview-container .th,
    .preview .td,
    .preview .th,
    .preview-document .td,
    .preview-document .th {
        min-width: 0;
        overflow: hidden !important;
        text-overflow: clip !important;
        white-space: nowrap !important;
        line-height: 1.05;
    }

    #preview-container .truncate,
    .preview .truncate,
    .preview-document .truncate {
        overflow: hidden !important;
        text-overflow: clip !important;
        white-space: nowrap !important;
    }

    .gos-a5-document {
        box-sizing: border-box;
        padding: 8mm;
        font-size: 11.2px;
        line-height: 1.24;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .gos-a5-document hr {
        margin-top: 6px !important;
        margin-bottom: 6px !important;
    }

    .gos-a5-document #banner,
    .gos-a5-document .banner,
    .gos-a5-document #header,
    .gos-a5-document .header {
        padding-left: 14px !important;
        padding-right: 14px !important;
    }

    .gos-a5-document #banner,
    .gos-a5-document .banner {
        align-items: center !important;
    }

    .gos-a5-document .logo img {
        max-height: 36px !important;
        width: auto !important;
    }

    .gos-a5-document .logo .h-\[3\.50rem\] {
        height: 40px !important;
        width: 152px !important;
    }

    .gos-a5-document .logo .mt-2 {
        margin-top: 4px !important;
    }

    .gos-a5-document .text-2xl {
        font-size: 20.5px !important;
        line-height: 1.12 !important;
    }

    .gos-a5-document .text-lg {
        font-size: 14.5px !important;
        line-height: 1.15 !important;
    }

    .gos-a5-document .text-sm {
        font-size: 11.1px !important;
        line-height: 1.2 !important;
    }

    .gos-a5-document .customer {
        font-size: 14.7px !important;
        line-height: 1.15 !important;
    }

    .gos-a5-document .right,
    .gos-a5-document .date,
    .gos-a5-document .number,
    .gos-a5-document .document-number,
    .gos-a5-document .preview-copy,
    .gos-a5-document .copy,
    .gos-a5-document .person,
    .gos-a5-document .address,
    .gos-a5-document .phone {
        font-size: 11.6px !important;
        line-height: 1.22 !important;
    }

    .gos-a5-document .document-number {
        font-size: 12.8px !important;
        font-weight: 700 !important;
        line-height: 1.16 !important;
    }

    .gos-a5-document .right.space-y-1\.5 > :not([hidden]) ~ :not([hidden]),
    .gos-a5-document .left.space-y-1 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 2px !important;
    }

    .gos-a5-document .body {
        padding-left: 14px !important;
        padding-right: 14px !important;
    }

    .gos-a5-document .grid-cols-9 {
        grid-template-columns:
            minmax(28px, 0.55fr)
            minmax(56px, 1.05fr)
            minmax(0, 1.3fr)
            minmax(0, 1.7fr)
            minmax(30px, 0.65fr)
            minmax(42px, 0.78fr)
            minmax(34px, 0.64fr)
            minmax(54px, 1fr)
            minmax(64px, 1.18fr) !important;
    }

    .gos-a5-document .grid-cols-8 {
        grid-template-columns:
            minmax(28px, 0.55fr)
            minmax(56px, 1.05fr)
            minmax(0, 1.4fr)
            minmax(0, 1.7fr)
            minmax(42px, 0.8fr)
            minmax(34px, 0.65fr)
            minmax(58px, 1.05fr)
            minmax(66px, 1.2fr) !important;
    }

    .gos-a5-document .th,
    .gos-a5-document .td {
        min-width: 0;
        align-items: center;
        display: flex;
        overflow: hidden !important;
        justify-content: center;
        text-align: center;
        font-size: 10.8px !important;
        line-height: 1.22 !important;
    }

    .gos-a5-document .thead .th:nth-child(3),
    .gos-a5-document .tbody .td:nth-child(3) {
        justify-content: flex-start;
        text-align: left;
    }

    .gos-a5-document .thead .th:last-child,
    .gos-a5-document .tbody .td:last-child,
    .gos-a5-document .thead .th:nth-last-child(2),
    .gos-a5-document .tbody .td:nth-last-child(2) {
        justify-content: flex-end;
        text-align: right;
    }

    .gos-a5-document .thead .tr {
        column-gap: 3px;
        min-height: 25px;
        padding: 4px 9px !important;
    }

    .gos-a5-document .tbody .tr {
        column-gap: 3px;
        min-height: 28px;
        padding: 4px 9px !important;
    }

    .gos-a5-document .thead .tr {
        min-height: 0;
        padding: 5.5px 9px !important;
    }

    .cargo-list-document .tbody .tr {
        min-height: 0;
        padding: 5.5px 9px !important;
    }

    .gos-a5-document .tbody hr {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }

    .gos-a5-document .table.border {
        padding-bottom: 0 !important;
    }

    .gos-a5-document > .flex > .grid.grid-cols-2 {
        gap: 7px !important;
        padding-left: 14px !important;
        padding-right: 14px !important;
    }

    .gos-a5-document .total {
        border-radius: 10px !important;
        font-size: 11px !important;
        line-height: 1.2 !important;
    }

    .gos-a5-document .total > div:first-child {
        min-width: 0;
        overflow: hidden;
        padding-right: 8px;
        text-overflow: ellipsis;
    }

    .gos-a5-document .total > div:last-child {
        min-width: 70px;
        text-align: right;
    }

    .gos-a5-document .footer,
    .gos-a5-document .tfooter {
        padding-left: 14px !important;
        padding-right: 14px !important;
        font-size: 10px !important;
        line-height: 1.2 !important;
    }

    .gos-a5-document hr:has(+ .footer),
    .gos-a5-document hr:has(+ .tfooter) {
        margin-top: 6px !important;
        margin-bottom: 6px !important;
    }

    .gos-a5-document .footer:has(> :nth-child(4)),
    .gos-a5-document .tfooter:has(> :nth-child(4)) {
        align-items: end !important;
        column-gap: 12px !important;
        display: grid !important;
        grid-template-columns: minmax(0, 1fr) auto !important;
        min-height: 19px !important;
        padding-top: 2px !important;
        padding-bottom: 3px !important;
        row-gap: 2px !important;
    }

    .gos-a5-document .footer:has(> :nth-child(4)) > :nth-child(1),
    .gos-a5-document .tfooter:has(> :nth-child(4)) > :nth-child(1) {
        align-self: end;
        font-size: 8.8px !important;
        grid-column: 1;
        grid-row: 1;
        line-height: 1.08 !important;
        min-width: 0;
        text-align: left;
    }

    .gos-a5-document .footer:has(> :nth-child(4)) > :nth-child(2),
    .gos-a5-document .tfooter:has(> :nth-child(4)) > :nth-child(2) {
        align-self: end;
        grid-column: 2;
        grid-row: 1;
        font-size: 8px !important;
        line-height: 1.05 !important;
        text-align: right;
        white-space: nowrap;
    }

    .gos-a5-document .footer:has(> :nth-child(4)) > :nth-child(3),
    .gos-a5-document .tfooter:has(> :nth-child(4)) > :nth-child(3) {
        grid-column: 2;
        grid-row: 2;
        font-size: 8px !important;
        line-height: 1.05 !important;
        text-align: right;
        white-space: nowrap;
    }

    .gos-a5-document .footer:has(> :nth-child(4)) > :nth-child(4),
    .gos-a5-document .tfooter:has(> :nth-child(4)) > :nth-child(4) {
        grid-column: 1;
        grid-row: 2;
        font-size: 8px !important;
        line-height: 1.05 !important;
        text-align: left;
        white-space: nowrap;
    }

    .gos-a5-document.gos-a5-invoice {
        --gos-invoice-border: #4b5563;
        --gos-invoice-detail-bg: #f8fafc;
        --gos-invoice-muted: #374151;
        box-sizing: border-box;
        padding: 0;
        font-size: 11px;
        line-height: 1.28;
    }

    .gos-a5-invoice hr {
        border-color: var(--gos-invoice-border) !important;
        border-top-width: 1px !important;
        margin-top: 6px !important;
        margin-bottom: 6px !important;
    }

    .gos-a5-invoice #banner,
    .gos-a5-invoice .banner,
    .gos-a5-invoice #header,
    .gos-a5-invoice .header,
    .gos-a5-invoice .body,
    .gos-a5-invoice > .flex > .grid.grid-cols-2,
    .gos-a5-invoice .footer,
    .gos-a5-invoice .tfooter {
        padding-left: 8px !important;
        padding-right: 8px !important;
    }

    .gos-a5-invoice .logo img {
        max-height: 36px !important;
    }

    .gos-a5-invoice .logo .h-\[3\.50rem\] {
        height: 40px !important;
        width: 160px !important;
    }

    .gos-a5-invoice .text-2xl {
        font-size: 22px !important;
        font-weight: 700 !important;
        line-height: 1.12 !important;
    }

    .gos-a5-invoice .customer {
        font-size: 13.8px !important;
        font-weight: 700 !important;
        line-height: 1.18 !important;
    }

    .gos-a5-invoice .right,
    .gos-a5-invoice .date,
    .gos-a5-invoice .number,
    .gos-a5-invoice .document-number,
    .gos-a5-invoice .preview-copy,
    .gos-a5-invoice .copy,
    .gos-a5-invoice .person,
    .gos-a5-invoice .address,
    .gos-a5-invoice .phone {
        font-size: 10.7px !important;
        line-height: 1.22 !important;
    }

    .gos-a5-invoice .document-number {
        font-size: 12.8px !important;
        font-weight: 700 !important;
        line-height: 1.12 !important;
    }

    .gos-a5-invoice .deliver-to {
        font-size: 12px !important;
        font-weight: 700 !important;
        line-height: 1.18 !important;
    }

    .gos-a5-invoice #header .right.space-y-1\.5 > :not([hidden]) ~ :not([hidden]),
    .gos-a5-invoice .header .right.space-y-1\.5 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 0.5px !important;
    }

    .gos-a5-invoice #header .right .date,
    .gos-a5-invoice #header .right .number,
    .gos-a5-invoice #header .right .preview-copy,
    .gos-a5-invoice .header .right .date,
    .gos-a5-invoice .header .right .number,
    .gos-a5-invoice .header .right .preview-copy {
        line-height: 1.08 !important;
    }

    .gos-a5-invoice .grid-cols-9 {
        grid-template-columns:
            18px
            minmax(54px, 1fr)
            minmax(0, 2.3fr)
            28px
            30px
            30px
            45px
            55px
            54px !important;
    }

    .gos-a5-invoice .grid-cols-8 {
        grid-template-columns:
            18px
            minmax(58px, 1.1fr)
            minmax(0, 2.8fr)
            28px
            32px
            32px
            50px
            62px !important;
    }

    .gos-a5-invoice .table.border,
    .gos-a5-invoice .total {
        border-color: var(--gos-invoice-border) !important;
        border-width: 1px !important;
    }

    .gos-a5-invoice .thead .tr {
        column-gap: 0;
        min-height: 28px;
        padding: 3px 7px !important;
    }

    .gos-a5-invoice .tbody .tr {
        column-gap: 0;
        min-height: 38px;
        padding: 3px 7px !important;
    }

    .cargo-list-preview .thead .tr {
        min-height: 0;
        padding: 5.5px 7px !important;
    }

    .cargo-list-preview .tbody .tr {
        min-height: 0;
        padding: 5.5px 7px !important;
    }

    .gos-a5-invoice .th,
    .gos-a5-invoice .td {
        font-size: 11px !important;
        line-height: 1.12 !important;
    }

    .gos-a5-invoice .th {
        align-items: center !important;
        font-size: 11px !important;
        font-weight: 600 !important;
    }

    .gos-a5-invoice .td {
        align-items: center;
        font-variant-numeric: tabular-nums;
        padding-top: 0 !important;
    }

    .gos-a5-invoice .grid-cols-8 .th:first-child,
    .gos-a5-invoice .grid-cols-8 .td:first-child,
    .gos-a5-invoice .grid-cols-9 .th:first-child,
    .gos-a5-invoice .grid-cols-9 .td:first-child {
        justify-content: center;
        padding-left: 0 !important;
        padding-right: 2px !important;
        text-align: center;
    }

    .gos-a5-invoice .grid-cols-8 .th:nth-child(2),
    .gos-a5-invoice .grid-cols-8 .td:nth-child(2),
    .gos-a5-invoice .grid-cols-8 .th:nth-child(3),
    .gos-a5-invoice .grid-cols-8 .td:nth-child(3),
    .gos-a5-invoice .grid-cols-9 .th:nth-child(2),
    .gos-a5-invoice .grid-cols-9 .td:nth-child(2),
    .gos-a5-invoice .grid-cols-9 .th:nth-child(3),
    .gos-a5-invoice .grid-cols-9 .td:nth-child(3) {
        justify-content: flex-start;
        padding-left: 6px !important;
        text-align: left;
    }

    .gos-a5-invoice .grid-cols-8 .td:nth-child(6),
    .gos-a5-invoice .grid-cols-8 .td:nth-child(7),
    .gos-a5-invoice .grid-cols-8 .td:nth-child(8),
    .gos-a5-invoice .grid-cols-9 .td:nth-child(6),
    .gos-a5-invoice .grid-cols-9 .td:nth-child(7),
    .gos-a5-invoice .grid-cols-9 .td:nth-child(8),
    .gos-a5-invoice .grid-cols-9 .td:nth-child(9) {
        font-weight: 600 !important;
    }

    .gos-a5-invoice .grid-cols-8 .th:nth-child(4),
    .gos-a5-invoice .grid-cols-8 .td:nth-child(4),
    .gos-a5-invoice .grid-cols-9 .th:nth-child(4),
    .gos-a5-invoice .grid-cols-9 .td:nth-child(4),
    .gos-a5-invoice .grid-cols-9 .th:nth-child(9),
    .gos-a5-invoice .grid-cols-9 .td:nth-child(9) {
        justify-content: center;
        text-align: center;
    }

    .gos-a5-invoice .grid-cols-8 .th:nth-last-child(-n + 2),
    .gos-a5-invoice .grid-cols-8 .td:nth-last-child(-n + 2),
    .gos-a5-invoice .grid-cols-9 .th:nth-child(7),
    .gos-a5-invoice .grid-cols-9 .td:nth-child(7),
    .gos-a5-invoice .grid-cols-9 .th:nth-child(8),
    .gos-a5-invoice .grid-cols-9 .td:nth-child(8) {
        justify-content: flex-end;
        text-align: right;
    }

    .gos-a5-invoice .invoice-item-row {
        border-top: 0.5px solid #c8ced8;
    }

    .gos-a5-invoice .invoice-item-row:first-child {
        border-top: 0;
    }

    .gos-a5-invoice .invoice-item-main .td:nth-child(2) {
        white-space: normal !important;
        overflow-wrap: anywhere;
        word-break: normal;
    }

    .gos-a5-invoice .invoice-article-cell {
        align-items: flex-start !important;
        display: flex !important;
        flex-direction: column;
        gap: 2px;
        justify-content: center !important;
        min-height: 37px;
        overflow: visible !important;
        padding-left: 6px !important;
        padding-right: 4px !important;
        text-align: left !important;
        white-space: normal !important;
    }

    .gos-a5-invoice .invoice-article-code {
        color: #111827;
        font-size: 11px;
        font-weight: 700;
        line-height: 1.1;
        max-width: 100%;
        overflow: hidden;
        text-overflow: clip;
        white-space: nowrap;
    }

    .gos-a5-invoice .invoice-article-desc {
        color: #666;
        font-size: 10.4px;
        font-weight: 500;
        line-height: 1.16;
        margin-top: 0;
        max-width: 100%;
        overflow: visible;
        overflow-wrap: anywhere;
        text-overflow: clip;
        white-space: normal;
        word-break: normal;
    }

    .gos-a5-invoice .invoice-description-cell {
        align-items: center !important;
        color: #111827;
        display: flex !important;
        font-size: 11px !important;
        font-weight: 700 !important;
        justify-content: flex-start !important;
        line-height: 1.12 !important;
        overflow: visible !important;
        overflow-wrap: anywhere;
        padding-left: 6px !important;
        padding-right: 5px !important;
        text-align: left !important;
        white-space: normal !important;
        word-break: normal;
    }

    #preview-container .gos-a5-invoice .invoice-description-cell,
    .preview.gos-a5-invoice .invoice-description-cell,
    .preview-document.gos-a5-invoice .invoice-description-cell {
        overflow: visible !important;
        overflow-wrap: anywhere !important;
        text-overflow: clip !important;
        white-space: normal !important;
        word-break: normal !important;
    }

    .gos-a5-invoice .invoice-item-desc {
        display: none;
    }

    .gos-a5-invoice .invoice-item-desc span {
        display: none;
    }

    .gos-a5-invoice .total {
        font-size: 12px !important;
    }

    .gos-a5-invoice .total > div:last-child {
        font-weight: 600;
    }

    .gos-a5-invoice .total:last-child {
        font-weight: 700;
    }

    .gos-a5-invoice .total:last-child > div:last-child {
        font-weight: 700;
    }

    .gos-a5-invoice > .flex > .grid.grid-cols-2 {
        gap: 6px !important;
    }
</style>
