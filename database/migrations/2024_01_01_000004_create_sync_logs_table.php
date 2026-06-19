<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('pancake_shops')->cascadeOnDelete();
            $table->string('type'); // customers | orders
            $table->string('status'); // running | success | failed
            $table->integer('records_fetched')->default(0);
            $table->integer('records_upserted')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('sync_logs'); }
};
