<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('app_permission_rules')) {
            return;
        }

        Schema::create('app_permission_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->string('module_key')->nullable();
            $table->boolean('can_view')->default(true);
            $table->boolean('can_create')->default(true);
            $table->boolean('can_update')->default(true);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_switch')->default(false);
            $table->boolean('can_manage')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'module_key']);
            $table->index(['role', 'module_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_permission_rules');
    }
};
