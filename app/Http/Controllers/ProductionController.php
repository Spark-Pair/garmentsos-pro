<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Employee;
use App\Models\Fabric;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Production;
use App\Models\Rate;
use App\Models\Setup;
use App\Models\Supplier;
use App\Services\Branches\BranchSerialService;
use App\Services\Branches\ModuleBranchService;
use App\Services\Production\ProductionItemSyncService;
use App\Services\Production\ProductionFlowService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ProductionController extends Controller
{
    public function availability(Request $request, ProductionFlowService $flows)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return $resp;
        }

        $validated = $request->validate([
            'article_id' => 'required|integer|exists:articles,id',
            'work_id' => 'nullable|integer|exists:setups,id',
            'ticket' => 'nullable|string',
            'mode' => 'required|string|in:issue,receive',
        ]);

        if (!$flows->ready()) {
            return response()->json(['parts' => []]);
        }

        if ($validated['mode'] === 'receive' && !empty($validated['ticket'])) {
            $parts = $flows->receiveableByPart($validated['ticket']);
        } else {
            $work = !empty($validated['work_id']) ? Setup::find($validated['work_id']) : null;
            if ($flows->isCutting($work)) {
                $article = Article::findOrFail($validated['article_id']);
                $received = $flows->cuttingReceivedByPart((int) $article->id);
                $parts = collect(app('article')->parts[$this->articlePartKey($article)] ?? [])
                    ->mapWithKeys(fn ($part) => [$part => max(0, $flows->articleLimit($article) - (float) ($received[$part] ?? 0))])
                    ->filter(fn ($quantity) => $quantity > 0);
            } else {
                $parts = $flows->issueableByPart(
                    (int) $validated['article_id'],
                    !empty($validated['work_id']) ? (int) $validated['work_id'] : null,
                );
            }
        }

        return response()->json([
            'parts' => $parts
                ->map(fn ($quantity, $part) => ['part' => $part, 'quantity' => (float) $quantity])
                ->values(),
        ]);
    }

    public function availableWorks(Request $request, ProductionFlowService $flows)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return $resp;
        }

        $validated = $request->validate([
            'article_id' => 'required|integer|exists:articles,id',
            'mode' => 'required|string|in:issue',
        ]);

        if (!$flows->ready()) {
            return response()->json(['work_ids' => []]);
        }

        return response()->json([
            'work_ids' => $flows->issueableWorkIds((int) $validated['article_id'])->values(),
        ]);
    }

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
            if (app(ProductionFlowService::class)->ready()) {
                $relations[] = 'productionFlows';
            }

            $productionsQuery = $branches
                ->applyScope(Production::with($relations)->orderByDesc('id'), 'productions');
            if (app(ProductionFlowService::class)->ready()) {
                $productionsQuery->where(function ($query) {
                    $query
                        ->whereNotNull('issue_date')
                        ->orWhereDoesntHave('productionFlows', function ($flowQuery) {
                            $flowQuery->whereNotNull('parent_ticket');
                        });
                });
            }

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

        $flowService = app(ProductionFlowService::class);

        if (Auth::user()->production_type === 'issue') {
            $articleQuery = $branches->applyRelatedScope(
                Article::whereNotNull('fabric_type')->whereNotNull('category'),
                'articles',
                'productions'
            )->with('production.work');
            $articles = $articleQuery->get();

            if ($flowService->ready()) {
                $issueableArticleIds = $articles
                    ->filter(fn (Article $article) => $flowService->issueableWorkIds((int) $article->id)->isNotEmpty())
                    ->pluck('id')
                    ->all();
                $articles = $articles
                    ->filter(fn (Article $article) => in_array((int) $article->id, $issueableArticleIds, true))
                    ->values();
            }
        } else {
            $cmt_work_id = Setup::where('title', 'CMT | E')->value('id') ?? 0;
            $allTickets = $branches->applyScope(Production::whereNull('receive_date'), 'productions')
                ->whereNotNull('ticket')
                ->where('work_id', '!=', $cmt_work_id)
                ->with(['article.production.work', 'work', 'worker'])
                ->orderByDesc('id')
                ->get();
            if ($flowService->ready()) {
                $allTickets = $allTickets
                    ->filter(fn (Production $production) => $flowService->receiveableByPart((string) $production->ticket)->isNotEmpty())
                    ->values();
            }
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
            if ($flowService->ready()) {
                $articles = $articles
                    ->filter(function (Article $article) use ($flowService) {
                        $limit = $flowService->articleLimit($article);
                        if ($limit <= 0) {
                            return false;
                        }

                        $parts = collect(app('article')->parts[$this->articlePartKey($article)] ?? [])
                            ->filter(fn ($part) => trim((string) $part) !== '');
                        if ($parts->isEmpty()) {
                            return false;
                        }

                        $received = $flowService->cuttingReceivedByPart((int) $article->id);

                        return $parts->contains(function ($part) use ($received, $limit) {
                            return (float) ($received[$part] ?? 0) < $limit;
                        });
                    })
                    ->values();
            }
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
        $workerTagBalances = app(ProductionItemSyncService::class)->workerTagBalances($workers->pluck('id'), 'productions');
        $availableTags = $workerTagBalances
            ->flatten(1)
            ->pluck('tag')
            ->filter()
            ->unique()
            ->values();
        $fabricsByTag = $availableTags->isEmpty()
            ? collect()
            : Fabric::with('supplier')
                ->whereIn('tag', $availableTags)
                ->get()
                ->keyBy('tag');

        foreach($workers as $worker) {
            $employeePayload = $employeePayloads[(int) $worker->id];
            $worker['taags'] = $workerTagBalances
                ->get((int) $worker->id, collect())
                ->map(function (array $balance) use ($fabricsByTag) {
                    $fabric = $fabricsByTag->get($balance['tag']);

                    return [
                        'tag' => $balance['tag'],
                        'quantity' => $balance['issued_quantity'],
                        'sumofinproductions' => $balance['production_quantity'],
                        'returned_quantity' => $balance['returned_quantity'],
                        'unit' => ucfirst((string) (($balance['unit'] ?? null) ?: $fabric?->unit ?? '')),
                        'available_quantity' => $balance['available_quantity'],
                        'supplier_name' => $fabric?->supplier?->supplier_name,
                    ];
                })
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

    private function articlePartKey(Article $article): string
    {
        return $article->category . '_' . $article->season;
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
            'production_flows' => 'nullable|string',
            'title' => 'nullable|string',
            'rate' => 'nullable|decimal:0,2|min:0.01',
            'amount' => 'nullable|decimal:0,2|min:0.01',
            'issue_date' => 'nullable|date',
            'receive_date' => 'nullable|date',
            'issued_by_name' => 'nullable|string|max:120',
            'received_by_name' => 'nullable|string|max:120',
        ]);

        $validator->after(function ($validator) use ($request) {
            $isReceive = Auth::user()->production_type === 'receive';
            $hasIssueDate = $request->filled('issue_date');
            $hasReceiveDate = $request->filled('receive_date');

            if ($isReceive && !$hasReceiveDate) {
                $validator->errors()->add('receive_date', 'Receive date is required.');
            }

            if ($isReceive && !$request->filled('rate')) {
                $validator->errors()->add('rate', 'Rate is required.');
            }

            if ($isReceive && !$request->filled('amount')) {
                $validator->errors()->add('amount', 'Amount is required.');
            }

            if (!$isReceive && !$hasIssueDate) {
                $validator->errors()->add('issue_date', 'Issue date is required.');
            }

            if ($hasIssueDate && $hasReceiveDate) {
                $validator->errors()->add('issue_date', 'Use either issue date or receive date, not both.');
            }

            if ($request->filled('production_flows')) {
                $decodedFlows = json_decode((string) $request->production_flows, true);
                if (!is_array($decodedFlows)) {
                    $validator->errors()->add('production_flows', 'Selected parts data is invalid.');
                }
            }

            if ($request->filled('parts')) {
                $decodedParts = json_decode((string) $request->parts, true);
                if (!is_array($decodedParts)) {
                    $validator->errors()->add('parts', 'Selected parts are invalid.');
                }
            }
        });

        if ($validator->fails()) {
            return $this->validationBack($validator);
        }

        $incomingTags = $this->decodeJsonArray($request->tags);
        $incomingMaterials = $this->decodeJsonArray($request->materials);
        $incomingParts = $this->decodeJsonArray($request->parts);
        $flowService = app(ProductionFlowService::class);
        $partQuantities = $flowService->normalizePartQuantities($request->production_flows);
        if ($partQuantities->isEmpty() && !empty($incomingParts) && $request->article_quantity) {
            $partQuantities = collect($incomingParts)->map(fn ($part) => [
                'part' => (string) $part,
                'quantity' => (float) $request->article_quantity,
            ]);
        }
        if (!empty($incomingTags)) {
            $tagBalances = app(ProductionItemSyncService::class)
                ->workerTagBalances([(int) $request->worker_id], 'productions')
                ->get((int) $request->worker_id, collect())
                ->keyBy('tag');

            foreach (collect($incomingTags)->groupBy('tag') as $tag => $rows) {
                $requestedQuantity = collect($rows)->sum(fn ($row) => (float) ($row['quantity'] ?? 0));
                $availableQuantity = (float) ($tagBalances->get($tag)['available_quantity'] ?? 0);
                if ($requestedQuantity > $availableQuantity) {
                    return $this->validationBack([
                        'tags' => "{$tag} available fabric is {$availableQuantity}.",
                    ]);
                }
            }
        }

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
            'issued_by_name' => $request->issued_by_name,
            'received_by_name' => $request->received_by_name,
            'branch_id' => app(ModuleBranchService::class)->branchIdForCreate('productions'),
        ];
        $ticket = null;
        $production = null;

        if ($flowService->ready()) {
            $article = Article::findOrFail((int) $request->article_id);
            $work = Setup::findOrFail((int) $request->work_id);
            if ($partQuantities->isEmpty()) {
                return $this->validationBack(['production_flows' => 'Select at least one part quantity.']);
            }

            if ($request->filled('ticket_name') && $request->ticket_name != '-- Select Ticket --') {
                if (!$request->filled('receive_date')) {
                    return $this->validationBack(['receive_date' => 'Receive date is required.']);
                }

                $parent = Production::where('ticket', $request->ticket_name)->first();
                if (!$parent) {
                    return $this->validationBack(['ticket_name' => 'Ticket not found.']);
                }

                try {
                    $flowService->validateReceive((string) $parent->ticket, $partQuantities);
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    return $this->validationBack($exception->errors());
                }

                $data['issue_date'] = null;
                $data['receive_date'] = $request->receive_date;
                $data['ticket'] = $parent->ticket . '-R' . now()->format('His');
                $data['parts'] = $partQuantities->pluck('part')->values()->all();
                $data['branch_id'] = $parent->branch_id;
                $production = Production::create($data);
                try {
                    $flowService->sync($production->fresh(), 'receive', $partQuantities, (string) $parent->ticket);
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    $production->delete();
                    return $this->validationBack($exception->errors());
                }

                if ($flowService->receiveableByPart((string) $parent->ticket)->isEmpty()) {
                    $parent->update(['receive_date' => $request->receive_date]);
                }

                $ticket = $production->ticket;
            } else {
                try {
                    if ($request->receive_date && $flowService->isCutting($work)) {
                        $flowService->validateCuttingReceive($article, $partQuantities);
                    } elseif ($request->receive_date && !$flowService->isCutting($work)) {
                        return $this->validationBack(['ticket_name' => 'Select an issue ticket before receiving this work.']);
                    } elseif ($request->issue_date && !$flowService->isCutting($work)) {
                        $flowService->validateIssue($article, $partQuantities, $work);
                    } elseif ($request->issue_date && $flowService->isCutting($work)) {
                        return $this->validationBack(['work_id' => 'Cutting issue ticket is not allowed. Receive cutting directly.']);
                    } else {
                        return $this->validationBack([
                            Auth::user()->production_type === 'receive' ? 'receive_date' : 'issue_date' => 'Production date is required.',
                        ]);
                    }
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    return $this->validationBack($exception->errors());
                }

                $data['parts'] = $partQuantities->pluck('part')->values()->all();
                $data['ticket'] = 'TEMP';
                $production = Production::create($data);

                $workPrefix = explode('|', $work->short_title ?: $work->title)[0];
                $ticket = app(ModuleBranchService::class)->shouldFilterRecords('productions')
                    ? app(BranchSerialService::class)->nextProductionTicket($workPrefix)
                    : $workPrefix . str_pad($production->id, 3, '0', STR_PAD_LEFT);
                $production->update(['ticket' => $ticket]);
                try {
                    $flowService->sync(
                        $production->fresh(),
                        $request->issue_date ? 'issue' : 'receive',
                        $partQuantities,
                    );
                } catch (\Illuminate\Validation\ValidationException $exception) {
                    $production->delete();
                    return $this->validationBack($exception->errors());
                }
                app(ProductionItemSyncService::class)->sync($production->fresh(), $incomingTags, $incomingMaterials);
            }
        } elseif ($request->filled('ticket_name') && $request->ticket_name != '-- Select Ticket --') {
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
                    'issued_by_name' => $request->issued_by_name,
                    'received_by_name' => $request->received_by_name,
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
            if (app(ProductionFlowService::class)->ready()) {
                $previewRelations[] = 'productionFlows';
            }
            $production->loadMissing($previewRelations);
        }

        return redirect()->route('productions.create')
            ->with('success', 'Production ' . $issueOrReceive . ' successfully. Ticket: ' . $ticket)
            ->with('production_ticket_preview', $production ? $this->ticketPreviewPayload($production) : null);
    }

    protected function ticketPreviewPayload(Production $production): array
    {
        $partQuantities = $this->productionPartQuantities($production);
        $flowQuantity = collect($partQuantities)->max('quantity') ?? $production->quantity;

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
            'movement_type' => $production->issue_date ? 'Issue' : 'Receive',
            'parent_ticket' => $this->productionParentTicket($production),
            'quantity' => $flowQuantity,
            'rate' => $production->rate,
            'amount' => $production->amount,
            'title' => $production->title,
            'parts' => $production->parts,
            'part_quantities' => $partQuantities,
            'tags' => app(ProductionItemSyncService::class)->tagsForPayload($production),
            'materials' => app(ProductionItemSyncService::class)->materialsForPayload($production),
            'creator' => $production->creator?->name,
            'issued_by_name' => $production->issued_by_name,
            'received_by_name' => $production->received_by_name,
            'branch_branding' => app(ModuleBranchService::class)->documentBranding('productions', $production),
        ];
    }

    private function productionPartQuantities(Production $production): array
    {
        if (app(ProductionFlowService::class)->ready()) {
            $production->loadMissing('productionFlows');
            if ($production->productionFlows->isNotEmpty()) {
                return $production->productionFlows
                    ->groupBy('part')
                    ->map(fn ($flows, $part) => [
                        'part' => (string) $part,
                        'quantity' => (float) $flows->sum('quantity'),
                        'movement_type' => ucfirst((string) $flows->first()->movement_type),
                    ])
                    ->values()
                    ->all();
            }
        }

        return collect($production->parts ?? [])
            ->map(fn ($part) => [
                'part' => (string) $part,
                'quantity' => (float) ($production->quantity ?? $production->article?->quantity ?? 0),
                'movement_type' => $production->issue_date ? 'Issue' : 'Receive',
            ])
            ->values()
            ->all();
    }

    private function productionParentTicket(Production $production): ?string
    {
        if (!app(ProductionFlowService::class)->ready()) {
            return null;
        }

        $production->loadMissing('productionFlows');

        return $production->productionFlows
            ->pluck('parent_ticket')
            ->filter()
            ->first();
    }

    private function validationBack(mixed $errors)
    {
        $errorArray = method_exists($errors, 'errors')
            ? $errors->errors()->toArray()
            : (array) $errors;

        $firstMessage = collect($errorArray)
            ->flatten()
            ->filter()
            ->first() ?: 'Please fix the highlighted validation errors.';

        return redirect()->back()
            ->withErrors($errors)
            ->withInput()
            ->with('error', $firstMessage);
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

        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $production->loadMissing(['article', 'work', 'worker', 'productionFlows']);

        return view('productions.edit', [
            'production' => $production,
            'branchBranding' => app(ModuleBranchService::class)->documentBranding('productions', $production),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Production $production)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($production, 'productions');

        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $validated = $request->validate([
            'issue_date' => 'nullable|date',
            'receive_date' => 'nullable|date',
            'rate' => 'nullable|numeric|min:0',
            'amount' => 'nullable|numeric|min:0',
            'issued_by_name' => 'nullable|string|max:120',
            'received_by_name' => 'nullable|string|max:120',
        ]);

        DB::transaction(function () use ($production, $validated) {
            $data = [
                'rate' => $validated['rate'] ?? null,
                'amount' => $validated['amount'] ?? null,
                'issued_by_name' => $validated['issued_by_name'] ?? $production->issued_by_name,
                'received_by_name' => $validated['received_by_name'] ?? $production->received_by_name,
            ];

            if (array_key_exists('issue_date', $validated) && $validated['issue_date']) {
                $data['issue_date'] = $validated['issue_date'];
                $production->update($data);

                if (app(ProductionFlowService::class)->ready()) {
                    $production->productionFlows()->where('movement_type', 'issue')->update([
                        'date' => $validated['issue_date'],
                    ]);
                }

                return;
            }

            if (array_key_exists('receive_date', $validated) && $validated['receive_date']) {
                if ($production->receive_date || !$production->ticket || !app(ProductionFlowService::class)->ready()) {
                    $data['receive_date'] = $validated['receive_date'];
                    $production->update($data);
                    if (app(ProductionFlowService::class)->ready()) {
                        $production->productionFlows()->where('movement_type', 'receive')->update([
                            'date' => $validated['receive_date'],
                        ]);
                    }

                    return;
                }

                $childId = \App\Models\ProductionFlow::query()
                    ->where('parent_ticket', $production->ticket)
                    ->where('movement_type', 'receive')
                    ->orderByDesc('date')
                    ->orderByDesc('id')
                    ->value('production_id');

                if ($childId) {
                    $child = Production::find($childId);
                    if ($child) {
                        $child->update(array_merge($data, [
                            'receive_date' => $validated['receive_date'],
                        ]));
                        $child->productionFlows()->where('movement_type', 'receive')->update([
                            'date' => $validated['receive_date'],
                        ]);
                    }
                } else {
                    $production->update(array_merge($data, [
                        'receive_date' => $validated['receive_date'],
                    ]));
                }
            }
        });

        return redirect()->route('productions.index')->with('success', 'Production updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Production $production)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($production, 'productions');

        if ($resp = $this->denyIfNoRole(['developer'])) {
            return $resp;
        }

        $dependencies = $this->dependencyCounts([
            'production tags' => ['production_tags', 'production_id', $production->id],
            'production materials' => ['production_materials', 'production_id', $production->id],
        ]);

        if (!empty($dependencies)) {
            return redirect()->back()->with('error', $this->dependencyBlockMessage('Production', $dependencies));
        }

        DB::transaction(function () use ($production) {
            $production->delete();
        });

        return redirect()->route('productions.index')->with('success', 'Production deleted successfully.');
    }
}
