<?php

namespace App\Http\Controllers;

use App\Events\NewNotificationEvent;
use App\Models\Article;
use App\Models\Order;
use App\Models\Setup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ArticleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        // $articles = Article::with('creator')->get();

        // foreach ($articles as $article) {
        //     $orders = Order::all();

        //     if ($orders) {
        //         foreach ($orders as $order) {
        //             $articlesArray = json_decode($order->ordered_articles, true);
        //         }
        //     }
        // }

        $authLayout = $this->getAuthLayout($request->route()->getName());

        if ($request->ajax()) {
            $articles = Article::orderByDesc('id')
                ->applyFilters($request);

            return response()->json(['data' => $articles, 'authLayout' => $authLayout]);
        }

        return view('articles.index', compact( 'authLayout'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        $lastRecord = Article::orderBy('id', 'desc')->first();

        if ($lastRecord) {
            $lastRecord->total_rate = 0;
        } else {
            $lastRecord = '';
        }

        $articles = Article::all();

        return view('articles.create', compact('lastRecord', 'articles'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        // return $request;

        $validator = Validator::make($request->all(), [
            'article_no' => 'required|integer|unique:articles,article_no',
            'date' => 'required|date',
            'category' => 'nullable|string',
            'size' => 'required|string',
            'season' => 'required|string',
            'quantity' => 'nullable|integer|min:1',
            'extra_pcs' => 'nullable|integer|min:0',
            'fabric_type' => 'nullable|string',
            'rates_array' => 'nullable|json',
            "sales_rate" => 'required|numeric|min:0',
            'image_upload' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        // Prepare data for saving
        $data = $request->all();

        $data['rates_array'] = json_decode($data['rates_array']);

        $year = date('y');
        $seasonLetter = strtoupper(substr($data['season'], 0, 1));

        // Get first and last digit of the year
        $yearFirstDigit = substr($year, 0, 1);
        $yearLastDigit = substr($year, -1);

        // Pad article_no to 3 digits
        $articleNoPadded = str_pad($data['article_no'], 3, '0', STR_PAD_LEFT);

        // Combine as F2-5-001
        $formattedArticleNo = $seasonLetter . $yearFirstDigit . '-' . $yearLastDigit . '|' . $articleNoPadded;

        $data['article_no'] = $formattedArticleNo;

        // Handle the image upload if present
        if ($request->hasFile('image_upload')) {
            $file = $request->file('image_upload');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads/images', $fileName, 'public'); // Store in public disk

            $data['image'] = $fileName; // Save the file path in the database
        }

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $article = Article::create($data);

        if ($article->sales_rate > 0 && $article->category != null && $article->fabric_type != null && app('pusher.enabled')) {
            try {
                event(new NewNotificationEvent(['title' => 'New Article Added.', 'message' => 'Your articles feed has been updated. Please check.']));
            } catch (\Exception $e) {
                //
            }
        }

        return redirect()->route('articles.create')->with('success', 'Article added successfully');
    }

    /**
     * Display the specified resource.
     */
    public function show(Article $article)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Article $article)
    {
        if(!$this->checkRole(['developer', 'owner', 'admin']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        if ($article->ordered_quantity != 0) {
            return redirect(route('articles.index'))->with("error", "This article can't be edited.");
        }

        return view('articles.edit', compact('article'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Article $article)
    {
        if(!$this->checkRole(['developer', 'owner', 'admin']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        $validator = Validator::make($request->all(), [
            'article_no' => 'required|string|unique:articles,article_no,' . $article->id,
            'category' => 'nullable|string',
            'size' => 'required|string',
            'season' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'extra_pcs' => 'required|integer|min:0',
            'fabric_type' => 'nullable|string',
            'rates_array' => 'nullable|string',
            "sales_rate" => 'required|numeric|min:0',
            'image_upload' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        // Prepare data for saving
        $data = $request->all();

        $data['rates_array'] = json_decode($data['rates_array']);

        // Handle the image upload if present
        if ($request->hasFile('image_upload')) {
            if ($article->image && Storage::disk('public')->exists('uploads/images/' . $article->image)) {
                Storage::disk('public')->delete('uploads/images/' . $article->image);
            }

            $file = $request->file('image_upload');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads/images', $fileName, 'public'); // Store in public disk

            $data['image'] = $fileName; // Save the file path in the database
        }

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Please check the form for errors.');
        }

        $article->update($data);

        foreach (['category' => 'article_category', 'size' => 'article_size', 'season' => 'article_seasons'] as $field => $type) {
            if (!empty($data[$field])) {
                Setup::firstOrCreate([
                    'type' => $type,
                    'title' => $data[$field],
                ]);
            }
        }

        return redirect()->route('articles.index')->with('success', 'Article edit successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Article $article)
    {
        //
    }

    public function updateImage(Request $request)
    {
        $article = Article::where('id', $request->article_id)->first();

        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        // Validate input first
        $validator = Validator::make($request->all(), [
            'article_id' => 'integer|required|exists:articles,id',
            'image_upload' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Prepare data for saving
        $data = [];

        // Handle the image upload if present
        if ($request->hasFile('image_upload')) {
            if ($article->image && Storage::disk('public')->exists('uploads/images/' . $article->image)) {
                Storage::disk('public')->delete('uploads/images/' . $article->image);
            }

            $file = $request->file('image_upload');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $filePath = $file->storeAs('uploads/images', $fileName, 'public'); // Store in public disk

            $data['image'] = $fileName; // Save the file path in the database
        }

        // Update only if image is set
        if (!empty($data['image'])) {
            $article->update(['image' => $data['image']]);
            return redirect()->route('articles.index')->with('success', 'Image added successfully');
        } else {
            return redirect()->back()->with('error', 'Please upload an image');
        }
    }

    public function addRate(Request $request)
    {
        if(!$this->checkRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper']))
        {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        };

        // Validate input first
        $validator = Validator::make($request->all(), [
            'article_id' => 'required|integer|exists:articles,id',
            "sales_rate" => 'required|numeric|min:0',
            "pcs_per_packet" => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $data = $request->all();
        $data['rates_array'] = json_decode($data['rates_array']);

        Article::where('id', $request->article_id)->update(['sales_rate' => $data['sales_rate'], 'rates_array' => $data['rates_array'], 'pcs_per_packet' => $data['pcs_per_packet']]);

        return redirect()->route('articles.index')->with('success', 'Rate added successfully');
    }
}
