@extends('app')
@section('title', 'Edit Invoice | ' . $client_company->name)

@section('content')
    @php
        $hideDocumentDiscount = (bool) ($branchBranding['discount_disabled'] ?? false);
        $invoiceType = 'order';
        $grossAmount = $invoice->invoiceArticles->sum(function ($line) {
            return ((int) $line->invoice_pcs) * (float) ($line->article?->sales_rate ?? 0);
        });
        $netAmount = (float) $invoice->netAmount;
        $discountPercent = $grossAmount > 0 ? max(0, round((($grossAmount - $netAmount) / $grossAmount) * 100, 2)) : 0;
    @endphp

    <div class="switch-btn-container hidden absolute top-3 md:top-17 left-3 md:left-5 z-[100]">
        <div class="switch-btn relative flex border-3 border-[var(--secondary-bg-color)] bg-[var(--secondary-bg-color)] rounded-2xl overflow-hidden">
            <div id="highlight" class="absolute h-full rounded-xl bg-[var(--bg-color)] transition-all duration-300 ease-in-out z-0"></div>

            <button id="orderBtn" type="button"
                class="relative z-10 px-3.5 md:px-5 py-1.5 md:py-2 cursor-default rounded-xl transition-colors duration-300"
                disabled>
                <div class="hidden md:block">Order</div>
                <div class="block md:hidden"><i class="fas fa-cart-shopping text-xs"></i></div>
            </button>
        </div>
    </div>

    <div class="mb-5 max-w-4xl mx-auto">
        <x-search-header heading="Edit Invoice" link linkText="Show Invoices" linkHref="{{ route('invoices.index') }}" />
        <x-progress-bar :steps="['Edit Invoice', 'Extra Details', 'Preview']" :currentStep="1" />
    </div>

    <form id="form" action="{{ route('invoices.update', ['invoice' => $invoice->id]) }}" method="post"
        class="bg-[var(--secondary-bg-color)] text-sm rounded-xl shadow-lg p-8 border border-[var(--glass-border-color)]/20 pt-14 max-w-4xl mx-auto relative overflow-hidden">
        @csrf
        @method('PUT')
        <x-form-title-bar title="Edit Invoice" />

        @if (session('error'))
            <div class="mb-4 rounded-lg border border-[var(--border-error)] bg-[var(--border-error)]/10 px-4 py-3 text-[var(--border-error)]">
                {{ session('error') }}
            </div>
        @endif

        <div class="step1 space-y-4">
            <div class="flex justify-between gap-4">
                <div class="grow">
                    <x-select label="Order Number" name="order_no" id="order_no" :options="$ordersOptions" :value="old('order_no', $invoice->order_no)" required showDefault withButton btnId="loadInvoiceOrderBtn" btnText="Load Articles" />
                </div>
            </div>
            <div id="invoiceEditSourceError" class="hidden rounded-lg border border-[var(--border-error)] bg-[var(--border-error)]/10 px-4 py-3 text-xs text-[var(--border-error)]"></div>

            <div id="article-table" class="w-full text-left text-sm">
                <div class="flex justify-between items-center bg-[var(--h-bg-color)] rounded-lg py-2 px-4 mb-4">
                    <div class="w-[5%]">#</div>
                    <div class="w-[11%]">Article</div>
                    <div class="w-[11%]">Packets</div>
                    <div class="w-[10%]">Pcs</div>
                    <div class="grow">Decs.</div>
                    <div class="w-[8%]">Pcs/Pkt.</div>
                    <div class="w-[12%] text-right">Rate/Pc</div>
                    <div class="w-[15%] text-right">Amount</div>
                    <div class="w-[15%] text-right">Action</div>
                </div>

                <div id="invoiceArticleRows" class="h-[20rem] overflow-y-auto my-scrollbar-2">
                    @foreach ($invoice->invoiceArticles as $line)
                        @php
                            $lineArticle = $line->article;
                            $pcsPerPacket = max(1, (int) ($lineArticle?->pcs_per_packet ?? 0));
                            $packets = $pcsPerPacket > 0 ? ((int) $line->invoice_pcs / $pcsPerPacket) : 0;
                            $rate = (float) ($lineArticle?->sales_rate ?? 0);
                            $amount = (int) $line->invoice_pcs * $rate;
                        @endphp
                        <div class="invoice-article-row flex justify-between items-center gap-3 border-b border-gray-600 py-3 px-4">
                            <div class="row-number w-[5%] font-semibold">{{ $loop->iteration }}</div>
                            <div class="w-[11%]">
                                <input type="hidden" name="articles[{{ $loop->index }}][id]" value="{{ $line->id }}">
                                <x-select label="" name="articles[{{ $loop->index }}][article_id]" id="article_id_{{ $line->id }}" :options="$articles" :value="old('articles.' . $loop->index . '.article_id', $line->article_id)" required onchange="refreshInvoiceEditRow(this)" />
                            </div>
                            <div class="w-[11%] px-2">
                                <span data-role="packets" class="tabular-nums">{{ rtrim(rtrim(number_format($packets, 2, '.', ''), '0'), '.') }}</span>
                            </div>
                            <div class="w-[10%]">
                                <input name="articles[{{ $loop->index }}][invoice_pcs]" id="invoice_pcs_{{ $line->id }}" type="number" value="{{ old('articles.' . $loop->index . '.invoice_pcs', $line->invoice_pcs) }}" required oninput="refreshInvoiceEditRow(this)"
                                    class="w-full bg-transparent outline-none border border-gray-600 rounded-lg px-3 py-2">
                            </div>
                            <div class="grow">
                                <input name="articles[{{ $loop->index }}][description]" id="description_{{ $line->id }}" value="{{ old('articles.' . $loop->index . '.description', $line->description) }}" oninput="refreshInvoiceEditTotals()"
                                    class="w-full bg-transparent outline-none border border-gray-600 rounded-lg px-3 py-2">
                            </div>
                            <div class="w-[8%] px-2">
                                <span data-role="pcs_per_packet" class="tabular-nums">{{ $pcsPerPacket }}</span>
                            </div>
                            <div class="w-[12%] px-2 text-right">
                                <span data-role="rate" class="tabular-nums">{{ number_format($rate, 1) }}</span>
                            </div>
                            <div class="w-[15%] px-2 text-right">
                                <span data-role="amount" class="tabular-nums">{{ number_format($amount, 1) }}</span>
                            </div>
                            <div class="w-[15%] flex justify-end">
                                <button type="button" onclick="removeInvoiceArticleRow(this)"
                                    class="h-9 w-9 rounded-lg text-[var(--border-error)] hover:bg-[var(--border-error)]/10 transition">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex w-full grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-nowrap">
                <div class="total-qty flex justify-between items-center border border-gray-600 cursor-not-allowed rounded-lg py-2 px-4 w-full">
                    <div class="grow">Total Quantity - Pcs</div>
                    <div id="totalQuantityInForm">0</div>
                </div>
                <div class="final {{ $hideDocumentDiscount ? 'hidden' : '' }} flex justify-between items-center border border-gray-600 cursor-not-allowed rounded-lg py-2 px-4 w-full">
                    <div class="grow">Gross Amount - Rs.</div>
                    <div id="totalAmountInForm">0.0</div>
                </div>
                <div class="final {{ $hideDocumentDiscount ? 'hidden' : '' }} flex justify-between items-center border border-gray-600 cursor-not-allowed rounded-lg py-2 px-4 w-full">
                    <div class="grow">Discount - %</div>
                    <div id="dicountInForm">{{ $discountPercent }}</div>
                </div>
                <div class="final flex justify-between items-center border border-gray-600 cursor-not-allowed rounded-lg py-2 px-4 w-full">
                    <div class="grow">Net Amount - Rs.</div>
                    <input type="text" name="netAmount" id="netAmountInForm" value="{{ old('netAmount', $invoice->netAmount) }}" readonly
                        class="text-right bg-transparent outline-none w-1/2 border-none" />
                </div>
            </div>
        </div>

        <div class="step2 hidden space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-input label="Invoice No" name="invoice_no" id="invoice_no" value="{{ old('invoice_no', $invoice->invoice_no) }}" required />
                <x-input label="Date" name="date" id="date" type="date" value="{{ old('date', $invoice->date?->format('Y-m-d')) }}" required />
                <x-input label="Customer" id="customer_display" value="{{ $invoice->customer?->customer_name }} | {{ $invoice->customer?->city?->title ?? '-' }}" disabled />
                <x-input label="Carton Count" name="carton_count" id="carton_count" type="number" value="{{ old('carton_count', $invoice->carton_count) }}" />
            </div>
        </div>

        <div class="step3 hidden space-y-4 text-black h-[35rem] overflow-y-auto my-scrollbar-2 bg-white rounded-md">
            <div id="preview-container" class="w-[148mm] h-[210mm] mx-auto overflow-hidden relative">
                <div id="preview" class="preview w-[148mm] h-[210mm] gos-a5-document gos-a5-invoice overflow-hidden flex flex-col">
                    <h1 class="text-[var(--border-error)] font-medium text-center mt-5">No Preview available.</h1>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('page-scripts')
