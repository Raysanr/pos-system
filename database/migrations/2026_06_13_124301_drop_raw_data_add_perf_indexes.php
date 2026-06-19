<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('raw_data');
            $table->index(['shop_id', 'status', 'ordered_at'], 'orders_shop_status_date');
            $table->index(['shop_id', 'customer_pancake_id', 'ordered_at'], 'orders_shop_customer_date');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('raw_data');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('raw_data')->nullable();
            $table->dropIndex('orders_shop_status_date');
            $table->dropIndex('orders_shop_customer_date');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->json('raw_data')->nullable();
        });
    }
};
