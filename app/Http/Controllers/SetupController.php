<?php

namespace App\Http\Controllers;

use App\Models\Setup;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SetupController extends Controller
{
    public function index(Request $request) {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        if ($request->ajax()) {
            $setups = app(ModuleBranchService::class)
                ->applyScope(Setup::orderByDesc('id'), 'setups')
                ->applyFilters($request);

            return response()->json(['data' => $setups, 'authLayout' => $authLayout]);
        }

        $canUpdate = app_menu_can('setups', ['developer', 'owner', 'admin', 'accountant', 'store_keeper'], 'update');
        $canDelete = app_menu_can('setups', ['developer', 'owner', 'admin', 'accountant', 'store_keeper'], 'delete');

        return view('setups.index', compact('authLayout', 'canUpdate', 'canDelete'));
    }
    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $branches = app(ModuleBranchService::class);
        $existingShortTitles = $branches->applyScope(Setup::query(), 'setups')
            ->whereNotNull('short_title')
            ->pluck('short_title')
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->values();

        $titlesByType = $branches->applyScope(Setup::query(), 'setups')
            ->select('type', 'title')
            ->get()
            ->groupBy('type')
            ->map(fn ($items) => $items
                ->pluck('title')
                ->map(fn ($value) => strtolower(trim((string) $value)))
                ->filter()
                ->values())
            ->toArray();

        return view('setups.add', compact('existingShortTitles', 'titlesByType'));
    }
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $shortTitle = trim((string) $request->input('short_title'));

        $request->merge([
            'title' => trim((string) $request->input('title')),
            'short_title' => $shortTitle !== '' ? $shortTitle : null,
            'type' => trim((string) $request->input('type')),
        ]);
        
        // Validation rules
        $validator = Validator::make($request->all(), [
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('setups', 'title')->where(fn ($query) => $query->where('type', $request->type)),
            ],
            'short_title' => 'nullable|string|max:255|unique:setups,short_title',
            'type' => 'required|string|max:255',
        ], [
            'title.unique' => 'Is type mein yeh title pehle se mojood hai.',
            'short_title.unique' => 'Yeh short title pehle se system mein use ho raha hai. Short title globally unique hona chahiye.',
        ]);

        // If validation fails, return with errors
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        
        Setup::create(app(ModuleBranchService::class)->assignBranchOnCreate([
            'title' => $request->title,
            'short_title' => $request->short_title,
            'type' => $request->type,
        ], 'setups'));

        return redirect()->back()->with('success', 'Setup added successfully');
    }

    public function edit(Setup $setup)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($setup, 'setups');

        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        [$existingShortTitles, $titlesByType] = $this->existingSetupValidationPayload($setup);

        return view('setups.edit', compact('setup', 'existingShortTitles', 'titlesByType'));
    }

    public function update(Request $request, Setup $setup)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($setup, 'setups');

        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $shortTitle = trim((string) $request->input('short_title'));

        $request->merge([
            'title' => trim((string) $request->input('title')),
            'short_title' => $shortTitle !== '' ? $shortTitle : null,
            'type' => trim((string) $request->input('type')),
        ]);

        $validator = Validator::make($request->all(), [
            'title' => [
                'required',
                'string',
                'max:255',
                Rule::unique('setups', 'title')
                    ->where(fn ($query) => $query->where('type', $request->type))
                    ->ignore($setup->id),
            ],
            'short_title' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('setups', 'short_title')->ignore($setup->id),
            ],
            'type' => 'required|string|max:255',
        ], [
            'title.unique' => 'Is type mein yeh title pehle se mojood hai.',
            'short_title.unique' => 'Yeh short title pehle se system mein use ho raha hai. Short title globally unique hona chahiye.',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $setup->update([
            'title' => $request->title,
            'short_title' => $request->short_title,
            'type' => $request->type,
        ]);

        return redirect()->route('setups.index')->with('success', 'Setup updated successfully');
    }

    public function destroy(Setup $setup)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($setup, 'setups');

        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $dependencies = $this->setupDependencyCounts($setup);
        if ($dependencies !== []) {
            return redirect()->back()->with('error', $this->dependencyBlockMessage('Setup', $dependencies));
        }

        $setup->delete();

        return redirect()->route('setups.index')->with('success', 'Setup deleted successfully');
    }

    private function existingSetupValidationPayload(?Setup $excluding = null): array
    {
        $query = app(ModuleBranchService::class)->applyScope(Setup::query(), 'setups');
        if ($excluding) {
            $query->whereKeyNot($excluding->id);
        }

        $existingShortTitles = (clone $query)
            ->whereNotNull('short_title')
            ->pluck('short_title')
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->values();

        $titlesByType = (clone $query)
            ->select('type', 'title')
            ->get()
            ->groupBy('type')
            ->map(fn ($items) => $items
                ->pluck('title')
                ->map(fn ($value) => strtolower(trim((string) $value)))
                ->filter()
                ->values())
            ->toArray();

        return [$existingShortTitles, $titlesByType];
    }

    private function setupDependencyCounts(Setup $setup): array
    {
        $id = $setup->id;
        $checks = [
            'customers' => ['customers', 'city_id'],
            'fabrics' => ['fabrics', 'fabric_id'],
            'employees' => ['employees', 'type_id'],
            'rates' => ['rates', 'type_id'],
            'productions' => ['productions', 'work_id'],
            'production flows' => ['production_flows', 'work_id'],
            'customer payments' => ['customer_payments', 'bank_id'],
            'bank accounts' => ['bank_accounts', 'bank_id'],
            'utility accounts by bill type' => ['utility_accounts', 'bill_type_id'],
            'utility accounts by location' => ['utility_accounts', 'location_id'],
            'inventory items' => ['inventory_items', 'fabric_id'],
        ];

        $counts = [];
        foreach ($checks as $label => [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                $count = DB::table($table)->where($column, $id)->count();
                if ($count > 0) {
                    $counts[$label] = $count;
                }
            }
        }

        if (Schema::hasTable('suppliers') && Schema::hasColumn('suppliers', 'categories_array')) {
            $supplierCount = DB::table('suppliers')
                ->whereJsonContains('categories_array', $id)
                ->count();

            if ($supplierCount > 0) {
                $counts['suppliers'] = $supplierCount;
            }
        }

        return $counts;
    }
}
