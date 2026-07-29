<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\Invoice;
use App\Services\Branches\BranchSerialService;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CargoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest'])) {
            return $resp;
        }

        $authLayout = $this->getAuthLayout($request->route()->getName());
        $branches = app(ModuleBranchService::class);

        if ($request->ajax()) {
            $orders = $branches->applyScope(Cargo::orderByDesc('id'), 'cargo')
                ->applyFilters($request);

            return response()->json(['data' => $orders, 'authLayout' => $authLayout]);
        }

        // $cargos = Cargo::all();
        return view('cargos.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $branches = app(ModuleBranchService::class);

        $invoices = $branches->applyRelatedScope(Invoice::with('customer.city'), 'invoices', 'cargo')
            ->whereNotNull('shipment_no')
            ->where(function ($query) {
                $query->whereNull('cargo_name')
                    ->orWhere('cargo_name', '');
            })
            ->get()
            ->map(fn ($invoice) => $this->formatInvoiceOptionPayload($invoice))
            ->values();

        $last_cargo = new Cargo();
        $last_cargo->cargo_no = app(BranchSerialService::class)->next('cargo', Cargo::class, 'cargo_no', null, 4);

        return view('cargos.generate', compact('invoices', 'last_cargo'));
    }

    private function formatInvoiceOptionPayload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_no' => $invoice->invoice_no,
            'date' => $invoice->date?->format('Y-m-d'),
            'cotton_count' => $invoice->cotton_count,
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
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'cargo_name' => 'required|string',
            'cargo_no' => 'required|string',
            'invoices_array' => 'required|json',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::transaction(function () use ($request) {
            $branches = app(ModuleBranchService::class);
            $invoicesArray = json_decode($request->invoices_array, true) ?? [];
            $invoiceIds = collect($invoicesArray)->pluck('id')->filter()->values();

            if ($invoiceIds->isNotEmpty()) {
                Invoice::whereIn('id', $invoiceIds)->update([
                    'cargo_name' => $request->cargo_name,
                ]);
            }

            Cargo::create([
                'date' => $request->date,
                'cargo_name' => $request->cargo_name,
                'cargo_no' => $branches->shouldFilterRecords('cargo')
                    ? app(BranchSerialService::class)->next('cargo', Cargo::class, 'cargo_no', null, 4)
                    : $request->cargo_no,
                'invoices_array' => $request->invoices_array,
                'branch_id' => $branches->branchIdForCreate('cargo'),
            ]);
        });

        return redirect()->back()->with(['success' => 'Cargo List Generated Successfuly!']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Cargo $cargo)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($cargo, 'cargo');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Cargo $cargo)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($cargo, 'cargo');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cargo $cargo)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($cargo, 'cargo');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cargo $cargo)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($cargo, 'cargo');

        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        DB::transaction(function () use ($cargo) {
            $invoiceIds = collect(json_decode($cargo->invoices_array, true) ?: [])
                ->pluck('id')
                ->filter()
                ->values();

            if ($invoiceIds->isNotEmpty()) {
                Invoice::whereIn('id', $invoiceIds)->update(['cargo_name' => null]);
            }

            $cargo->delete();
        });

        return redirect()->route('cargos.index')->with('success', 'Cargo record deleted successfully.');
    }
}
