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
    public function create(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        if ($request->ajax()) {
            $validated = $request->validate([
                'date' => 'required|date',
                'cargo_id' => 'nullable|integer|exists:cargos,id',
            ]);

            $cargo = !empty($validated['cargo_id']) ? Cargo::find($validated['cargo_id']) : null;

            if ($cargo) {
                app(ModuleBranchService::class)->assertRecordInAllowedBranch($cargo, 'cargo');
            }

            return response()->json([
                'invoices' => $this->availableInvoiceOptions($cargo, $validated['date']),
            ]);
        }

        $invoices = collect();
        $cargo = null;
        $selectedInvoices = collect();

        $last_cargo = new Cargo();
        $last_cargo->cargo_no = app(BranchSerialService::class)->next('cargo', Cargo::class, 'cargo_no', null, 4);

        $branchBranding = app(ModuleBranchService::class)->documentBranding('cargos');

        return view('cargos.generate', compact('cargo', 'invoices', 'selectedInvoices', 'last_cargo', 'branchBranding'));
    }

    private function availableInvoiceOptions(?Cargo $cargo = null, ?string $date = null)
    {
        $branches = app(ModuleBranchService::class);
        $selectedInvoiceIds = collect(json_decode($cargo?->invoices_array, true) ?: [])
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();

        return $branches->applyRelatedScope(Invoice::with('customer.city'), 'invoices', 'cargo')
            ->whereNotNull('shipment_no')
            ->where(function ($query) use ($selectedInvoiceIds, $date) {
                // already-selected invoices: always include, regardless of date
                if ($selectedInvoiceIds->isNotEmpty()) {
                    $query->whereIn('id', $selectedInvoiceIds);
                }

                // otherwise: unassigned invoices within the date cutoff
                $query->orWhere(function ($q) use ($date) {
                    $q->where(function ($q2) {
                        $q2->whereNull('cargo_name')->orWhere('cargo_name', '');
                    })
                    ->when($date, fn ($q2) => $q2->whereDate('date', '<=', $date));
                });
            })
            ->get()
            ->map(fn ($invoice) => $this->formatInvoiceOptionPayload($invoice))
            ->values();
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

        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $invoices = $this->availableInvoiceOptions($cargo, $cargo->date?->format('Y-m-d'));
        $selectedInvoiceIds = collect(json_decode($cargo->invoices_array, true) ?: [])
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();
        $selectedInvoices = $invoices
            ->whereIn('id', $selectedInvoiceIds)
            ->sortBy(fn ($invoice) => $selectedInvoiceIds->search((int) $invoice['id']))
            ->values();
        $last_cargo = $cargo;

        $branchBranding = app(ModuleBranchService::class)->documentBranding('cargos');

        return view('cargos.generate', compact('cargo', 'invoices', 'selectedInvoices', 'last_cargo', 'branchBranding'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cargo $cargo)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($cargo, 'cargo');

        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'cargo_name' => 'required|string',
            'invoices_array' => 'required|json',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        DB::transaction(function () use ($request, $cargo) {
            $oldInvoiceIds = collect(json_decode($cargo->invoices_array, true) ?: [])
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values();
            $newInvoiceIds = collect(json_decode($request->invoices_array, true) ?: [])
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->values();

            $removedInvoiceIds = $oldInvoiceIds->diff($newInvoiceIds)->values();
            if ($removedInvoiceIds->isNotEmpty()) {
                Invoice::whereIn('id', $removedInvoiceIds)->update(['cargo_name' => null]);
            }

            if ($newInvoiceIds->isNotEmpty()) {
                Invoice::whereIn('id', $newInvoiceIds)->update([
                    'cargo_name' => $request->cargo_name,
                ]);
            }

            $cargo->update([
                'date' => $request->date,
                'cargo_name' => $request->cargo_name,
                'invoices_array' => $request->invoices_array,
            ]);
        });

        return redirect()->route('cargos.index')->with('success', 'Cargo List Updated Successfully!');
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
