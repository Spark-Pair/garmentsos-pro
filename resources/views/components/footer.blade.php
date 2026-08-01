<!-- Footer Component Start -->
<footer class="w-full bg-[var(--secondary-bg-color)] px-6 py-4 md:py-2 shadow-lg z-30 text-sm fade-in">
    <div class="container mx-auto flex justify-between items-center">
        @if (request()->is('users/create') || request()->is('suppliers/create') || request()->is('employees/create') || request()->is('articles/create') || request()->is('rates/create') || request()->is('productions/create') || request()->is('articles/*/edit') || request()->is('customers/*/edit') || request()->is('suppliers/*/edit') || request()->is('orders/*/edit') || request()->is('employees/*/edit') || request()->is('customers/create') || request()->is('orders/create') || request()->is('cr/create') || request()->is('dr/create') || request()->is('shipments/create') || request()->is('shipments/*/edit') || request()->is('invoices/create') || request()->is('invoices/*/edit') || request()->is('vouchers/create') || request()->is('vouchers/*/edit') || request()->is('cargos/create') || request()->is('inventory/create') || request()->is('reports/statement') || request()->is('reports/pending-payments') || request()->is('reports/physical-quantity') || request()->is('reports/fabric') || request()->is('attendances/generate-slip') || request()->is('statement-adjustments/create'))
            <button id="prevBtn" type="button" class="bg-[var(--h-bg-color)] text-[var(--text-color)] px-4 md:px-5 py-2 md:py-1 rounded-lg hover:scale-95 transition-all duration-300 ease-in-out flex items-center disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer" disabled>
                <i class='fas fa-angles-left mr-1'></i> <div class="bg-[var(--h-bg-color)] hidden md:block">Previous</div>
            </button>
        @endif
        <div class="flex justify-between items-center mx-auto px-8 py-3">
            <div class="md:flex hidden justify-between items-center mx-auto">
                <span class="text-center text-sm mx-3">Copyright  &copy; 2024-<span class="opacity-100" id="year">{{ now()->year }}</span> SparkPair All rights reserved.</span>
                <div class="flex justify-center mx-3 ">
                    <a href="https://wa.me/+923165825495" target="_blank" class="text-[var(--primary-color)] hover:underline">+92 316 5825495</a>
                    <span class="mx-2">|</span>
                    <a href="https://sparkpair.dev" target="_blank" class="text-[var(--primary-color)] hover:underline">sparkpair.dev</a>
                </div>
            </div>
            <div class="md:hidden flex justify-between items-center mx-auto">
                <span class="text-center text-xs mx-3">Copyright  &copy; <span class="opacity-100" id="year">{{ now()->year }}</span> SparkPair</span>
            </div>
            @if (request()->is('login'))
                <div class="flex justify-center mx-5 fixed right-0">
                    <button id="themeToggle" type="button" onclick="changeTheme()" class="text-sm text-[var(--secondary-text)] hover:text-[var(--primary-color)] cursor-pointer">
                        <i class="fas fa-moon"></i>
                    </button>
                </div>
            @endif
        </div>
        <div class="flex items-center gap-3">
            @if (request()->is('reports/statement') || request()->is('reports/pending-payments') || request()->is('reports/physical-quantity') || request()->is('reports/fabric') || request()->is('attendances/generate-slip'))
                <button id="printBtn" type="button" class="bg-[var(--success-color)] text-[#e2e8f0] px-4 md:px-5 py-2 md:py-1 rounded-lg hover:bg-[var(--h-success-color)] hover:scale-95 transition-all duration-300 ease-in-out flex items-center gap-1 hidden cursor-pointer" onclick="onClickOnPrintBtn()">
                    <i class='fas fa-print'></i> <div class="text-[#e2e8f0] hidden md:block">Print</div>
                </button>
            @endif
            @if (request()->is('invoices/create') || request()->is('orders/create') || request()->is('cargos/create'))
                <button id="printAndSaveBtn" type="button" class="bg-[var(--success-color)] text-[#e2e8f0] px-4 md:px-5 py-2 md:py-1 rounded-lg hover:bg-[var(--h-success-color)] hover:scale-95 transition-all duration-300 ease-in-out flex items-center gap-1 hidden cursor-pointer">
                    <i class='fas fa-save'></i> <div class="text-[#e2e8f0] hidden md:block">Print & Save</div>
                </button>
            @endif
            {{-- @if (request()->is('orders/create'))
                <button id="quickInvoiceBtn" class="bg-[var(--success-color)] text-[#e2e8f0] px-4 md:px-5 py-2 md:py-1 rounded-lg hover:bg-[var(--h-success-color)] hover:scale-95 transition-all duration-300 ease-in-out flex items-center gap-1 hidden cursor-pointer">
                    <i class='fas fa-receipt'></i> <div class="text-[#e2e8f0] hidden md:block">Quick Invoice</div>
                </button>
            @endif --}}
            @if (request()->is('users/create') || request()->is('suppliers/create') || request()->is('employees/create') || request()->is('articles/create') || request()->is('rates/create') || request()->is('productions/create') || request()->is('articles/*/edit') || request()->is('customers/*/edit') || request()->is('suppliers/*/edit') || request()->is('orders/*/edit') || request()->is('employees/*/edit') || request()->is('customers/create') || request()->is('orders/create') || request()->is('cr/create') || request()->is('dr/create') || request()->is('shipments/create') || request()->is('shipments/*/edit') || request()->is('invoices/create') || request()->is('invoices/*/edit') || request()->is('vouchers/create') || request()->is('vouchers/*/edit') || request()->is('cargos/create') || request()->is('inventory/create'))
                <button id="saveBtn" type="button" class="bg-[var(--success-color)] text-[#e2e8f0] px-4 md:px-5 py-2 md:py-1 rounded-lg hover:bg-[var(--h-success-color)] hover:scale-95 transition-all duration-300 ease-in-out flex items-center gap-1 hidden cursor-pointer">
                    <i class='fas fa-save'></i> <div class="text-[#e2e8f0] hidden md:block">Save</div>
                </button>
            @endif
            @if (request()->is('users/create') || request()->is('suppliers/create') || request()->is('employees/create') || request()->is('articles/create') || request()->is('rates/create') || request()->is('productions/create') || request()->is('articles/*/edit') || request()->is('customers/*/edit') || request()->is('suppliers/*/edit') || request()->is('orders/*/edit') || request()->is('employees/*/edit') || request()->is('customers/create') || request()->is('orders/create') || request()->is('cr/create') || request()->is('dr/create') || request()->is('shipments/create') || request()->is('shipments/*/edit') || request()->is('invoices/create') || request()->is('invoices/*/edit') || request()->is('vouchers/create') || request()->is('vouchers/*/edit') || request()->is('cargos/create') || request()->is('inventory/create') || request()->is('reports/statement') || request()->is('reports/pending-payments') || request()->is('reports/physical-quantity') || request()->is('reports/fabric') || request()->is('attendances/generate-slip') || request()->is('statement-adjustments/create'))
                <button id="nextBtn" type="button" class="bg-[var(--primary-color)] text-[var(--text-color)] px-4 md:px-5 py-2 md:py-1 rounded-lg hover:bg-[var(--h-primary-color)] hover:scale-95 transition-all duration-300 ease-in-out flex items-center gap-1 cursor-pointer">
                    <div class="text-[#e2e8f0] hidden md:block">Next</div> <i class='fas fa-angles-right'></i>
                </button>
            @endif
        </div>
    </div>
    <script defer src="{{ asset('js/components/footer.js') }}"></script>
    <script>
        window.__footer = {
            wizardEnabled: @json(
                request()->is('users/create') ||
                    request()->is('suppliers/create') ||
                    request()->is('employees/create') ||
                    request()->is('articles/create') ||
                    request()->is('rates/create') ||
                    request()->is('productions/create') ||
                    request()->is('articles/*/edit') ||
                    request()->is('customers/*/edit') ||
                    request()->is('suppliers/*/edit') ||
                    request()->is('orders/*/edit') ||
                    request()->is('employees/*/edit') ||
                    request()->is('customers/create') ||
                    request()->is('orders/create') ||
                    request()->is('cr/create') ||
                    request()->is('dr/create') ||
                    request()->is('shipments/create') ||
                    request()->is('shipments/*/edit') ||
                    request()->is('invoices/create') ||
                    request()->is('invoices/*/edit') ||
                    request()->is('vouchers/create') ||
                    request()->is('vouchers/*/edit') ||
                    request()->is('cargos/create') ||
                    request()->is('reports/statement') ||
                    request()->is('reports/pending-payments') ||
                    request()->is('reports/physical-quantity') ||
                    request()->is('reports/fabric') ||
                    request()->is('attendances/generate-slip') ||
                    request()->is('inventory/create')
            ),
            enableEscapeClose: @json(!request()->is('login')),
            isLogin: @json(request()->is('login')),
        };
    </script>
</footer>
<!-- Footer Component End -->
