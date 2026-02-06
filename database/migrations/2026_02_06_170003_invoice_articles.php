<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoice_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->foreignId('article_id')->constrained('articles')->onDelete('cascade');
            $table->string('description')->nullable();
            $table->integer('invoice_pcs')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('invoice_articles');
    }
};