<script>
    window.__invoiceEditArticles = @json($articles);
    window.__invoiceEditCustomers = @json($customers);
    window.__invoiceEditCompany = @json($branchBranding ?? $client_company);
    window.__invoiceEditDiscount = Number(@json($discountPercent));
    window.__invoiceEditCsrf = @json(csrf_token());

    function invoiceEditArticleData(row) {
        const articleInput = row.querySelector('[name$="[article_id]"]');
        const articleId = articleInput?.value;
        return window.__invoiceEditArticles?.[articleId]?.data_option || {};
    }

    function formatInvoiceEditNumber(value, decimals = 1) {
        const number = Number(value || 0);
        return number.toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
    }

    function escapeInvoiceEditHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function invoiceEditSelectedValue(id) {
        return (document.querySelector(`.dbInput[data-for="${id}"]`)?.value
            || document.getElementById(id)?.value
            || '').trim();
    }

    function setInvoiceEditHiddenValue(id, value) {
        const hidden = document.querySelector(`.dbInput[data-for="${id}"]`) || document.querySelector(`input[name="${id}"]`);
        if (hidden) {
            hidden.value = value || '';
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function showInvoiceEditSourceError(message = '') {
        const error = document.getElementById('invoiceEditSourceError');
        if (!error) {
            return;
        }

        error.textContent = message;
        error.classList.toggle('hidden', !message);
    }

    function setInvoiceEditCustomer(customer) {
        if (!customer?.id) {
            return;
        }

        const hidden = document.querySelector('.dbInput[data-for="customer_id"]');
        const visible = document.getElementById('customer_id');
        const text = customer.customer_name
            ? `${customer.customer_name} | ${customer.city?.title || customer.city || '-'}`
            : (window.__invoiceEditCustomers?.[customer.id]?.text || '');

        if (hidden) {
            hidden.value = customer.id;
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (visible && text) {
            visible.value = text;
            visible.dispatchEvent(new Event('input', { bubbles: true }));
        }

        const display = document.getElementById('customer_display');
        if (display && text) {
            display.value = text;
        }
    }

    function mergeInvoiceEditArticles(lines = []) {
        lines.forEach((line) => {
            const article = line.article || {};
            const articleId = line.article_id || article.id;
            if (!articleId || window.__invoiceEditArticles?.[articleId]) {
                return;
            }

            window.__invoiceEditArticles[articleId] = {
                text: `${article.article_no || articleId} | ${article.description || ''}`,
                data_option: article,
            };
        });
    }

    function invoiceEditArticleOptions(selectedId) {
        return Object.entries(window.__invoiceEditArticles || {}).map(([value, option]) => {
            const selected = String(value) === String(selectedId) ? 'selected' : '';
            return `<option value="${escapeInvoiceEditHtml(value)}" ${selected}>${escapeInvoiceEditHtml(option?.text || value)}</option>`;
        }).join('');
    }

    function normalizeInvoiceEditSourceLine(line, source = 'order') {
        const article = line.article || {};
        const articleId = line.article_id || article.id || line.id || '';
        const pcsPerPacket = Number(article.pcs_per_packet || window.__invoiceEditArticles?.[articleId]?.data_option?.pcs_per_packet || 1);
        const packetQuantity = Number(line.total_quantity_in_packets || line.packets || 0);
        const invoiceablePcs = packetQuantity > 0 ? packetQuantity * pcsPerPacket : 0;
        const pcs = Number(
            line.invoice_pcs
            || invoiceablePcs
            || line.ordered_pcs
            || line.shipment_pcs
            || 0
        );

        return {
            article_id: articleId,
            invoice_pcs: pcs,
            description: line.description || article.description || '',
        };
    }

    function invoiceEditRowHtml(line, index) {
        return `
            <div class="invoice-article-row flex justify-between items-center gap-3 border-b border-gray-600 py-3 px-4">
                <div class="row-number w-[5%] font-semibold">${index + 1}</div>
                <div class="w-[11%]">
                    <select name="articles[${index}][article_id]" required onchange="refreshInvoiceEditRow(this)"
                        class="w-full bg-transparent outline-none border border-gray-600 rounded-lg px-3 py-2">
                        ${invoiceEditArticleOptions(line.article_id)}
                    </select>
                </div>
                <div class="w-[11%] px-2">
                    <span data-role="packets" class="tabular-nums">0</span>
                </div>
                <div class="w-[10%]">
                    <input name="articles[${index}][invoice_pcs]" type="number" value="${escapeInvoiceEditHtml(line.invoice_pcs)}" required oninput="refreshInvoiceEditRow(this)"
                        class="w-full bg-transparent outline-none border border-gray-600 rounded-lg px-3 py-2">
                </div>
                <div class="grow">
                    <input name="articles[${index}][description]" value="${escapeInvoiceEditHtml(line.description)}" oninput="refreshInvoiceEditTotals()"
                        class="w-full bg-transparent outline-none border border-gray-600 rounded-lg px-3 py-2">
                </div>
                <div class="w-[8%] px-2">
                    <span data-role="pcs_per_packet" class="tabular-nums"></span>
                </div>
                <div class="w-[12%] px-2 text-right">
                    <span data-role="rate" class="tabular-nums">0.0</span>
                </div>
                <div class="w-[15%] px-2 text-right">
                    <span data-role="amount" class="tabular-nums">0.0</span>
                </div>
                <div class="w-[15%] flex justify-end">
                    <button type="button" onclick="removeInvoiceArticleRow(this)"
                        class="h-9 w-9 rounded-lg text-[var(--border-error)] hover:bg-[var(--border-error)]/10 transition">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
    }

    function replaceInvoiceEditRows(lines) {
        const rows = document.getElementById('invoiceArticleRows');
        const normalized = (lines || []).map((line) => normalizeInvoiceEditSourceLine(line)).filter((line) => line.article_id && line.invoice_pcs > 0);

        if (!rows || normalized.length === 0) {
            showInvoiceEditSourceError('No invoiceable articles were found for the selected document.');
            return;
        }

        rows.innerHTML = normalized.map((line, index) => invoiceEditRowHtml(line, index)).join('');
        rows.querySelectorAll('.invoice-article-row').forEach((row) => refreshInvoiceEditRow(row));
        showInvoiceEditSourceError('');
    }

    function loadInvoiceEditOrderArticles() {
        const orderNo = invoiceEditSelectedValue('order_no');
        if (!orderNo) {
            showInvoiceEditSourceError('Please select an order first.');
            return;
        }

        $.ajax({
            url: '/get-order-details',
            type: 'POST',
            data: {
                _token: window.__invoiceEditCsrf,
                order_no: orderNo,
                allow_invoiced: 1,
                current_invoice_id: @json($invoice->id),
            },
            success: function (response) {
                if (response.error) {
                    if (document.querySelectorAll('.invoice-article-row').length > 0) {
                        showInvoiceEditSourceError('');
                        if (typeof appAlert === 'function') {
                            appAlert(response.error, 'error');
                        }
                    } else {
                        showInvoiceEditSourceError(response.error);
                    }
                    return;
                }

                window.__invoiceEditDiscount = Number(response.discount || 0);
                document.getElementById('dicountInForm').textContent = window.__invoiceEditDiscount;
                mergeInvoiceEditArticles(response.articles || []);
                setInvoiceEditCustomer(response.customer);
                replaceInvoiceEditRows(response.articles || []);
            },
            error: function () {
                showInvoiceEditSourceError('Could not load order articles. Please try again.');
            },
        });
    }

    window.refreshInvoiceEditRow = function refreshInvoiceEditRow(element) {
        const row = element.closest('.invoice-article-row');
        if (!row) {
            return;
        }

        const article = invoiceEditArticleData(row);
        const pcsInput = row.querySelector('[name$="[invoice_pcs]"]');
        const pcs = Number(pcsInput?.value || 0);
        const pcsPerPacket = Number(article.pcs_per_packet || 1);
        const rate = Number(article.sales_rate || 0);
        const packets = pcsPerPacket > 0 ? pcs / pcsPerPacket : 0;
        const amount = pcs * rate;

        row.querySelector('[data-role="packets"]').textContent = Number.isInteger(packets)
            ? packets
            : packets.toFixed(2);
        row.querySelector('[data-role="pcs_per_packet"]').textContent = pcsPerPacket || '';
        row.querySelector('[data-role="rate"]').textContent = formatInvoiceEditNumber(rate);
        row.querySelector('[data-role="amount"]').textContent = formatInvoiceEditNumber(amount);
        refreshInvoiceEditTotals();
    };

    function refreshInvoiceEditTotals() {
        let totalPcs = 0;
        let grossAmount = 0;

        document.querySelectorAll('.invoice-article-row').forEach((row) => {
            const article = invoiceEditArticleData(row);
            const pcs = Number(row.querySelector('[name$="[invoice_pcs]"]')?.value || 0);
            const rate = Number(article.sales_rate || 0);
            totalPcs += pcs;
            grossAmount += pcs * rate;
        });

        const discount = Number(window.__invoiceEditDiscount || 0);
        const netAmount = Math.max(0, grossAmount - ((grossAmount * discount) / 100));

        document.getElementById('totalQuantityInForm').textContent = totalPcs;
        document.getElementById('totalAmountInForm').textContent = formatInvoiceEditNumber(grossAmount);
        document.getElementById('netAmountInForm').value = formatInvoiceEditNumber(netAmount);
        refreshInvoiceEditPreview(totalPcs, grossAmount, netAmount);
    }

    function invoiceEditCustomerText() {
        const customerId = document.getElementById('customer_id')?.value;
        return window.__invoiceEditCustomers?.[customerId]?.data_option?.customer_name
            || window.__invoiceEditCustomers?.[customerId]?.text
            || '-';
    }

    function invoiceEditRowsPreview() {
        return Array.from(document.querySelectorAll('.invoice-article-row')).map((row, index) => {
            const article = invoiceEditArticleData(row);
            const description = row.querySelector('[name$="[description]"]')?.value || article.description || '-';
            const pcs = Number(row.querySelector('[name$="[invoice_pcs]"]')?.value || 0);
            const pcsPerPacket = Number(article.pcs_per_packet || 1);
            const packets = pcsPerPacket > 0 ? pcs / pcsPerPacket : 0;
            const rate = Number(article.sales_rate || 0);
            const amount = pcs * rate;

            return `
                <div style="display:grid;grid-template-columns:8% 37% 9% 9% 9% 13% 15%;align-items:center;border-bottom:1px solid #cbd5e1;min-height:38px;padding:5px 8px;gap:4px;">
                    <div>${index + 1}</div>
                    <div style="display:flex;flex-direction:column;gap:2px;line-height:1.15;">
                        <strong style="font-size:11px;">${article.article_no || '-'}</strong>
                        <span style="font-size:10px;color:#666;">${description}</span>
                    </div>
                    <div style="text-align:center;">${pcsPerPacket || '-'}</div>
                    <div style="text-align:center;">${Number.isInteger(packets) ? packets : packets.toFixed(2)}</div>
                    <div style="text-align:center;">${pcs}</div>
                    <div style="text-align:right;">${formatInvoiceEditNumber(rate)}</div>
                    <div style="text-align:right;font-weight:700;">${formatInvoiceEditNumber(amount)}</div>
                </div>
            `;
        }).join('');
    }

    function refreshInvoiceEditPreview(totalPcs, grossAmount, netAmount) {
        const preview = document.getElementById('preview');
        if (!preview) {
            return;
        }

        const company = window.__invoiceEditCompany || {};
        const invoiceNo = document.getElementById('invoice_no')?.value || '-';
        const date = document.getElementById('date')?.value || '-';
        const orderNo = document.getElementById('order_no')?.value || '';

        preview.innerHTML = `
            <div class="p-5 h-full flex flex-col">
                <div class="flex justify-between items-start border-b border-slate-700 pb-2">
                    <div>
                        <div class="text-lg font-bold text-blue-700">${company.logo_text || company.name || 'GarmentsOS PRO'}</div>
                        <div class="text-xs text-slate-600">${company.phone_number || company.phone || ''}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-bold text-blue-700">Sales Invoice</div>
                        <div class="font-semibold">Invoice No.: ${invoiceNo}</div>
                        ${orderNo ? `<div>Order No.: ${orderNo}</div>` : ''}
                    </div>
                </div>
                <div class="flex justify-between gap-5 py-3 border-b border-slate-700 text-sm">
                    <div class="grow">
                        <div class="text-lg font-bold">M/S: ${invoiceEditCustomerText()}</div>
                    </div>
                    <div class="w-52 text-right">
                        <div>Date: ${date}</div>
                        <div>Invoice Copy: Office</div>
                        <div>Document: Sales Invoice</div>
                    </div>
                </div>
                <div class="mt-3 rounded-lg border border-slate-700 overflow-hidden text-xs">
                    <div style="display:grid;grid-template-columns:8% 37% 9% 9% 9% 13% 15%;align-items:center;background:#1d4ed8;color:white;font-weight:700;min-height:30px;padding:5px 8px;gap:4px;">
                        <div>S.#</div>
                        <div>Article</div>
                        <div style="text-align:center;">Unit</div>
                        <div style="text-align:center;">Pkts</div>
                        <div style="text-align:center;">Pcs.</div>
                        <div style="text-align:right;">Rate</div>
                        <div style="text-align:right;">Amount</div>
                    </div>
                    ${invoiceEditRowsPreview()}
                </div>
                <div class="mt-auto grid grid-cols-2 gap-2 text-sm">
                    <div class="border border-slate-700 rounded-md px-3 py-2 flex justify-between"><span>Total Quantity</span><strong>${totalPcs}</strong></div>
                    <div class="border border-slate-700 rounded-md px-3 py-2 flex justify-between"><span>Gross Amount</span><strong>${formatInvoiceEditNumber(grossAmount)}</strong></div>
                    <div class="border border-slate-700 rounded-md px-3 py-2 flex justify-between {{ $hideDocumentDiscount ? 'hidden' : '' }}"><span>Discount - %</span><strong>${window.__invoiceEditDiscount || 0}</strong></div>
                    <div class="border border-slate-700 rounded-md px-3 py-2 flex justify-between"><span>Net Amount</span><strong>${formatInvoiceEditNumber(netAmount)}</strong></div>
                </div>
            </div>
        `;
    }

    window.removeInvoiceArticleRow = function removeInvoiceArticleRow(button) {
        const rows = document.querySelectorAll('.invoice-article-row');
        if (rows.length <= 1) {
            return;
        }

        button.closest('.invoice-article-row')?.remove();
        document.querySelectorAll('.invoice-article-row').forEach((row, index) => {
            const number = row.querySelector('.row-number');
            if (number) {
                number.textContent = index + 1;
            }

            row.querySelectorAll('[name^="articles["]').forEach((input) => {
                input.name = input.name.replace(/articles\[\d+]/, `articles[${index}]`);
            });
        });
        refreshInvoiceEditTotals();
    };

    function positionInvoiceEditSwitch() {
        const activeButton = document.getElementById('orderBtn');
        const highlight = document.getElementById('highlight');
        if (!activeButton || !highlight) {
            return;
        }

        highlight.style.left = `${activeButton.offsetLeft}px`;
        highlight.style.width = `${activeButton.offsetWidth}px`;
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.invoice-article-row').forEach((row) => refreshInvoiceEditRow(row));
        ['invoice_no', 'date', 'customer_id', 'order_no'].forEach((id) => {
            document.getElementById(id)?.addEventListener('change', refreshInvoiceEditTotals);
            document.getElementById(id)?.addEventListener('input', refreshInvoiceEditTotals);
        });
        document.querySelector('.dbInput[data-for="order_no"]')?.addEventListener('change', refreshInvoiceEditTotals);
        document.getElementById('loadInvoiceOrderBtn')?.addEventListener('click', loadInvoiceEditOrderArticles);
        positionInvoiceEditSwitch();
    });
    window.addEventListener('resize', positionInvoiceEditSwitch);
</script>
@endpush
