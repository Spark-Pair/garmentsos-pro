<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Setup;
use App\Models\Supplier;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InventoryController extends Controller
{
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return $resp;
        }

        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            if (!Schema::hasTable('inventory_items')) {
                return response()->json(['data' => [], 'authLayout' => $authLayout]);
            }

            $items = app(ModuleBranchService::class)
                ->applyScope(InventoryItem::with(['fabric', 'transactions'])->orderByDesc('id'), 'inventory')
                ->applyFilters($request);

            return response()->json(['data' => $items, 'authLayout' => $authLayout]);
        }

        return view('inventory.index', compact('authLayout'));
    }

    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $branches = app(ModuleBranchService::class);
        [$supplierOptions, $fabricOptions] = $this->formOptions();

        $lastRecord = Schema::hasTable('inventory_items')
            ? $branches->applyScope(InventoryItem::with(['fabric', 'transactions'])->latest(), 'inventory')->first()
            : null;

        return view('inventory.create', compact('supplierOptions', 'fabricOptions', 'lastRecord'));
    }

    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        if (!Schema::hasTable('inventory_items')) {
            return redirect()->back()
                ->withErrors(['inventory' => 'Inventory tables are not ready yet. Please run database migrations, then try again.'])
                ->withInput();
        }

        $validated = $request->validate($this->rules());

        $branchId = app(ModuleBranchService::class)->branchIdForCreate('inventory');
        $amount = $validated['amount'] ?? null;
        if ($amount === null && isset($validated['unit_price'])) {
            $amount = (float) $validated['quantity'] * (float) $validated['unit_price'];
        }

        DB::transaction(function () use ($validated, $branchId, $amount) {
            $item = InventoryItem::create([
                'branch_id' => $branchId,
                'name' => $validated['name'],
                'type' => $validated['type'],
                'unit' => $validated['unit'],
                'tag' => $validated['tag'] ?? null,
                'fabric_id' => $validated['fabric_id'] ?? null,
                'color' => $validated['color'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            InventoryTransaction::create([
                'branch_id' => $branchId,
                'inventory_item_id' => $item->id,
                'direction' => 'in',
                'date' => $validated['date'],
                'supplier_id' => $validated['supplier_id'] ?? null,
                'payment_method' => $validated['payment_method'] ?? null,
                'quantity' => $validated['quantity'],
                'unit' => $validated['unit'],
                'unit_price' => $validated['unit_price'] ?? null,
                'amount' => $amount,
                'reference_no' => $validated['reference_no'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);
        });

        return redirect()->route('inventory.create')->with('success', 'Inventory item added successfully.');
    }

    public function edit(InventoryItem $inventory)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($inventory, 'inventory');

        [$supplierOptions, $fabricOptions] = $this->formOptions();
        $stockTransaction = $inventory->transactions()
            ->where('direction', 'in')
            ->oldest('id')
            ->first();
        $lastRecord = $inventory->load(['fabric', 'transactions']);

        return view('inventory.create', compact('supplierOptions', 'fabricOptions', 'lastRecord', 'inventory', 'stockTransaction'));
    }

    public function update(Request $request, InventoryItem $inventory)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($inventory, 'inventory');

        $validated = $request->validate($this->rules());

        $amount = $validated['amount'] ?? null;
        if ($amount === null && isset($validated['unit_price'])) {
            $amount = (float) $validated['quantity'] * (float) $validated['unit_price'];
        }

        DB::transaction(function () use ($validated, $inventory, $amount) {
            $inventory->update([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'unit' => $validated['unit'],
                'tag' => $validated['tag'] ?? null,
                'fabric_id' => $validated['fabric_id'] ?? null,
                'color' => $validated['color'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $stockTransaction = $inventory->transactions()
                ->where('direction', 'in')
                ->oldest('id')
                ->first();

            if ($stockTransaction) {
                $stockTransaction->update([
                    'date' => $validated['date'],
                    'supplier_id' => $validated['supplier_id'] ?? null,
                    'payment_method' => $validated['payment_method'] ?? null,
                    'quantity' => $validated['quantity'],
                    'unit' => $validated['unit'],
                    'unit_price' => $validated['unit_price'] ?? null,
                    'amount' => $amount,
                    'reference_no' => $validated['reference_no'] ?? null,
                    'remarks' => $validated['remarks'] ?? null,
                ]);
            }
        });

        return redirect()->route('inventory.index')->with('success', 'Inventory item updated successfully.');
    }

    public function destroy(InventoryItem $inventory)
    {
        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($inventory, 'inventory');

        DB::transaction(function () use ($inventory) {
            $inventory->transactions()->delete();
            $inventory->delete();
        });

        return redirect()->route('inventory.index')->with('success', 'Inventory item deleted successfully.');
    }

    private function formOptions(): array
    {
        $branches = app(ModuleBranchService::class);
        $suppliers = $branches->applyRelatedScope(Supplier::query(), 'suppliers', 'inventory')
            ->orderBy('supplier_name')
            ->get();
        $fabrics = $branches->applyRelatedScope(Setup::where('type', 'fabric'), 'setups', 'inventory')
            ->orderBy('title')
            ->get();

        $supplierOptions = $suppliers->mapWithKeys(fn ($supplier) => [
            $supplier->id => ['text' => $supplier->supplier_name],
        ])->all();

        $fabricOptions = $fabrics->mapWithKeys(fn ($fabric) => [
            $fabric->id => ['text' => $fabric->title],
        ])->all();

        return [$supplierOptions, $fabricOptions];
    }

    private function rules(): array
    {
        return [
            'date' => 'required|date',
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'unit' => 'required|string|max:50',
            'quantity' => 'required|numeric|min:0.001',
            'unit_price' => 'nullable|numeric|min:0',
            'amount' => 'nullable|numeric|min:0',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'payment_method' => 'nullable|string|max:50',
            'fabric_id' => 'nullable|exists:setups,id',
            'tag' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'reference_no' => 'nullable|string|max:255',
            'remarks' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ];
    }
}
