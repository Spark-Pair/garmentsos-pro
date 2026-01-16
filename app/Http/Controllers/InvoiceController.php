<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderArticles;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

use function PHPSTORM_META\type;

class InvoiceController extends Controller
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
            $invoices = Invoice::with(['order.articles.article', 'shipment.articles.article', 'customer.city'])->orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $invoices, 'authLayout' => $authLayout]);
        }

        // $invoices = Invoice::with(['order.articles.article', 'shipment.articles.article', 'customer.city'])->orderBy('id', 'desc')->get();

        // foreach ($invoices as $invoice) {
        //     $articles = [];

        //     foreach ($invoice->articles_in_invoice as $article_in_invoice) {
        //         $article = Article::find($article_in_invoice['id']);

        //         $articles[] = [
        //             'article' => $article,
        //             'description' => $article_in_invoice['description'],
        //             'invoice_quantity' => $article_in_invoice['invoice_quantity'],
        //         ];
        //     }
        //     $invoice['articles'] = $articles;
        // }

        // return $invoices;
        return view('invoices.index', compact('authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $orderNumber = session('orderNumber');

        if ($orderNumber) {
            $user = Auth::user();
            $user->invoice_type = 'order';
            $user->save();
        }

        $last_Invoice = invoice::orderby('id', 'desc')->first();

        if (!$last_Invoice) {
            $last_Invoice = new invoice();
            $last_Invoice->invoice_no = '00-0000';
        }

        $customers = Customer::with('user')->whereIn('category', ['regular', 'site'])->whereHas('user', function ($query) {
            $query->where('status', 'active');
        })->get();

        return view("invoices.generate", compact("last_Invoice", 'customers', 'orderNumber'));
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

        // check request has shipment no
        if ($request->has('shipment_no')) {
            $validator = Validator::make($request->all(), [
                "shipment_no" => "required|string|exists:shipments,shipment_no",
                "date" => "required|date",
                "customers_array" => "required|json",
                "printAfterSave" => "integer|in:0,1",
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $customers_array = json_decode($request->customers_array, true);

            $shipment = Shipment::where("shipment_no", $request->shipment_no)->first();
            // $articlesInShipment = $shipment->getArticles();

            $last_Invoice = Invoice::orderBy('id', 'desc')->first();

            if (!$last_Invoice) {
                $last_Invoice = new Invoice();
                $last_Invoice->invoice_no = '00-0000';
            }

            $currentYear = date("y");

            $lastNumberPart = substr($last_Invoice->invoice_no, -4); // last 4 characters
            $nextNumber = str_pad((int)$lastNumberPart + 1, 4, '0', STR_PAD_LEFT);


            $invoiceNumbers = [];
            foreach ($customers_array as $customer) {
                // $article_in_invoice = [];
                // foreach ($articlesInShipment as $article) {
                //     $article_in_invoice[] = [
                //         "id" => $article['article']["id"],
                //         "description" => $article["description"],
                //         "invoice_quantity" => $article["shipment_quantity"] * $customer['cotton_count'],
                //     ];
                //     $articleModel = Article::where("id", $article['article']["id"])->first();

                //     if ($articleModel) {
                //         $articleModel->increment('sold_quantity', $article["shipment_quantity"] * $customer['cotton_count']);
                //         $articleModel->increment('ordered_quantity', $article["shipment_quantity"] * $customer['cotton_count']);
                //     }
                // }

                $invoice = new Invoice();
                $invoice->customer_id = $customer["id"];
                $invoice->invoice_no = $currentYear . '-' . $nextNumber;
                $invoice->shipment_no = $request->shipment_no;
                $invoice->netAmount = $shipment->netAmount * $customer['cotton_count'];
                $invoice->cotton_count = $customer['cotton_count'];
                // $invoice->articles_in_invoice = $article_in_invoice;
                $invoice->date = date("Y-m-d");

                $nextNumber = str_pad((int)$nextNumber + 1, 4, '0', STR_PAD_LEFT);

                $invoiceNumbers[] = $currentYear . '-' . str_pad((int)$nextNumber - 1, 4, '0', STR_PAD_LEFT);

                $invoice->save();
            }

            if ($request->printAfterSave) {
                return redirect()->route('invoices.print')->with('invoiceNumbers', $invoiceNumbers);
            } else {
                return redirect()->route('invoices.create')->with('success', 'Invoice generated successfully.');
            }
        }
        else if ($request->has('order_no')) {
            $validator = Validator::make($request->all(), [
                "invoice_no" => "required|string|unique:invoices,invoice_no",
                "order_no" => "required|string|exists:orders,order_no",
                "date" => "required|date",
                "netAmount" => "required|string",
                "articles_in_invoice" => "required|string",
            ]);

            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }

            $data = $request->all();

            $data['articles_in_invoice'] = json_decode($data['articles_in_invoice'], true);

            // return $data;

            // foreach ($data['articles_in_invoice'] as $article) {
            //     $articleDb = Article::where("id", $article["id"])->increment('sold_quantity', $article["invoice_quantity"]);
            // }

            $orderDb = Order::where("order_no", $data["order_no"])->first();
            foreach ($data['articles_in_invoice'] as $article) {
                // $orderedArticleDb = json_decode($orderDb["articles"], true);

                // Update all matching articles
                // foreach ($orderedArticleDb as &$orderedArticle) { // Pass by reference!
                //     if (isset($orderedArticle["id"]) && $orderedArticle["id"] == $article["id"]) {
                //         $orderedArticle["invoice_quantity"] = ($orderedArticle["invoice_quantity"] ?? 0) + $article["invoice_quantity"];
                //     }
                // }
                // unset($orderedArticle); // Important: break reference after loop

                // Save updated articles back to the database
                // $orderDb->articles = json_encode($orderedArticleDb);

                $orderArticleDb = OrderArticles::find($article['order_article_id']);
                $orderArticleDb->dispatched_pcs = $article['invoice_quantity'];
                $orderArticleDb->save();

                if ($orderArticleDb->dispatched_pcs == 0) {
                    $orderDb->status = 'pending';
                } elseif ($orderArticleDb->dispatched_pcs < $orderArticleDb->ordered_pcs) {
                    $orderDb->status = 'partially_invoiced';
                } else {
                    $orderDb->status = 'invoiced';
                }

                $orderDb->save();
            }

            $data["netAmount"] = (int) str_replace(',', '', $data["netAmount"]);
            $data["customer_id"] = $orderDb["customer_id"];

            Invoice::create($data);
        }

        return redirect()->route('invoices.create')->with('success', 'Invoice generated successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(invoice $invoice)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(invoice $invoice)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, invoice $invoice)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(invoice $invoice)
    {
        //
    }

    public function print()
    {
        $invoiceNumbers = session('invoiceNumbers');

        if (!$invoiceNumbers) {
            return redirect()->route('invoices.create')->with('error', 'No invoices to print.');
        }

        $invoices = Invoice::with(["customer.city", 'shipment.articles.article'])->whereIn('invoice_no', $invoiceNumbers)->get();

        return view("invoices.print", compact("invoices"));
    }

}
