<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderArticles;
use App\Models\PaymentProgram;
use App\Models\PhysicalQuantity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $authLayout = $this->getAuthLayout($request->route()->getName());

        if ($request->ajax()) {
            $orders = Order::with('customer.city', 'articles.article')->orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $orders, 'authLayout' => $authLayout]);
        }

        // $orders = Order::with('customer.city', 'articles.article')->get();

        // // Collect all article IDs from ordered articles
        // $articleIds = $orders->flatMap(function ($order) {
        //     return collect(json_decode($order->articles, true))->pluck('id');
        // })->unique();

        // // Fetch all required articles in a single query
        // $articles = Article::whereIn('id', $articleIds)->get()->keyBy('id');

        // $orders = $orders->transform(function ($order) use ($articles) {
        //     // Step 1: Decode and normalize articles to indexed array
        //     $orderedArticlesRaw = json_decode($order->articles, true) ?? [];
        //     $orderedArticlesArray = array_values($orderedArticlesRaw); // Normalize to indexed array

        //     // Step 2: Map through each ordered article
        //     $orderedArticles = collect($orderedArticlesArray)->map(function ($orderedArticle) use ($articles) {
        //         if (isset($articles[$orderedArticle['id']])) {
        //             $orderedArticle['article'] = $articles[$orderedArticle['id']];
        //         }

        //         $orderedArticle['ordered_pcs'] = max(0, $orderedArticle['ordered_pcs'] - ($orderedArticle['invoice_quantity'] ?? 0));

        //         return $orderedArticle;
        //     })->filter(function ($orderedArticle) {
        //         return $orderedArticle['ordered_pcs'] > 0;
        //     })->values(); // 👈 ensures final collection is indexed (not associative)

        //     // Step 3: Put it back into the order
        //     $order['articles'] = $orderedArticles;

        //     return $order;
        // })
        // ->filter(function ($order) {
        //     return $order['articles']->isNotEmpty();
        // })
        // ->values();

        // foreach ($orders as $key => $order) {
        //     $order['previous_balance'] = $order->customer->calculateBalance(null, $order->date, false, false);
        //     $order['current_balance'] = $order['previous_balance'] + $order['netAmount'];
        // }

        return view('orders.index', compact( 'authLayout'));
        // return $orders;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $customers_options = [];
        $articles = [];

        if ($request->date) {
            $customers = Customer::with('orders', 'payments')->whereHas('user', function ($query) {
                $query->where('status', 'active');
            })->where('date', '<=', $request->date)->get();

            foreach ($customers as $customer) {
                $customers_options[(int)$customer->id] = [
                    'text' => $customer->customer_name . ' | ' . $customer->city->title,
                    'data_option' => $customer
                ];
            }

            $articles = Article::where('date', '<=', $request->date)->where('sales_rate', '>', 0)->whereNotNull(['category', 'fabric_type'])->whereRaw('ordered_quantity < quantity')->orderByDesc('id')->get();

            foreach ($articles as $article) {
                $physical_quantity = PhysicalQuantity::where('article_id', $article->id)->sum('packets');
                $article['physical_quantity'] = ( $physical_quantity * $article->pcs_per_packet ) - $article['sold_quantity'];

                $article['category'] = ucfirst(str_replace('_', ' ', $article['category']));
                $article['season'] = ucfirst(str_replace('_', ' ', $article['season']));
                $article['size'] = ucfirst(str_replace('_', '-', $article['size']));
            }
        }

        $last_order = Order::orderby('id', 'desc')->first();

        if (!$last_order) {
            $last_order = new Order();
            $last_order->order_no = '00-0000';
        }

        if ($request->ajax()) {
            return response()->json([
                'status' => 'success',
                'articles' => $articles,
                'customers_options' => array_values($customers_options),
            ]);
        }

        return view('orders.generate', compact('last_order'));
        // return $articles;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $validator = Validator::make($request->all(), [
            'date' => 'required|date',
            'customer_id' => 'required|integer|exists:customers,id',
            'discount' => 'required|integer',
            'netAmount' => 'required|string',
            'articles' => 'required|json',
            'order_no' => 'required|string',
            'generateInvoiceAfterSave' => 'integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->all();

        $data['netAmount'] = str_replace(',', '', $data['netAmount']);

        $order = Order::create($data);

        $data['articles'] = json_decode($data['articles'], true);

        foreach ($data['articles'] as $articleData) {
            // $article = Article::where('id', $articleData['id'])->first();
            // if ($article) {
            //     $article->ordered_quantity += $articleData['ordered_quantity'];
            //     $article->save();
            // }
            OrderArticles::create([
                'order_id' => $order['id'],
                'article_id' => $articleData['id'],
                'description' => $articleData['description'],
                'ordered_pcs' => $articleData['ordered_quantity'],
            ]);
        }

        $customer = Customer::find($order['customer_id']);

        if ($customer['category'] == 'cash') {
            $lastProgram = PaymentProgram::orderBy('id','desc')->first();
            $nextProgramNo = $lastProgram ? $lastProgram->program_no + 1 : 1;

            $program = new PaymentProgram([
                'program_no' => $nextProgramNo,
                'date' => $order['date'],
                'order_no' => $order['order_no'],
                'customer_id' => $order['customer_id'],
                'category' => 'waiting',
                'amount' => $order['netAmount'],
            ]);

            $program->save();
        }

        // if ($request->generateInvoiceAfterSave) {
        //     return redirect()->route('invoices.create')->with('orderNumber', $order->order_no);
        // } else {
            return redirect()->route('orders.create')->with('success', 'Order generated successfully. Order No. : ' . $order['order_no']);
        // }
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        //
    }
}
