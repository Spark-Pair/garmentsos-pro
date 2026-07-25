<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropUnique('articles_article_no_unique');

            $table->unique(
                ['article_no', 'branch_id'],
                'articles_article_no_branch_id_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropUnique('articles_article_no_branch_id_unique');

            $table->unique(
                'article_no',
                'articles_article_no_unique'
            );
        });
    }
};