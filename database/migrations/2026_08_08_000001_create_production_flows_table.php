<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_id')->nullable()->constrained('productions')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('work_id')->constrained('setups')->cascadeOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('movement_type', 20);
            $table->string('part');
            $table->decimal('quantity', 12, 2);
            $table->string('ticket')->nullable();
            $table->string('parent_ticket')->nullable();
            $table->date('date');
            $table->timestamps();

            $table->index(['article_id', 'part', 'movement_type'], 'production_flows_article_part_type_idx');
            $table->index(['ticket', 'movement_type'], 'production_flows_ticket_type_idx');
            $table->index(['parent_ticket', 'movement_type'], 'production_flows_parent_type_idx');
            $table->index(['branch_id', 'article_id'], 'production_flows_branch_article_idx');
        });

        DB::table('productions')
            ->join('articles', 'articles.id', '=', 'productions.article_id')
            ->join('setups', 'setups.id', '=', 'productions.work_id')
            ->select([
                'productions.id',
                'productions.article_id',
                'productions.work_id',
                'productions.worker_id',
                'productions.branch_id',
                'productions.ticket',
                'productions.issue_date',
                'productions.receive_date',
                'productions.parts',
                'articles.quantity',
                'articles.extra_pcs',
                'setups.title as work_title',
            ])
            ->orderBy('productions.id')
            ->chunk(200, function ($productions) {
                foreach ($productions as $production) {
                    $parts = json_decode($production->parts ?? '[]', true);
                    if (!is_array($parts) || empty($parts)) {
                        continue;
                    }

                    $quantity = (float) ($production->quantity ?? 0) + (float) ($production->extra_pcs ?? 0);
                    if ($quantity <= 0) {
                        continue;
                    }

                    $isCutting = strcasecmp(trim((string) $production->work_title), 'Cutting') === 0;
                    $base = [
                        'production_id' => $production->id,
                        'article_id' => $production->article_id,
                        'work_id' => $production->work_id,
                        'worker_id' => $production->worker_id,
                        'branch_id' => $production->branch_id,
                        'ticket' => $production->ticket,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    foreach ($parts as $part) {
                        $part = trim((string) $part);
                        if ($part === '') {
                            continue;
                        }

                        if ($production->issue_date) {
                            DB::table('production_flows')->insert(array_merge($base, [
                                'movement_type' => 'issue',
                                'part' => $part,
                                'quantity' => $quantity,
                                'parent_ticket' => null,
                                'date' => $production->issue_date,
                            ]));
                        }

                        if ($production->receive_date) {
                            DB::table('production_flows')->insert(array_merge($base, [
                                'movement_type' => 'receive',
                                'part' => $part,
                                'quantity' => $quantity,
                                'parent_ticket' => $isCutting ? null : $production->ticket,
                                'date' => $production->receive_date,
                            ]));
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_flows');
    }
};
