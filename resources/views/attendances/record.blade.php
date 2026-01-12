@extends('app')
@section('title', 'Add Physical Quantities | ' . $client_company->name)
@section('content')
    <!-- Main Content -->
    <div class="max-w-2xl mx-auto">
        <x-search-header heading="Record Attendance" link linkText="Generate Slip"
            linkHref="{{ route('attendances.generate-slip') }}" />
    </div>

    <!-- Form -->
    <form id="form" action="{{ route('attendances.store') }}" method="post"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-2xl mx-auto relative overflow-hidden">
        @csrf
        <x-form-title-bar title="Record Attendance" />

        <div>
            <x-file-upload id="inputFile" placeholder="{{ asset('images/xls_icon.png') }}"
                uploadText="Upload XLS file" class="h-50" imageSize="12" accept=".xlsx, .xls" />
        </div>

        <!-- hidden input for formatted data -->
        <input type="hidden" name="attendances" id="attendancesInput">

        <div class="w-full flex justify-end mt-4">
            <button type="submit"
                class="px-6 py-1 bg-[var(--bg-success)] border border-[var(--bg-success)] text-[var(--text-success)] font-medium text-nowrap rounded-lg hover:bg-[var(--h-bg-success)] transition-all 0.3s ease-in-out cursor-pointer">
                <i class='fas fa-save mr-1'></i> Save
            </button>
        </div>
    </form>

    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>

    <script>
        let formattedData = [];

        document.getElementById('inputFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.onload = function(event) {
                const data = new Uint8Array(event.target.result);
                const workbook = XLSX.read(data, {
                    type: 'array'
                });

                const sheetName = workbook.SheetNames[0];
                const sheet = workbook.Sheets[sheetName];

                const json = XLSX.utils.sheet_to_json(sheet, {
                    header: 1
                });

                // format the data
                formattedData = json.slice(1).map(row => ({
                    employee_name: row[2],
                    datetime: row[3],
                    state: row[4]
                }));

                // put it in hidden input as JSON string
                document.getElementById('attendancesInput').value = JSON.stringify(formattedData);
            };

            reader.readAsArrayBuffer(file);
        });

        // optional validation before submit
        document.getElementById('form').addEventListener('submit', function(e) {
            if (!formattedData.length) {
                e.preventDefault();
                alert("Please upload an attendance XLS file before saving.");
            }
        });
    </script>

    @if(session('invalid_employees'))
        <script>
            function generateInvalidEmployeesModal() {
                const invalidEmployees = @json(session('invalid_employees'));
                let cardData = [];

                if (invalidEmployees.length > 0) {
                    cardData.push(...invalidEmployees.map(employee => {
                        return {
                            name: employee,
                        };
                    }));
                }

                let modalData = {
                    id: 'invalidEmployeesModal',
                    class: 'h-[80%] w-full',
                    cards: {name: 'Invalid Employees', count: 3, data: cardData},
                }

                createModal(modalData);
            }

            generateInvalidEmployeesModal();
        </script>
    @endif
@endsection
