<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CargoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $authLayout = $this->getAuthLayout($request->route()->getName());

        if ($request->ajax()) {
            $orders = Cargo::orderByDesc('id')
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
        $invoices = Invoice::with('customer.city')->whereNotNull('shipment_no')->get()->filter(function ($invoice) {return !$invoice->is_in_cargo;})->values();

        $last_cargo = [];
        $last_cargo = Cargo::orderby('id', 'desc')->first();

        if (!$last_cargo) {
            $last_cargo = new Cargo();
            $last_cargo->cargo_no = '0000';
        }

        return view('cargos.generate', compact('invoices', 'last_cargo'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'cargo_name' => 'required|string',
            'cargo_no' => 'required|string',
            'invoices_array' => 'required|json',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->all();

        $invoicesArray = json_decode($data['invoices_array'], true);
        foreach ($invoicesArray as $invoice) {
            $invoiceModel = Invoice::find($invoice['id']);
            if ($invoiceModel) {
                $invoiceModel->cargo_name = $data['cargo_name'];
                $invoiceModel->save();
            }
        }

        Cargo::create($data);

        return redirect()->back()->with(['success' => 'Cargo List Generated Successfuly!']);
    }

    /**
     * Display the specified resource.
     */
    public function show(Cargo $cargo)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Cargo $cargo)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cargo $cargo)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cargo $cargo)
    {
        //
    }
}
