<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'fabric_report_type')) {
                $column = $table->string('fabric_report_type')->default('worker');

                if (Schema::hasColumn('users', 'physical_quantity_report_type')) {
                    $column->after('physical_quantity_report_type');
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'fabric_report_type')) {
                $table->dropColumn('fabric_report_type');
            }
        });
    }
};
