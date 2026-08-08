<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_permission_rules') || Schema::hasColumn('app_permission_rules', 'can_override')) {
            return;
        }

        Schema::table('app_permission_rules', function (Blueprint $table) {
            $table->boolean('can_override')->default(false)->after('can_delete');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('app_permission_rules') || !Schema::hasColumn('app_permission_rules', 'can_override')) {
            return;
        }

        Schema::table('app_permission_rules', function (Blueprint $table) {
            $table->dropColumn('can_override');
        });
    }
};
