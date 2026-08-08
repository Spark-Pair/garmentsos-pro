<?php

namespace App\Http\Controllers;

use App\Models\Bilty;
use App\Models\Invoice;
use App\Services\Branches\BranchSerialService;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BiltyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // $bilties = Bilty::with('invoice.customer.city')->get();
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            $orders = app(ModuleBranchService::class)
                ->applyScope(Bilty::with('invoice.customer.city')->orderByDesc('id'), 'bilties')
                ->applyFilters($request);

            return response()->json(['data' => $orders, 'authLayout' => $authLayout]);
        }

        return view('bilties.show', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $invoices = app(ModuleBranchService::class)
            ->applyRelatedScope(Invoice::with('customer.city'), 'invoices', 'bilties')
            ->doesntHave('bilty')
            ->get()
            ->map(fn ($invoice) => $this->formatInvoiceOptionPayload($invoice))
            ->values();

        return view("bilties.add", compact('invoices'));
    }

    private function formatInvoiceOptionPayload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'date' => $invoice->date?->format('Y-m-d'),
            'carton_count' => $invoice->carton_count,
            'cargo_name' => $invoice->cargo_name,
            'shipment_no' => $invoice->shipment_no,
            'customer' => $invoice->customer ? [
                'id' => $invoice->customer->id,
                'customer_name' => $invoice->customer->customer_name,
                'city' => [
                    'id' => $invoice->customer->city?->id,
                    'title' => $invoice->customer->city?->title,
                    'short_title' => $invoice->customer->city?->short_title,
                ],
            ] : null,
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'invoices_array' => 'required|json',
        ]);

        $invoicesArray = json_decode($request->invoices_array, true);

        // Validate that all invoices have biltyNo
        foreach ($invoicesArray as $invoice) {
            if (!isset($invoice['biltyNo'])) {
                return redirect()->back()->withInput()->with('error', 'Please enter a bilty number for every selected invoice.');
            }
        }

        // Create bilties for each invoice
        $branches = app(ModuleBranchService::class);
        foreach ($invoicesArray as $invoice) {
            $sourceInvoice = $branches->applyRelatedScope(Invoice::query(), 'invoices', 'bilties')
                ->doesntHave('bilty')
                ->find($invoice['id']);

            if (!$sourceInvoice) {
                return redirect()->back()->withInput()->with('error', 'One of the selected invoices is not available for this branch.');
            }

            Bilty::create($branches->assignBranchOnCreate([
                'date' => $request->date,
                'invoice_id' => $invoice['id'],
                'bilty_no' => app(BranchSerialService::class)->formatBranchDocumentNumber(
                    (string) $invoice['biltyNo'],
                    'bilties',
                    $branches->selectedBranchForModule('bilties')
                ),
            ], 'bilties'));

            $updateData = array_filter([
                'cargo_name' => $invoice['cargoName'] ?? null,
                'carton_count' => $invoice['cartonCount'] ?? null,
            ], fn($value) => !is_null($value));

            if (!empty($updateData)) {
                $sourceInvoice->update($updateData);
            }
        }

        return redirect()->back()->with('success', 'Bilties created successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Bilty $bilty)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($bilty, 'bilties');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Bilty $bilty)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($bilty, 'bilties');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Bilty $bilty)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($bilty, 'bilties');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bilty $bilty)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($bilty, 'bilties');

        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        DB::transaction(function () use ($bilty) {
            $bilty->delete();
        });

        return redirect()->route('bilties.index')->with('success', 'Bilty deleted successfully.');
    }
}
