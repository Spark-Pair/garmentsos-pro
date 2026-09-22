<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Services\ArticleStockService;
use App\Services\PhysicalQuantityReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PhysicalQuantityArticleFilterTest extends TestCase
{
    public function test_article_filter_combines_multiple_ranges_and_values(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('article_no');
            $table->string('processed_by')->nullable();
        });

        foreach (['F2-6|0010', 'F2-6|0015', 'F2-6|0030', 'F2-6|0042', 'F2-6|0049', 'F2-6|0055'] as $number) {
            DB::table('articles')->insert(['article_no' => $number, 'processed_by' => 'Aj']);
        }
        DB::table('articles')->insert(['article_no' => 'F2-6|0045', 'processed_by' => 'Kashif']);

        $service = new PhysicalQuantityReportService($this->app->make(ArticleStockService::class));
        $method = new \ReflectionMethod($service, 'applyArticleFilters');
        $query = Article::query();
        $method->invoke($service, $query, [
            'article_no' => '0010 - 0020, 0042 - 0049, 0055',
            'processed_by' => 'Aj',
        ]);

        $this->assertEqualsCanonicalizing(
            ['F2-6|0010', 'F2-6|0015', 'F2-6|0042', 'F2-6|0049', 'F2-6|0055'],
            $query->pluck('article_no')->all()
        );
    }
}
