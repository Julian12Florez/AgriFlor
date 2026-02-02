<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('date');
            $table->foreignUuid('worker_id')->constrained('workers')->onDelete('restrict');
            $table->foreignUuid('task_id')->constrained('tasks')->onDelete('restrict');
            $table->string('worker_code', 50);
            $table->string('task_code', 50);
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->json('deductions_detail')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignUuid('processed_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->index('date');
            $table->index('worker_code');
            $table->index('task_code');
            $table->index('processed_by');
            $table->index(['date', 'worker_id']);
            $table->index(['date', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_assignments');
    }
};
