(() => {
    function initReportsPhysicalQuantity() {
        const config = window.__reportsPhysicalQuantity || {};
        const reportUrl = config.reportUrl || "";
        let reportTypeGlobal = config.reportType || "altration";

        function getHiddenSelectValue(id) {
            return document.querySelector(`input[type="hidden"][data-for="${id}"]`)?.value || "";
        }

        function getInputValue(id) {
            return document.getElementById(id)?.value?.trim() || "";
        }

        function getDirectValue(id) {
            return document.getElementById(id)?.value || "";
        }

        function setSelectDisabledState(wrapperId, inputId, shouldDisable) {
            const wrapper = document.getElementById(wrapperId);
            const visibleInput = document.getElementById(inputId);
            const hiddenInput = document.querySelector(`input[type="hidden"][data-for="${inputId}"]`);

            if (wrapper) {
                wrapper.classList.toggle("hidden", shouldDisable);
            }

            if (visibleInput) {
                visibleInput.disabled = shouldDisable;
                if (shouldDisable) {
                    visibleInput.value = "";
                }
            }

            if (hiddenInput) {
                hiddenInput.disabled = shouldDisable;
                if (shouldDisable) {
                    hiddenInput.value = "";
                }
            }
        }

        function setInputDisabledState(wrapperId, inputId, shouldDisable) {
            const wrapper = document.getElementById(wrapperId);
            const input = document.getElementById(inputId);

            if (wrapper) {
                wrapper.classList.toggle("hidden", shouldDisable);
            }

            if (input) {
                input.disabled = shouldDisable;
                if (shouldDisable) {
                    input.value = "";
                }
            }
        }

        window.togglePhysicalQuantityMode = function togglePhysicalQuantityMode() {
            const mode = getHiddenSelectValue("mode") || "all_articles";

            setSelectDisabledState("articleFilterWrap", "article_id", mode !== "article_wise");
            setInputDisabledState("proceedByFilterWrap", "proceed_by", mode !== "proceed_by_wise");
        };

        window.setPhysicalQuantityReportType = function setPhysicalQuantityReportType(btn, reportType) {
            doHide = true;
            if (reportTypeGlobal === reportType) {
                return;
            }

            const reportTypeInput = document.getElementById("report_type");
            if (reportTypeInput) {
                reportTypeInput.value = reportType;
            }

            moveHighlight(btn, reportType);

            $.ajax({
                url: config.setTypeUrl,
                type: "POST",
                data: {
                    _token: config.csrfToken,
                    physical_quantity_report_type: reportType,
                },
                success: function () {
                    location.reload();
                },
                error: function () {
                    appAlert("Failed to update physical quantity report type.");
                },
            });
        };

        function moveHighlight(btn, reportType) {
            const highlight = document.getElementById("reportTypeHighlight");
            if (!highlight || !btn?.parentElement) {
                reportTypeGlobal = reportType;
                return;
            }

            const rect = btn.getBoundingClientRect();
            const parentRect = btn.parentElement.getBoundingClientRect();

            highlight.style.width = `${rect.width}px`;
            highlight.style.left = `${rect.left - parentRect.left - 3}px`;
            reportTypeGlobal = reportType;
        }

        function renderPreview(response) {
            const $responseHtml = $(response);
            const $previewInResponse = $responseHtml.find(".step2");

            if ($previewInResponse.length) {
                $(".step2").html($previewInResponse.html());
            }
        }

        function validateFilters() {
            const mode = getHiddenSelectValue("mode") || "all_articles";
            const articleId = getHiddenSelectValue("article_id");
            const proceedBy = getInputValue("proceed_by");

            if (mode === "article_wise" && !articleId) {
                appAlert("Please select an article.");
                return false;
            }

            if (mode === "proceed_by_wise" && !proceedBy) {
                appAlert("Please type a Proceed By value.");
                return false;
            }

            return true;
        }

        function fetchReportPreview() {
            const reportType = getDirectValue("report_type") || reportTypeGlobal || "altration";
            const mode = getHiddenSelectValue("mode") || "all_articles";
            const articleId = getHiddenSelectValue("article_id");
            const proceedBy = getInputValue("proceed_by");

            $.ajax({
                url: reportUrl,
                type: "GET",
                data: {
                    withData: 1,
                    report_type: reportType,
                    mode: mode,
                    article_id: articleId,
                    proceed_by: proceedBy,
                },
                success: function (response) {
                    renderPreview(response);
                },
                error: function (xhr, status, error) {
                    console.error("Error fetching physical quantity report:", error);
                    appAlert("Failed to generate the report preview.");
                },
            });
        }

        window.onClickOnPrintBtn = function onClickOnPrintBtn() {
            const preview = document.getElementById("preview-container");

            if (!preview) {
                appAlert("Preview not ready yet.");
                return;
            }

            const clone = preview.cloneNode(true);
            const oldIframe = document.getElementById("printIframe");

            if (oldIframe) {
                oldIframe.remove();
            }

            const printIframe = document.createElement("iframe");
            printIframe.id = "printIframe";
            printIframe.style.position = "absolute";
            printIframe.style.width = "0";
            printIframe.style.height = "0";
            printIframe.style.border = "0";
            printIframe.style.display = "none";
            document.body.appendChild(printIframe);

            const printDocument = printIframe.contentDocument || printIframe.contentWindow.document;
            printDocument.open();

            printDocument.write(`
                <html>
                <head>
                    <title>Print Physical Quantity Report</title>
                    ${document.head.innerHTML}
                    <style>
                        @page {
                            size: A4;
                            margin: 0;
                        }

                        html, body {
                            width: 210mm;
                            margin: 0;
                            padding: 0;
                            background: #fff;
                            overflow: visible !important;
                        }

                        body {
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                        }

                        #preview-container {
                            display: block !important;
                            height: auto !important;
                            min-height: auto !important;
                            overflow: visible !important;
                            position: static !important;
                        }

                        .preview-page {
                            width: 210mm;
                            height: 297mm;
                            min-height: 297mm;
                            margin: 0 auto;
                            background: #fff;
                            page-break-after: always;
                            break-after: page;
                            box-shadow: none !important;
                            overflow: hidden;
                            position: relative !important;
                        }

                        .preview-page:last-child {
                            page-break-after: auto;
                            break-after: auto;
                        }

                        .preview,
                        .preview-document {
                            height: 100% !important;
                            min-height: 100% !important;
                            overflow: hidden !important;
                        }

                        #preview,
                        #preview-document,
                        #preview-body {
                            overflow: visible !important;
                        }

                        hr {
                            box-sizing: border-box;
                        }
                    </style>
                </head>
                <body>
                    ${clone.outerHTML}
                </body>
                </html>
            `);

            printDocument.close();
            printIframe.onload = () => {
                printIframe.contentWindow.focus();
                printIframe.contentWindow.print();
            };
        };

        window.validateForNextStep = function validateForNextStep() {
            if (!validateFilters()) {
                return false;
            }

            fetchReportPreview();
            return true;
        };

        togglePhysicalQuantityMode();

        const activeBtn =
            reportTypeGlobal === "stock"
                ? document.getElementById("stockBtn")
                : document.getElementById("altrationBtn");

        if (activeBtn) {
            moveHighlight(activeBtn, reportTypeGlobal);
        }
    }

    window.initReportsPhysicalQuantity = initReportsPhysicalQuantity;

    function boot() {
        if (window.__reportsPhysicalQuantity) {
            initReportsPhysicalQuantity();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
