<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('pancake_shops')->cascadeOnDelete();
            $table->string('pancake_id')->index();
            $table->string('order_code')->nullable()->index();
            $table->string('customer_pancake_id')->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('status')->nullable()->index();
            $table->string('payment_method')->nullable();
            $table->string('payment_status')->nullable();
            $table->decimal('total_price', 15, 2)->default(0);
            $table->decimal('shipping_fee', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('cod_amount', 15, 2)->default(0);
            $table->string('courier')->nullable()->index();
            $table->string('tracking_code')->nullable();
            $table->string('shipping_status')->nullable();
            $table->string('province')->nullable()->index();
            $table->string('district')->nullable();
            $table->string('ward')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('channel')->nullable();
            $table->string('warehouse')->nullable();
            $table->boolean('is_rts')->default(false)->index();
            $table->boolean('is_returned')->default(false)->index();
            $table->boolean('is_cancelled')->default(false)->index();
            $table->boolean('is_wholesale')->default(false);
            $table->string('extra_note')->nullable();
            $table->json('items')->nullable();
            $table->json('utm_data')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('ordered_at')->nullable()->index();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['shop_id', 'pancake_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('orders'); }
};
