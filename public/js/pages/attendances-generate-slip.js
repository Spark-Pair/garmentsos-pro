(() => {
    function initAttendancesGenerateSlip() {
        const config = window.__attendancesGenerateSlip || {};
        const companyName = config.companyName || "";

        const nameDom = document.getElementById("month");

        function generateSlipPreview() {
            $.ajax({
                url: "/attendances/generate-slip",
                type: "POST",
                data: {
                    month: nameDom.value,
                },
                headers: {
                    "X-CSRF-TOKEN": $("meta[name=\"csrf-token\"]").attr("content"),
                },
                success: function (response) {
                    const preview = document.getElementById("preview");
                    preview.innerHTML = "";

                    const perPage = 4;
                    let page;

                    page = document.createElement("div");
                    page.className = "w-full grid grid-cols-4 gap-x-4 h-full px-4.5";
                    preview.appendChild(page);

                    response.forEach((emp) => {
                        emp.records.push({ date: `${emp.month}-31`, time: "-" });

                        const empBlock = document.createElement("div");
                        empBlock.className = "employee-block h-[210mm] flex items-center p-2";

                        empBlock.innerHTML = `
                            <div class="grow">
                                <div class="mb-1 p-1 text-center border border-gray-600 rounded-lg">
                                    <h2 class="text-lg font-bold text-gray-800 tracking-wide">${emp.employee_name}</h2>
                                    <p class="text-xs text-gray-600">${emp.month}</p>
                                </div>
                                <div class="overflow-x-auto font-medium">
                                    <div class="w-full border border-gray-600 text-[8px] text-gray-700 rounded-lg overflow-hidden p-1">
                                        <div class="bg-[var(--primary-color)] text-white rounded-md">
                                            <div class="grid grid-cols-2 text-center">
                                                <div class="border-r border-white py-1 px-2">Date</div>
                                                <div class="py-1 px-2">Time</div>
                                            </div>
                                        </div>
                                        <div>
                                            ${emp.records
                                                .map((r, i) => {
                                                    const dateObj = new Date(r.date);
                                                    const isSunday = dateObj.getDay() === 0;
                                                    const noTime = r.time === "-";
                                                    let rowBg = "";

                                                    if (!noTime && !isSunday) {
                                                        const timeStr = r.time.trim().toUpperCase();
                                                        const [time, modifier] = timeStr.split(" ");
                                                        const [hours, minutes] = time.split(":").map(Number);

                                                        let h = hours;
                                                        if (modifier === "PM" && h !== 12) h += 12;
                                                        if (modifier === "AM" && h === 12) h = 0;

                                                        const totalMinutes = h * 60 + minutes;
                                                        const lateThreshold = 9 * 60 + 15;

                                                        if (totalMinutes > lateThreshold) {
                                                            rowBg =
                                                                "bg-[#f8d7da] text-[#58151c] font-semibold";
                                                        }
                                                    }

                                                    if (noTime) {
                                                        rowBg = "bg-[#fff3cd] text-[#5f4400] font-semibold";
                                                    }

                                                    if (isSunday) {
                                                        rowBg = "bg-[#cfe2ff] text-[#002b5c] font-semibold";
                                                    }

                                                    return `
                                                    <div class="grid grid-cols-2 text-center ${rowBg} ${
                                                        i === emp.records.length - 1 ? "rounded-b-md" : ""
                                                    }">
                                                        <div class="border-r border-gray-600 py-1 px-2 ${
                                                            i === emp.records.length - 1
                                                                ? ""
                                                                : "border-b border-gray-600"
                                                        }">
                                                            ${formatDate(r.date)}
                                                        </div>
                                                        <div class="py-1 px-2 ${
                                                            i === emp.records.length - 1
                                                                ? ""
                                                                : "border-b border-gray-600"
                                                        }">
                                                            ${r.time}
                                                        </div>
                                                    </div>
                                                `;
                                                })
                                                .join("")}
                                        </div>
                                    </div>
                                </div>
                                <div class="text-[10px] text-gray-600 flex justify-between mt-1 leading-none tracking-wide px-2.5 pt-1 border-t border-gray-600"><p>${companyName}</p><p>SparkPair</p></div>
                            </div>
                        `;

                        page.appendChild(empBlock);
                    });
                },
            });
        }

        window.validateForNextStep = function validateForNextStep() {
            if (!nameDom.value) return false;
            generateSlipPreview();
            return true;
        };

        window.onClickOnPrintBtn = function onClickOnPrintBtn() {
            const preview = document.getElementById("preview-container");

            let clone = preview.cloneNode(true);

            clone.querySelectorAll(":scope > hr").forEach((hr) => hr.remove());

            window.DocumentPrint.printHtml({
                title: "Print Attendance Slip",
                html: clone.innerHTML,
                style: `
                    @page {
                        size: A4 landscape;
                        margin: 3mm;
                    }

                    body {
                        margin: 0;
                        padding: 0;
                        background: #fff;
                    }
                `,
            });
        };
    }

    window.initAttendancesGenerateSlip = initAttendancesGenerateSlip;

    function boot() {
        if (window.__attendancesGenerateSlip) {
            initAttendancesGenerateSlip();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
