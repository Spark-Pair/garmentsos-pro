<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $requiredModules = [
        'auth',
        'auth_login',
        'setup',
        'first_run_setup',
        'subscription_expired',
        'branch_logo_delivery',
        'home',
        'dashboard',
        'developer_settings',
        'developer_branches',
        'developer_backups',
        'developer_updater',
        'developer_license',
        'developer_audit_logs',
    ];

    public function up(): void
    {
        $now = now();

        $this->ensureAppPermissionRulesTable();

        if (Schema::hasTable('module_settings')) {
            foreach (config('modules', []) as $moduleKey => $config) {
                $exists = DB::table('module_settings')->where('module_key', $moduleKey)->exists();

                if (!$exists) {
                    DB::table('module_settings')->insert([
                        'module_key' => $moduleKey,
                        'enabled' => (bool) ($config['default_enabled'] ?? true),
                        'visible_in_sidebar' => true,
                        'reason' => null,
                        'created_by' => null,
                        'updated_by' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            DB::table('module_settings')
                ->whereIn('module_key', $this->requiredModules)
                ->update([
                    'enabled' => true,
                    'visible_in_sidebar' => true,
                    'updated_at' => $now,
                ]);
        }

        if (!Schema::hasTable('branch_module_settings')) {
            return;
        }

        foreach ($this->requiredModules as $moduleKey) {
            $global = DB::table('branch_module_settings')
                ->whereNull('branch_id')
                ->where('module_key', $moduleKey)
                ->first();
            $metadata = $this->metadataWithoutClientDisabled($global?->metadata ?? null);

            if ($global) {
                DB::table('branch_module_settings')
                    ->where('id', $global->id)
                    ->update([
                        'branch_enabled' => true,
                        'status' => 'active',
                        'metadata' => json_encode($metadata),
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('branch_module_settings')->insert([
                    'branch_id' => null,
                    'module_key' => $moduleKey,
                    'branch_enabled' => true,
                    'default_branch_id' => null,
                    'allow_user_switching' => false,
                    'status' => 'active',
                    'metadata' => json_encode($metadata),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        //
    }

    private function metadataWithoutClientDisabled(?string $raw): array
    {
        $metadata = [];

        if ($raw) {
            $decoded = json_decode($raw, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        unset($metadata['client_disabled']);

        return $metadata;
    }

    private function ensureAppPermissionRulesTable(): void
    {
        if (!Schema::hasTable('app_permission_rules')) {
            Schema::create('app_permission_rules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->string('role')->nullable();
                $table->string('module_key')->nullable();
                $table->boolean('can_view')->default(true);
                $table->boolean('can_create')->default(true);
                $table->boolean('can_update')->default(true);
                $table->boolean('can_delete')->default(false);
                $table->boolean('can_override')->default(false);
                $table->boolean('can_switch')->default(false);
                $table->boolean('can_manage')->default(false);
                $table->timestamps();
                $table->index(['user_id', 'module_key']);
                $table->index(['role', 'module_key']);
            });

            return;
        }

        if (!Schema::hasColumn('app_permission_rules', 'can_override')) {
            Schema::table('app_permission_rules', function (Blueprint $table) {
                $table->boolean('can_override')->default(false)->after('can_delete');
            });
        }
    }
};
