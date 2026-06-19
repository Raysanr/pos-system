<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('pancake_shops')->cascadeOnDelete();
            $table->string('pancake_id')->index();
            $table->string('name')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('gender')->nullable();
            $table->date('birthday')->nullable();
            $table->string('province')->nullable()->index();
            $table->string('district')->nullable();
            $table->string('ward')->nullable();
            $table->text('address')->nullable();
            $table->string('customer_level')->nullable();
            $table->boolean('is_new_customer')->default(true);
            $table->boolean('is_wholesale')->default(false);
            $table->integer('total_orders')->default(0);
            $table->decimal('total_spent', 15, 2)->default(0);
            $table->decimal('reward_points', 10, 2)->default(0);
            $table->string('referred_by')->nullable();
            $table->json('tags')->nullable();
            $table->json('pancake_tags')->nullable();
            $table->json('utm_source')->nullable();
            $table->timestamp('first_order_at')->nullable();
            $table->timestamp('last_order_at')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
            $table->unique(['shop_id', 'pancake_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('customers'); }
};
