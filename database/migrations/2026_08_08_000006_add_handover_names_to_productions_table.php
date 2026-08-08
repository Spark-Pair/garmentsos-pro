<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('productions')) {
            return;
        }

        Schema::table('productions', function (Blueprint $table) {
            if (!Schema::hasColumn('productions', 'issued_by_name')) {
                $table->string('issued_by_name')->nullable()->after('creator_id');
            }

            if (!Schema::hasColumn('productions', 'received_by_name')) {
                $table->string('received_by_name')->nullable()->after('issued_by_name');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('productions')) {
            return;
        }

        Schema::table('productions', function (Blueprint $table) {
            if (Schema::hasColumn('productions', 'received_by_name')) {
                $table->dropColumn('received_by_name');
            }

            if (Schema::hasColumn('productions', 'issued_by_name')) {
                $table->dropColumn('issued_by_name');
            }
        });
    }
};
