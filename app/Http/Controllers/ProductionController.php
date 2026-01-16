<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Employee;
use App\Models\Fabric;
use App\Models\Production;
use App\Models\Rate;
use App\Models\ReturnFabric;
use App\Models\Setup;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProductionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!$this->checkRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        // $productions = Production::with('article', 'work', 'worker')->orderby('id', 'desc')->get();

        if ($request->ajax()) {
            $productions = Production::orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $productions, 'authLayout' => 'table']);
        }

        return view('productions.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!$this->checkRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $ticket_options = [];

        if (Auth::user()->production_type === 'issue') {
            $articles = Article::whereHas('production.work', function($query) {
                $query->where('title', 'Cutting');
            })->with('production.work')->get();
        } else {
            $cmt_work_id = Setup::where('title', 'CMT | E')->value('id') ?? 0;
            $allTickets = Production::whereNull('receive_date')->where('work_id', '!=', $cmt_work_id)->with('article.production.work')->get();
            foreach ($allTickets as $ticket) {
                if ($ticket->id == 50) {
                    $ticket_options[$ticket->id] = [
                        'text' => $ticket->ticket,
                        'data_option' => $ticket,
                    ];
                }
            }
            $articles = Article::whereNotNull('fabric_type')->whereNotNull('category')->with('production.work')->get();
        }
        $work_options = [];
        $workerTypes = Setup::where('type', 'worker_type')->get();
        foreach($workerTypes as $workerType) {
            $work_options[(int)$workerType->id] = [
                'text' => $workerType->title
            ];
        }
        $worker_options = [];
        $workers = Employee::with('type')->where('category', 'worker',)->where('status', 'active')->get();
        foreach($workers as $worker) {
            $worker['taags'] = $worker['tags']
                ->groupBy('tag')
                ->map(function ($items, $tag) use ($articles) {
                    $fabric = Fabric::where('tag', $tag)->first();
                    $total_return_fabric = ReturnFabric::where('tag', $tag)->sum('quantity');

                    $supplier = null;
                    if ($fabric && $fabric->supplier_id) {
                        $supplier = Supplier::find($fabric->supplier_id);
                    }

                    $sum = $articles->flatMap->production
                        ->flatMap->tags
                        ->filter(fn($tagObj) => $tagObj['tag'] === $tag)
                        ->sum('quantity');

                    $availableQuantity = ($items->sum('quantity') - $sum) - $total_return_fabric;

                    if ($availableQuantity > 0) {
                        return [
                            'tag' => $tag,
                            'unit' => ucfirst($fabric->unit),
                            'available_quantity' => $availableQuantity,
                            'supplier_name' => $supplier->supplier_name ?? null,
                        ];
                    }

                    return null; // keeps mapping consistent
                })
                ->filter() // removes all nulls
                ->values();

            $worker_options[(int)$worker->id] = [
                'text' => $worker->employee_name,
                'data_option' => $worker->makeHidden('tags'),
            ];
        }

        $rates = Rate::with('type')->get();

        return view('productions.add', compact('articles', 'work_options', 'worker_options', 'rates', 'ticket_options'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!$this->checkRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $validator = Validator::make($request->all(), [
            'article_id' => 'required|integer|exists:articles,id',
            'work_id' => 'required|integer|exists:setups,id',
            'worker_id' => 'required|integer|exists:employees,id',
            'tags' => 'nullable|string',
            'materials' => 'nullable|string',
            'parts' => 'nullable|string',
            'quantity' => 'nullable|integer|min:1',
            'title' => 'nullable|string',
            'rate' => 'nullable|decimal:0,2|min:1',
            'amount' => 'nullable|decimal:0,2|min:1',
            'issue_date' => 'nullable|date',
            'receive_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->all();
        $ticket = null;

        if (isset($data['ticket_name']) && $data['ticket_name'] != '-- Select Ticket --') {
            $ticket = $data['ticket_name'];
            $data['tags'] = isset($data['tags']) ? json_decode($data['tags']) : null;
            $data['materials'] = isset($data['materials']) ? json_decode($data['materials']) : null;
            $data['parts'] = isset($data['parts']) ? json_decode($data['parts']) : null;
            $production = Production::where('ticket', $data['ticket_name'])->first();
            if ($production) {
                $production->update($data);
            }
        } else {
            $data['tags'] = isset($data['tags']) ? json_decode($data['tags']) : null;
            $data['materials'] = isset($data['materials']) ? json_decode($data['materials']) : null;
            $data['parts'] = isset($data['parts']) ? json_decode($data['parts']) : null;

            if ($request->article_quantity) {
                Article::where('id', $request->article_id)->update(['quantity' => $request->article_quantity]);
            }

            $work = Setup::find($request->work_id);

            $data['ticket'] = 'TEMP';
            $production = Production::create($data);

            $ticket = explode('|', $work->short_title)[0] . str_pad($production->id, 3, '0', STR_PAD_LEFT);
            $production->update(['ticket' => $ticket]);
        }

        $issueOrReceive = '';
        if ($request->issue_date) {
            $issueOrReceive = 'issue';
        } else {
            $issueOrReceive = 'receive';
        }

        return redirect()->route('productions.create')->with('success', 'Production ' . $issueOrReceive . ' successfully. Ticket: ' . $ticket);
    }

    /**
     * Display the specified resource.
     */
    public function show(Production $production)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Production $production)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Production $production)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Production $production)
    {
        //
    }
}
