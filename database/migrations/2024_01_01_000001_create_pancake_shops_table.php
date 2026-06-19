<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('pancake_shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('shop_id');
            $table->string('api_key');
            $table->string('shop_name')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->integer('sync_interval_hours')->default(6);
            $table->timestamps();
            $table->unique(['user_id', 'shop_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('pancake_shops'); }
};
