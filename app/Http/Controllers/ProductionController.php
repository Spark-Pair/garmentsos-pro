<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Employee;
use App\Models\Fabric;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Production;
use App\Models\Rate;
use App\Models\ReturnFabric;
use App\Models\Setup;
use App\Models\Supplier;
use App\Services\Branches\BranchSerialService;
use App\Services\Branches\ModuleBranchService;
use App\Services\Production\ProductionItemSyncService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ProductionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper', 'supplier'])) {
            return $resp;
        }
        $authLayout = $this->getAuthLayout($request->route()->getName(), 'table');

        // $productions = Production::with('article', 'work', 'worker')->orderby('id', 'desc')->get();

        if ($request->ajax()) {
            $branches = app(ModuleBranchService::class);
            $relations = ['article', 'work', 'worker', 'creator'];
            if (app(ProductionItemSyncService::class)->tablesReady()) {
                $relations[] = 'productionTags';
                $relations[] = 'productionMaterials.inventoryItem';
            }

            $productionsQuery = $branches
                ->applyScope(Production::with($relations)->orderByDesc('id'), 'productions');

            if ($this->isSupplierRole()) {
                $supplier = $this->currentSupplier();
                if (!$supplier) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Supplier account not linked with this user.',
                    ], 403);
                }
                $productionsQuery->where('supplier_id', $supplier->id);
            }

            $productions = $productionsQuery->applyFilters($request);

            return response()->json(['data' => $productions, 'authLayout' => $authLayout]);
        }

        return view('productions.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return $resp;
        }

        $ticket_options = [];
        $branches = app(ModuleBranchService::class);

        if (Auth::user()->production_type === 'issue') {
            $articles = $branches->applyRelatedScope(Article::whereHas('production.work', function($query) {
                $query->where('title', 'Cutting');
            }), 'articles', 'productions')->with('production.work')->get();
        } else {
            $cmt_work_id = Setup::where('title', 'CMT | E')->value('id') ?? 0;
            $allTickets = $branches->applyScope(Production::whereNull('receive_date'), 'productions')
                ->whereNotNull('ticket')
                ->where('work_id', '!=', $cmt_work_id)
                ->with(['article.production.work', 'work', 'worker'])
                ->orderByDesc('id')
                ->get();
            foreach ($allTickets as $ticket) {
                $ticket_options[$ticket->ticket] = [
                    'text' => $ticket->ticket,
                    'data_option' => $ticket->toArray(),
                ];
            }
            $articles = $branches->applyRelatedScope(Article::whereNotNull('fabric_type'), 'articles', 'productions')
                ->whereNotNull('category')
                ->with('production.work')
                ->get();
        }
        $articles->each->setAppends([]);
        $work_options = [];
        $workerTypes = Setup::where('type', 'worker_type')->get();
        foreach($workerTypes as $workerType) {
            $work_options[(int)$workerType->id] = [
                'text' => $workerType->title
            ];
        }
        $worker_options = [];
        $workers = $branches->applyRelatedScope(
                Employee::with(['type', 'tags'])->where('category', 'worker')->where('status', 'active'),
                'employees',
                'productions',
            )
            ->get();
        $employeePayloads = $this->employeeOptionPayloads($workers, 'productions');
        $issuedTags = $workers
            ->flatMap(fn (Employee $worker) => $worker->tags->pluck('tag'))
            ->filter()
            ->unique()
            ->values();
        $fabricsByTag = $issuedTags->isEmpty()
            ? collect()
            : Fabric::with('supplier')
                ->whereIn('tag', $issuedTags)
                ->get()
                ->keyBy('tag');
        $returnedQuantityByTag = $issuedTags->isEmpty()
            ? collect()
            : ReturnFabric::whereIn('tag', $issuedTags)
                ->selectRaw('tag, SUM(quantity) as total_quantity')
                ->groupBy('tag')
                ->pluck('total_quantity', 'tag');

        foreach($workers as $worker) {
            $employeePayload = $employeePayloads[(int) $worker->id];
            $worker['taags'] = $worker['tags']
                ->groupBy('tag')
                ->map(function ($items, $tag) use ($worker, $articles, $fabricsByTag, $returnedQuantityByTag) {
                    $fabric = $fabricsByTag->get($tag);
                    $total_return_fabric = (float) ($returnedQuantityByTag->get($tag) ?? 0);

                    $sum = $articles
                        ->flatMap->production
                        ->filter(fn($production) => $production->worker_id == $worker->id)
                        ->flatMap->tags
                        ->where('tag', $tag)
                        ->sum('quantity');

                    $availableQuantity = ($items->sum('quantity') - $sum) - $total_return_fabric;

                    if ($availableQuantity > 0) {
                        return [
                            'tag' => $tag,
                            'quantity' => $items->sum('quantity'),
                            'sumofinproductions' => $sum,
                            'returned_quantity' => $total_return_fabric,
                            'unit' => ucfirst((string) ($fabric?->unit ?? '')),
                            'available_quantity' => $availableQuantity,
                            'supplier_name' => $fabric?->supplier?->supplier_name,
                        ];
                    }

                    return null; // keeps mapping consistent
                })
                ->filter() // removes all nulls
                ->values();
            $workerPayload = $worker->makeHidden('tags')->toArray();
            $workerPayload['balance'] = $employeePayload['balance'];
            $workerPayload['balance_formatted'] = $employeePayload['balance_formatted'];

            $worker_options[(int)$worker->id] = [
                'text' => $worker->employee_name . ' | ' . $employeePayload['balance_formatted'],
                'data_option' => $workerPayload,
            ];
        }

        $rates = Rate::with('type')->get();
        $inventoryItems = collect();
        if (Schema::hasTable('inventory_items')) {
            $inventoryRecords = $branches->applyRelatedScope(
                    InventoryItem::where('is_active', true)->with('fabric'),
                    'inventory',
                    'productions',
                )
                ->orderBy('name')
                ->get();
            $inventorySums = $inventoryRecords->isEmpty()
                ? collect()
                : InventoryTransaction::whereIn('inventory_item_id', $inventoryRecords->pluck('id'))
                    ->selectRaw("
                        inventory_item_id,
                        SUM(CASE WHEN direction = 'in' THEN quantity ELSE 0 END) as in_quantity,
                        SUM(CASE WHEN direction = 'out' THEN quantity ELSE 0 END) as out_quantity
                    ")
                    ->groupBy('inventory_item_id')
                    ->get()
                    ->keyBy('inventory_item_id');
            $inventoryItems = $inventoryRecords
                ->map(fn (InventoryItem $item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'type' => $item->type,
                    'unit' => $item->unit,
                    'tag' => $item->tag,
                    'fabric' => $item->fabric?->title,
                    'stock_quantity' => (float) ($inventorySums->get($item->id)?->in_quantity ?? 0)
                        - (float) ($inventorySums->get($item->id)?->out_quantity ?? 0),
                ])
                ->values();
        }

        $branchBranding = app(ModuleBranchService::class)->documentBranding('productions');

        return view('productions.add', compact('articles', 'work_options', 'worker_options', 'rates', 'ticket_options', 'branchBranding', 'inventoryItems'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'article_id' => 'required|integer|exists:articles,id',
            'work_id' => 'required|integer|exists:setups,id',
            'worker_id' => 'required|integer|exists:employees,id',
            'tags' => 'nullable|string',
            'materials' => 'nullable|string',
            'parts' => 'nullable|string',
            'title' => 'nullable|string',
            'rate' => 'nullable|decimal:0,2|min:1',
            'amount' => 'nullable|decimal:0,2|min:1',
            'issue_date' => 'nullable|date',
            'receive_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $incomingTags = $this->decodeJsonArray($request->tags);
        $incomingMaterials = $this->decodeJsonArray($request->materials);
        $incomingParts = $this->decodeJsonArray($request->parts);

        $data = [
            'article_id' => $request->article_id,
            'work_id' => $request->work_id,
            'worker_id' => $request->worker_id,
            'tags' => null,
            'materials' => null,
            'parts' => $incomingParts,
            'title' => $request->title,
            'rate' => $request->rate,
            'amount' => $request->amount,
            'issue_date' => $request->issue_date,
            'receive_date' => $request->receive_date,
            'branch_id' => app(ModuleBranchService::class)->branchIdForCreate('productions'),
        ];
        $ticket = null;
        $production = null;

        if ($request->filled('ticket_name') && $request->ticket_name != '-- Select Ticket --') {
            $ticket = $request->ticket_name;
            $production = Production::where('ticket', $request->ticket_name)->first();
            if ($production) {
                $itemSync = app(ProductionItemSyncService::class);
                $tagsForSync = $incomingTags ?: $itemSync->tagsForPayload($production);
                $materialsForSync = $incomingMaterials ?: $itemSync->materialsForPayload($production);

                $production->update([
                    'receive_date' => $request->receive_date,
                    'tags' => null,
                    'parts' => $incomingParts ?: $production->parts,
                    'title' => $request->title,
                    'rate' => $request->rate,
                    'amount' => $request->amount,
                    'branch_id' => $production->branch_id,
                ]);

                $itemSync->sync($production->fresh(), $tagsForSync, $materialsForSync);
            }
        } else {
            if ($request->article_quantity) {
                Article::where('id', $request->article_id)->update(['quantity' => $request->article_quantity]);
            }

            $work = Setup::find($request->work_id);

            $data['ticket'] = 'TEMP';
            $production = Production::create($data);

            $workPrefix = explode('|', $work->short_title)[0];
            $ticket = app(ModuleBranchService::class)->shouldFilterRecords('productions')
                ? app(BranchSerialService::class)->nextProductionTicket($workPrefix)
                : $workPrefix . str_pad($production->id, 3, '0', STR_PAD_LEFT);
            $production->update(['ticket' => $ticket]);
            app(ProductionItemSyncService::class)->sync(
                $production->fresh(),
                $incomingTags,
                $incomingMaterials,
            );
        }

        $issueOrReceive = '';
        if ($request->issue_date) {
            $issueOrReceive = 'issue';
        } else {
            $issueOrReceive = 'receive';
        }

        if ($production) {
            $previewRelations = ['article', 'work', 'worker', 'creator'];
            if (app(ProductionItemSyncService::class)->tablesReady()) {
                $previewRelations[] = 'productionTags';
                $previewRelations[] = 'productionMaterials.inventoryItem';
            }
            $production->loadMissing($previewRelations);
        }

        return redirect()->route('productions.create')
            ->with('success', 'Production ' . $issueOrReceive . ' successfully. Ticket: ' . $ticket)
            ->with('production_ticket_preview', $production ? $this->ticketPreviewPayload($production) : null);
    }

    protected function ticketPreviewPayload(Production $production): array
    {
        return [
            'id' => $production->id,
            'ticket' => $production->ticket,
            'issue_date' => optional($production->issue_date)->format('Y-m-d') ?: (string) $production->issue_date,
            'receive_date' => optional($production->receive_date)->format('Y-m-d') ?: (string) $production->receive_date,
            'article_no' => $production->article?->article_no,
            'article' => $production->article,
            'work' => $production->work,
            'worker' => $production->worker,
            'worker_name' => $production->worker?->employee_name,
            'quantity' => $production->quantity,
            'rate' => $production->rate,
            'amount' => $production->amount,
            'title' => $production->title,
            'parts' => $production->parts,
            'tags' => app(ProductionItemSyncService::class)->tagsForPayload($production),
            'materials' => app(ProductionItemSyncService::class)->materialsForPayload($production),
            'creator' => $production->creator?->name,
            'branch_branding' => app(ModuleBranchService::class)->documentBranding('productions', $production),
        ];
    }

    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Display the specified resource.
     */
    public function show(Production $production)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($production, 'productions');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Production $production)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($production, 'productions');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Production $production)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($production, 'productions');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Production $production)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($production, 'productions');
    }
}
