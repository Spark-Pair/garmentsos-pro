<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'sidebar_expanded')) {
                $column = $table->boolean('sidebar_expanded')->default(false);

                if (Schema::hasColumn('users', 'menu_shortcuts')) {
                    $column->after('menu_shortcuts');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'sidebar_expanded')) {
                $table->dropColumn('sidebar_expanded');
            }
        });
    }
};
