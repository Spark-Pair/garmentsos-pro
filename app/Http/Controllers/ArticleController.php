<?php

namespace App\Http\Controllers;

use App\Events\NewNotificationEvent;
use App\Models\Article;
use App\Models\Order;
use App\Models\Setup;
use App\Services\Branches\ModuleBranchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ArticleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'manager', 'admin', 'accountant', 'guest', 'store_keeper'])) {
            return $resp;
        }

        $branches = app(ModuleBranchService::class);
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
            $articles = $branches->applyScope(Article::orderByDesc('id'), 'articles')
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
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $branches = app(ModuleBranchService::class);
        $lastRecord = $branches->applyScope(Article::orderBy('id', 'desc'), 'articles')->first();

        if ($lastRecord) {
            $lastRecord->total_rate = 0;
        } else {
            $lastRecord = '';
        }

        $articles = $branches->applyScope(Article::select('id', 'article_no'), 'articles')->get();
        $masterUnitOptions = $this->masterUnitOptions();

        return view('articles.create', compact('lastRecord', 'articles', 'masterUnitOptions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        $validator = Validator::make($request->all(), [
            'article_no'    => 'required|integer',
            'date'          => 'required|date',
            'category'      => 'nullable|string',
            'size'          => 'required|string',
            'season'        => 'required|string',
            'quantity'      => 'nullable|integer|min:1',
            'extra_pcs'     => 'nullable|integer|min:0',
            'fabric_type'   => 'nullable|string',
            'pcs_per_packet' => 'nullable|integer|min:1',
            'rates_array'   => 'nullable|json',
            'sales_rate'    => 'required|numeric|min:0',
            'image_upload'  => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        $branches = app(ModuleBranchService::class);
        $branchId = $branches->branchIdForCreate('articles');

        // Prepare data
        $data = [
            'article_no'   => $request->article_no,
            'date'         => $request->date,
            'category'     => $request->category,
            'size'         => $request->size,
            'season'       => $request->season,
            'quantity'     => $request->quantity,
            'extra_pcs'    => $request->extra_pcs,
            'fabric_type'  => $request->fabric_type,
            'pcs_per_packet' => $request->filled('pcs_per_packet') ? (int) $request->pcs_per_packet : 0,
            'rates_array'  => json_decode($request->rates_array),
            'sales_rate'   => $request->sales_rate,
            'branch_id'    => $branchId,
        ];

        // Generate formatted Article No
        $year = date('y');
        $seasonLetter = strtoupper(substr($data['season'], 0, 1));

        $yearFirstDigit = substr($year, 0, 1);
        $yearLastDigit  = substr($year, -1);

        $articleNoPadded = str_pad($data['article_no'], 4, '0', STR_PAD_LEFT);

        $formattedArticleNo = $seasonLetter . $yearFirstDigit . '-' . $yearLastDigit . '|' . $articleNoPadded;

        $data['article_no'] = $formattedArticleNo;

        /*
        |--------------------------------------------------------------------------
        | Branch-wise Duplicate Check
        |--------------------------------------------------------------------------
        | Same article number is allowed in different branches.
        | Duplicate only if article_no AND branch_id are both the same.
        |--------------------------------------------------------------------------
        */
        $duplicateQuery = Article::where('article_no', $formattedArticleNo);

        if (Schema::hasColumn('articles', 'branch_id')) {
            $duplicateQuery->where('branch_id', $branchId);
        }

        if ($duplicateQuery->exists()) {
            $validator->after(function ($validator) {
                $validator->errors()->add(
                    'article_no',
                    'Article No already exists for this branch.'
                );
            });
        }

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Upload image
        if ($request->hasFile('image_upload')) {
            $file = $request->file('image_upload');

            $fileName = time() . '_' . $file->getClientOriginalName();

            $file->storeAs('uploads/images', $fileName, 'public');

            $data['image'] = $fileName;
        }

        $article = Article::create($data);

        if (
            $article->sales_rate > 0 &&
            !empty($article->category) &&
            !empty($article->fabric_type) &&
            app('pusher.enabled')
        ) {
            try {
                event(new NewNotificationEvent([
                    'type'    => 'success',
                    'title'   => 'New Article Added.',
                    'message' => 'Your articles feed has been updated. Please check.',
                ]));
            } catch (\Exception $e) {
                // Ignore Pusher errors
            }
        }

        return redirect()
            ->route('articles.create')
            ->with('success', 'Article added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Article $article)
    {
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($article, 'articles');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Article $article)
    {
        if (!$this->checkRole(['developer', 'owner', 'admin']) && !app_can('articles', 'override')) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($article, 'articles');

        $canArticleOverride = $this->canUseArticleDeveloperOverride();

        if (!$canArticleOverride && $article->ordered_quantity != 0) {
            return redirect(route('articles.index'))->with("error", "This article can't be edited.");
        }

        $developerImpact = $canArticleOverride
            ? $this->articleDeveloperImpact($article)
            : [];
        $masterUnitOptions = $this->masterUnitOptions($article->pcs_per_packet);

        return view('articles.edit', compact('article', 'developerImpact', 'canArticleOverride', 'masterUnitOptions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Article $article)
    {
        if (!$this->checkRole(['developer', 'owner', 'admin']) && !app_can('articles', 'update')) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }
        app(ModuleBranchService::class)->assertRecordInAllowedBranch($article, 'articles');

        if (!$this->canUseArticleDeveloperOverride() && $article->ordered_quantity != 0) {
            return redirect(route('articles.index'))->with("error", "This article can't be edited.");
        }

        $validator = Validator::make($request->all(), [
            'article_no' => 'required|string|unique:articles,article_no,' . $article->id,
            'category' => 'nullable|string',
            'size' => 'required|string',
            'season' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'extra_pcs' => 'required|integer|min:0',
            'fabric_type' => 'nullable|string',
            'pcs_per_packet' => 'nullable|integer|min:1',
            'rates_array' => 'nullable|string',
            "sales_rate" => 'required|numeric|min:0',
            'image_upload' => 'nullable|image|mimes:jpg,jpeg,png,gif|max:2048',
        ]);

        // Prepare data for saving
        $data = [
            'article_no' => $request->article_no,
            'category' => $request->category,
            'size' => $request->size,
            'season' => $request->season,
            'quantity' => $request->quantity,
            'extra_pcs' => $request->extra_pcs,
            'fabric_type' => $request->fabric_type,
            'pcs_per_packet' => $request->filled('pcs_per_packet') ? (int) $request->pcs_per_packet : 0,
            'rates_array' => json_decode($request->rates_array),
            'sales_rate' => $request->sales_rate,
        ];

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
        if (!$this->canUseArticleDeveloperOverride()) {
            return redirect(route('home'))->with('error', 'You do not have permission to access this page.');
        }

        app(ModuleBranchService::class)->assertRecordInAllowedBranch($article, 'articles');

        $impact = $this->articleDeveloperImpact($article);
        $hasConnections = collect($impact)->sum('count') > 0;

        if ($hasConnections && !request()->boolean('confirm_delete_connected')) {
            return redirect()
                ->route('articles.edit', $article)
                ->with('error', 'This article is connected with other records. Review the usage summary, then confirm force delete if needed.')
                ->with('developer_delete_impact', $impact);
        }

        if ($article->image && $article->image !== 'no_image_icon.png' && Storage::disk('public')->exists('uploads/images/' . $article->image)) {
            Storage::disk('public')->delete('uploads/images/' . $article->image);
        }

        $article->delete();

        return redirect()->route('articles.index')->with('success', 'Article deleted successfully.');
    }

    private function canUseArticleDeveloperOverride(): bool
    {
        return Auth::user()?->role === 'developer' || app_can('articles', 'override');
    }

    private function articleDeveloperImpact(Article $article): array
    {
        $relations = [
            'orderArticles' => 'Orders',
            'invoiceArticles' => 'Invoices',
            'shipmentArticles' => 'Shipments',
            'physicalQuantity' => 'Physical Quantity',
            'production' => 'Productions',
            'salesReturns' => 'Sales Returns',
        ];

        $impact = [];
        foreach ($relations as $relation => $label) {
            $count = method_exists($article, $relation)
                ? (int) $article->{$relation}()->count()
                : 0;

            $impact[] = [
                'label' => $label,
                'count' => $count,
            ];
        }

        return $impact;
    }

    private function masterUnitOptions($currentValue = null): array
    {
        $options = app(ModuleBranchService::class)
            ->applyRelatedScope(Setup::where('type', 'article_master_unit')->orderBy('title'), 'setups', 'articles')
            ->get()
            ->filter(fn (Setup $setup) => (int) $setup->title > 0)
            ->mapWithKeys(fn (Setup $setup) => [
                (string) (int) $setup->title => ['text' => (string) (int) $setup->title],
            ])
            ->all();

        $currentValue = (int) ($currentValue ?? 0);
        if ($currentValue > 0 && !isset($options[(string) $currentValue])) {
            $options[(string) $currentValue] = ['text' => (string) $currentValue];
        }

        return $options;
    }

    public function updateImage(Request $request)
    {
        $article = Article::where('id', $request->article_id)->first();

        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

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
        if ($resp = $this->denyIfNoRole(['developer', 'owner', 'admin', 'accountant', 'store_keeper'])) {
            return $resp;
        }

        // Validate input first
        $validator = Validator::make($request->all(), [
            'article_id' => 'required|integer|exists:articles,id',
            "sales_rate" => 'required|numeric|min:0',
            "pcs_per_packet" => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        Article::where('id', $request->article_id)->update([
            'sales_rate' => $request->sales_rate,
            'rates_array' => json_decode($request->rates_array),
            'pcs_per_packet' => $request->pcs_per_packet
        ]);

        return redirect()->route('articles.index')->with('success', 'Rate added successfully');
    }
}
