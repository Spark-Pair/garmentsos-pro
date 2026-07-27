(() => {
    function initAttendancesRecord() {
        const config = window.__attendancesRecord || {};
        const invalidEmployees = config.invalidEmployees || [];

        let formattedData = [];

        document.getElementById("inputFile")?.addEventListener("change", function (e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function (event) {
                const data = new Uint8Array(event.target.result);
                const workbook = XLSX.read(data, {
                    type: "array",
                });

                const sheetName = workbook.SheetNames[0];
                const sheet = workbook.Sheets[sheetName];

                const json = XLSX.utils.sheet_to_json(sheet, {
                    header: 1,
                });

                formattedData = json.slice(1).map((row) => ({
                    employee_name: row[2],
                    datetime: row[3],
                    state: row[4],
                }));

                document.getElementById("attendancesInput").value = JSON.stringify(formattedData);
            };

            reader.readAsArrayBuffer(file);
        });

        document.getElementById("form")?.addEventListener("submit", function (e) {
            if (!formattedData.length) {
                e.preventDefault();
                appAlert("Please upload an attendance XLS file before saving.");
            }
        });

        if (invalidEmployees.length > 0) {
            const cardData = invalidEmployees.map((employee) => ({
                name: employee,
            }));

            let modalData = {
                id: "invalidEmployeesModal",
                class: "h-[80%] w-full",
                cards: { name: "Invalid Employees", count: 3, data: cardData },
            };

            createModal(modalData);
        }
    }

    window.initAttendancesRecord = initAttendancesRecord;

    function boot() {
        if (window.__attendancesRecord) {
            initAttendancesRecord();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", boot);
    } else {
        boot();
    }
})();
